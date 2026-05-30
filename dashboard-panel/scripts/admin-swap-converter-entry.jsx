import React from 'react';
import { createRoot } from 'react-dom/client';
import { LiFiWidget, widgetEvents as liFiWidgetEvents } from '@lifi/widget';
import { widgetEvents as walletManagementEvents } from '@lifi/wallet-management';

const mountedRoots = new WeakMap();
const STORAGE_PREFIX = 'subme-swap-converter';
const ETHEREUM_CHAIN_ID = 1;
const BNB_CHAIN_ID = 56;
const POLYGON_CHAIN_ID = 137;
const SOLANA_CHAIN_ID = 1151111081099710;
const ETHEREUM_USDT_TOKEN = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
const POLYGON_USDT_TOKEN = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
const EVM_CHAIN_IDS = new Set([ETHEREUM_CHAIN_ID, BNB_CHAIN_ID, POLYGON_CHAIN_ID]);
const EXECUTION_NOTICE_STARTED = {
  type: 'info',
  title: 'Wymiana rozpoczęta',
  text: 'Podpisz wymagane zgody w portfelu. Po zatwierdzeniu transakcji nie odświeżaj strony i nie zamykaj tej zakładki - bridge lub swap może potrwać kilka minut.',
};
const EXECUTION_NOTICE_PENDING = {
  type: 'info',
  title: 'Transakcja jest w toku',
  text: 'Poczekaj na kolejne powiadomienia w widgetcie LI.FI. Nie odświeżaj strony, nawet jeśli MetaMask lub inny portfel przez chwilę nie pokazuje zmian.',
};
const EXECUTION_NOTICE_SUCCESS = {
  type: 'success',
  title: 'Wymiana zakończona',
  text: 'LI.FI potwierdziło zakończenie trasy. Sprawdź saldo i adres docelowy w portfelu.',
};
const EXECUTION_NOTICE_FAILED = {
  type: 'danger',
  title: 'Wymiana wymaga uwagi',
  text: 'LI.FI zgłosiło problem z wykonaniem trasy. Sprawdź komunikat w widgetcie albo portfelu przed ponowną próbą.',
};
const ROUTE_NOTICE_NO_ROUTES = {
  type: 'warning',
  title: 'Brak trasy LI.FI dla tego wyboru',
  text: 'Spróbuj wpisać kwotę po stronie Send, zwiększyć kwotę albo wybrać inną parę sieć-token. Część tras nie działa dla zbyt małych kwot lub wpisywania oczekiwanej kwoty po stronie Receive.',
};

function normalizeChainType(chainType) {
  return String(chainType || '').toUpperCase();
}

function getChainTypeForChainId(chainId) {
  const id = Number(chainId);
  if (EVM_CHAIN_IDS.has(id)) {
    return 'EVM';
  }
  if (id === SOLANA_CHAIN_ID) {
    return 'SVM';
  }
  return '';
}

function getAddressValue(value) {
  if (!value) {
    return '';
  }
  return typeof value === 'string' ? value : value.address || '';
}

function getExecutionNoticeForUpdate(update) {
  const status = String(update?.process?.status || '').toUpperCase();
  if (status === 'ACTION_REQUIRED' || status === 'MESSAGE_REQUIRED') {
    return EXECUTION_NOTICE_STARTED;
  }
  if (status === 'PENDING' || status === 'STARTED') {
    return EXECUTION_NOTICE_PENDING;
  }
  return null;
}

function getRequestUrl(input) {
  if (typeof input === 'string') {
    return input;
  }
  if (input instanceof URL) {
    return input.href;
  }
  return input?.url || '';
}

function isLiFiRouteRequest(url) {
  return /li\.quest\/v1\/(advanced\/routes|quote)/i.test(String(url || ''));
}

function getRouteErrorNotice(errorData) {
  const message = String(errorData?.message || '');
  if (/not configured for collecting fees/i.test(message)) {
    return ROUTE_NOTICE_NO_ROUTES;
  }
  if (/no available quotes|no routes/i.test(message)) {
    return ROUTE_NOTICE_NO_ROUTES;
  }
  if (message) {
    return {
      type: 'danger',
      title: 'LI.FI nie zwróciło trasy',
      text: message,
    };
  }
  return ROUTE_NOTICE_NO_ROUTES;
}

function ExecutionNotice({ notice }) {
  if (!notice) {
    return null;
  }

  return React.createElement(
    'div',
    {
      className: `admin-swap-execution-alert admin-swap-execution-alert--${notice.type}`,
      role: 'status',
      'aria-live': 'polite',
    },
    React.createElement('span', { className: 'admin-swap-execution-alert__icon' }, 'i'),
    React.createElement(
      'span',
      { className: 'admin-swap-execution-alert__body' },
      React.createElement('strong', null, notice.title),
      React.createElement('span', null, notice.text)
    )
  );
}

function ExecutionNoticeModal({ open, onClose }) {
  React.useEffect(() => {
    if (!open) {
      return undefined;
    }

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  const handleBackdropClick = (event) => {
    if (event.target === event.currentTarget) {
      onClose();
    }
  };

  return React.createElement(
    'div',
    {
      className: 'admin-swap-execution-modal',
      role: 'presentation',
      onClick: handleBackdropClick,
    },
    React.createElement(
      'div',
      {
        className: 'admin-swap-execution-modal__dialog',
        role: 'dialog',
        'aria-modal': 'true',
        'aria-labelledby': 'adminSwapExecutionModalTitle',
      },
      React.createElement(
        'button',
        {
          type: 'button',
          className: 'admin-swap-execution-modal__close',
          onClick: onClose,
          'aria-label': 'Zamknij komunikat',
        },
        'x'
      ),
      React.createElement('span', { className: 'admin-swap-execution-modal__eyebrow' }, 'LI.FI'),
      React.createElement(
        'h3',
        { id: 'adminSwapExecutionModalTitle', className: 'admin-swap-execution-modal__title' },
        'Wymiana jest uruchamiana'
      ),
      React.createElement(
        'p',
        { className: 'admin-swap-execution-modal__text' },
        'Jeśli portfel prosi o zgodę albo podpis transakcji, zatwierdź je i wróć do tej strony. Po zatwierdzeniu nie odświeżaj zakładki i poczekaj na kolejne statusy w widgetcie.'
      ),
      React.createElement(
        'p',
        { className: 'admin-swap-execution-modal__note' },
        'Swap lub bridge może potrwać kilka minut, szczególnie przy przejściu między sieciami.'
      ),
      React.createElement(
        'button',
        {
          type: 'button',
          className: 'admin-swap-execution-modal__button',
          onClick: onClose,
        },
        'Rozumiem, czekam'
      )
    )
  );
}

function SwapConverterWidget({ integrator, config }) {
  const formRef = React.useRef(null);
  const destinationAccountsRef = React.useRef(new Map());
  const currentToChainIdRef = React.useRef(config.toChain);
  const currentToAddressRef = React.useRef(getAddressValue(config.toAddress));
  const lastAutoAddressRef = React.useRef('');
  const [executionNotice, setExecutionNotice] = React.useState(null);
  const [executionModalOpen, setExecutionModalOpen] = React.useState(false);
  const [routeDiagnosticNotice, setRouteDiagnosticNotice] = React.useState(null);

  React.useEffect(() => {
    const setAutoDestinationAddress = (account) => {
      if (!account?.address || !account?.chainType || !formRef.current) {
        return false;
      }

      const address = String(account.address);
      const chainType = normalizeChainType(account.chainType);
      formRef.current.setFieldValue(
        'toAddress',
        {
          address,
          chainType,
          name: account.connectorName
            ? `Connected wallet (${account.connectorName})`
            : 'Connected wallet',
        },
        { setUrlSearchParam: false }
      );
      currentToAddressRef.current = address;
      lastAutoAddressRef.current = address;
      return true;
    };

    const clearAutoDestinationAddress = () => {
      if (!formRef.current || currentToAddressRef.current !== lastAutoAddressRef.current) {
        return;
      }
      formRef.current.setFieldValue('toAddress', '', { setUrlSearchParam: false });
      currentToAddressRef.current = '';
      lastAutoAddressRef.current = '';
    };

    const applySafeDefaultDestination = () => {
      const destinationChainType = getChainTypeForChainId(currentToChainIdRef.current);
      if (!destinationChainType) {
        return;
      }

      const compatibleAccount = destinationAccountsRef.current.get(destinationChainType);
      if (!compatibleAccount?.address) {
        clearAutoDestinationAddress();
        return;
      }

      const currentAddress = currentToAddressRef.current;
      const lastAutoAddress = lastAutoAddressRef.current;
      const hasManualAddress = currentAddress && currentAddress !== lastAutoAddress;
      if (hasManualAddress) {
        return;
      }

      setAutoDestinationAddress(compatibleAccount);
    };

    const handleWalletConnected = (account) => {
      const chainType = normalizeChainType(account?.chainType);
      if (!chainType || !account?.address) {
        return;
      }
      destinationAccountsRef.current.set(chainType, {
        address: account.address,
        chainType,
        connectorName: account.connectorName,
      });
      applySafeDefaultDestination();
    };

    const handleWalletDisconnected = (account) => {
      const chainType = normalizeChainType(account?.chainType);
      const storedAccount = destinationAccountsRef.current.get(chainType);
      if (storedAccount?.address === account?.address) {
        destinationAccountsRef.current.delete(chainType);
      }
      applySafeDefaultDestination();
    };

    const handleFormFieldChanged = (event) => {
      if (event?.fieldName === 'toChain') {
        currentToChainIdRef.current = event.newValue;
        applySafeDefaultDestination();
        return;
      }

      if (event?.fieldName === 'toAddress') {
        currentToAddressRef.current = getAddressValue(event.newValue);
      }

      if (event?.fieldName === 'toAmount' && Number(event.newValue) > 0) {
        setRouteDiagnosticNotice(ROUTE_NOTICE_NO_ROUTES);
      }
    };

    const handleAvailableRoutes = (routes) => {
      if (Array.isArray(routes) && routes.length > 0) {
        setRouteDiagnosticNotice(null);
        return;
      }
      if (Array.isArray(routes) && routes.length === 0) {
        setRouteDiagnosticNotice(ROUTE_NOTICE_NO_ROUTES);
      }
    };

    const handleRouteExecutionStarted = () => {
      setExecutionNotice(EXECUTION_NOTICE_STARTED);
      setExecutionModalOpen(true);
    };

    const handleRouteExecutionUpdated = (event) => {
      const nextNotice = getExecutionNoticeForUpdate(event);
      if (nextNotice) {
        setExecutionNotice(nextNotice);
      }
    };

    const handleRouteExecutionCompleted = () => {
      setExecutionNotice(EXECUTION_NOTICE_SUCCESS);
      setExecutionModalOpen(false);
    };

    const handleRouteExecutionFailed = () => {
      setExecutionNotice(EXECUTION_NOTICE_FAILED);
      setExecutionModalOpen(false);
    };

    walletManagementEvents.on('walletConnected', handleWalletConnected);
    walletManagementEvents.on('walletDisconnected', handleWalletDisconnected);
    liFiWidgetEvents.on('availableRoutes', handleAvailableRoutes);
    liFiWidgetEvents.on('formFieldChanged', handleFormFieldChanged);
    liFiWidgetEvents.on('routeExecutionStarted', handleRouteExecutionStarted);
    liFiWidgetEvents.on('routeExecutionUpdated', handleRouteExecutionUpdated);
    liFiWidgetEvents.on('routeExecutionCompleted', handleRouteExecutionCompleted);
    liFiWidgetEvents.on('routeExecutionFailed', handleRouteExecutionFailed);

    return () => {
      walletManagementEvents.off('walletConnected', handleWalletConnected);
      walletManagementEvents.off('walletDisconnected', handleWalletDisconnected);
      liFiWidgetEvents.off('availableRoutes', handleAvailableRoutes);
      liFiWidgetEvents.off('formFieldChanged', handleFormFieldChanged);
      liFiWidgetEvents.off('routeExecutionStarted', handleRouteExecutionStarted);
      liFiWidgetEvents.off('routeExecutionUpdated', handleRouteExecutionUpdated);
      liFiWidgetEvents.off('routeExecutionCompleted', handleRouteExecutionCompleted);
      liFiWidgetEvents.off('routeExecutionFailed', handleRouteExecutionFailed);
    };
  }, []);

  React.useEffect(() => {
    if (typeof window.fetch !== 'function') {
      return undefined;
    }

    let active = true;
    const originalFetch = window.fetch;
    const callFetch = originalFetch.bind(window);

    const patchedFetch = async (...args) => {
      const url = getRequestUrl(args[0]);
      const routeRequest = isLiFiRouteRequest(url);

      try {
        const response = await callFetch(...args);
        if (routeRequest) {
          if (response.ok) {
            setRouteDiagnosticNotice(null);
          } else {
            response
              .clone()
              .json()
              .then((errorData) => {
                if (active) {
                  setRouteDiagnosticNotice(getRouteErrorNotice(errorData));
                }
              })
              .catch(() => {
                if (active) {
                  setRouteDiagnosticNotice(ROUTE_NOTICE_NO_ROUTES);
                }
              });
          }
        }
        return response;
      } catch (error) {
        if (routeRequest && active) {
          setRouteDiagnosticNotice({
            type: 'danger',
            title: 'Nie udało się połączyć z LI.FI',
            text: 'Sprawdź połączenie z internetem albo spróbuj ponownie za chwilę.',
          });
        }
        throw error;
      }
    };

    window.fetch = patchedFetch;
    return () => {
      active = false;
      if (window.fetch === patchedFetch) {
        window.fetch = originalFetch;
      }
    };
  }, []);

  return React.createElement(
    'div',
    { className: 'admin-swap-widget-shell' },
    React.createElement(ExecutionNoticeModal, {
      open: executionModalOpen,
      onClose: () => setExecutionModalOpen(false),
    }),
    React.createElement(ExecutionNotice, { notice: routeDiagnosticNotice }),
    React.createElement(ExecutionNotice, { notice: executionNotice }),
    React.createElement(LiFiWidget, { integrator, config, formRef })
  );
}

function ensureLocalStorageNamespace() {
  if (window.__submeSwapStorageNamespaced || !window.Storage) {
    return;
  }

  const remapKey = (key) => {
    if (key === 'li.fi-widget-settings') {
      return `${STORAGE_PREFIX}-widget-settings`;
    }
    if (key === 'li.fi-widget-mode') {
      return `${STORAGE_PREFIX}-widget-mode`;
    }
    if (key === 'li.fi-widget-color-scheme') {
      return `${STORAGE_PREFIX}-widget-color-scheme`;
    }

    return key;
  };

  const { getItem, setItem, removeItem } = window.Storage.prototype;
  window.Storage.prototype.getItem = function getNamespacedItem(key) {
    return getItem.call(this, remapKey(String(key)));
  };
  window.Storage.prototype.setItem = function setNamespacedItem(key, value) {
    return setItem.call(this, remapKey(String(key)), value);
  };
  window.Storage.prototype.removeItem = function removeNamespacedItem(key) {
    return removeItem.call(this, remapKey(String(key)));
  };
  window.__submeSwapStorageNamespaced = true;
}

function renderSwapConverter(mount, options = {}) {
  if (!mount) {
    return;
  }

  const integrator = String(options.integrator || '').trim();
  if (!integrator) {
    throw new Error('Missing LI.FI integrator.');
  }

  const fee = Number.parseFloat(String(options.fee || '0.01'));
  const safeFee = Number.isFinite(fee) ? Math.max(0, Math.min(0.1, fee)) : 0.01;
  const apiKey = String(options.apiKey || '').trim();
  const allowedChains = Array.isArray(options.allowedChains)
    ? options.allowedChains
        .map((chainId) => Number(chainId))
        .filter((chainId) => Number.isSafeInteger(chainId) && chainId > 0)
    : [];
  const usdtDefaultsEnabled = allowedChains.length === 0
    || (allowedChains.includes(ETHEREUM_CHAIN_ID) && allowedChains.includes(POLYGON_CHAIN_ID));

  let root = mountedRoots.get(mount);
  if (!root) {
    root = createRoot(mount);
    mountedRoots.set(mount, root);
  }
  ensureLocalStorageNamespace();

  const widgetConfig = {
    integrator,
    apiKey: apiKey || undefined,
    fee: safeFee,
    slippage: 0.01,
    sdkConfig: {
      defaultRouteOptions: {
        slippage: 0.01,
      },
    },
    appearance: 'light',
    variant: 'compact',
    keyPrefix: STORAGE_PREFIX,
    buildUrl: false,
    hiddenUI: ['history'],
    requiredUI: ['toAddress'],
    ...(usdtDefaultsEnabled
      ? {
          fromChain: ETHEREUM_CHAIN_ID,
          fromToken: ETHEREUM_USDT_TOKEN,
          toChain: POLYGON_CHAIN_ID,
          toToken: POLYGON_USDT_TOKEN,
        }
      : {}),
    chains: allowedChains.length > 0 ? { allow: allowedChains } : undefined,
    languages: {
      default: 'pl',
    },
    theme: {
      palette: {
        primary: { main: '#e83e8c' },
      },
      container: {
        height: 'fit-content',
        overflow: 'visible',
        borderRadius: '14px',
        boxShadow: '0 12px 36px rgba(15, 23, 42, 0.08)',
      },
    },
  };

  root.render(
    React.createElement(
      'div',
      { style: { display: 'flex', justifyContent: 'center', width: '100%' } },
      React.createElement(SwapConverterWidget, { integrator, config: widgetConfig })
    )
  );
}

window.AdminSwapConverter = {
  render: renderSwapConverter,
};
