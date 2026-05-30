import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createConfig, http, useAccount, useBalance, useConfig, useConnect, useDisconnect, useSwitchChain, WagmiProvider } from 'wagmi';
import { injected, walletConnect } from 'wagmi/connectors';
import { formatUnits, isAddress, parseUnits } from 'ethers';
import { prepareWagmiHinkal } from '@hinkal/common/providers/prepareWagmiHinkal';

const mountedRoots = new WeakMap();
const queryClients = new WeakMap();
const POLYGON_CHAIN_ID = 137;
const DEFAULT_WALLETCONNECT_PROJECT_ID = '5432e3507d41270bee46b7b85bbc2ef8';
const POLYGON_CHAIN = {
  id: POLYGON_CHAIN_ID,
  name: 'Polygon',
  nativeCurrency: {
    name: 'POL',
    symbol: 'POL',
    decimals: 18,
  },
  rpcUrls: {
    default: {
      http: ['https://polygon.drpc.org'],
    },
    public: {
      http: ['https://polygon.drpc.org'],
    },
  },
  blockExplorers: {
    default: {
      name: 'PolygonScan',
      url: 'https://polygonscan.com',
      apiUrl: 'https://api.polygonscan.com/api',
    },
  },
  contracts: {
    multicall3: {
      address: '0xca11bde05977b3631167028862be2a173976ca11',
      blockCreated: 25770160,
    },
  },
};
const POLYGON_USDT = {
  chainId: POLYGON_CHAIN_ID,
  erc20TokenAddress: '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
  name: 'Tether USD',
  symbol: 'USDT',
  decimals: 6,
};

function getWindowProvider() {
  if (typeof window === 'undefined') {
    return undefined;
  }

  return window.trustwallet
    || (window.ethereum && window.ethereum.isTrust ? window.ethereum : undefined)
    || window.ethereum;
}

function getInjectedProvider(predicate) {
  if (typeof window === 'undefined') {
    return undefined;
  }

  const ethereum = window.ethereum;
  const providers = Array.isArray(ethereum?.providers) ? ethereum.providers : [];
  const provider = providers.find(predicate);
  if (provider) {
    return provider;
  }

  return ethereum && predicate(ethereum) ? ethereum : undefined;
}

function getTrustProvider() {
  if (typeof window === 'undefined') {
    return undefined;
  }

  return window.trustwallet
    || getInjectedProvider((provider) => Boolean(provider?.isTrust || provider?.isTrustWallet));
}

function getOkxProvider() {
  if (typeof window === 'undefined') {
    return undefined;
  }

  return window.okxwallet?.ethereum
    || window.okxwallet
    || getInjectedProvider((provider) => Boolean(provider?.isOkxWallet || provider?.isOKExWallet));
}

function getMetaMaskProvider() {
  return getInjectedProvider((provider) => Boolean(provider?.isMetaMask && !provider?.isTrust));
}

function createPrivateSendConfig(projectId) {
  const walletConnectProjectId = String(projectId || DEFAULT_WALLETCONNECT_PROJECT_ID).trim()
    || DEFAULT_WALLETCONNECT_PROJECT_ID;

  return createConfig({
    chains: [POLYGON_CHAIN],
    connectors: [
      injected({
        target: {
          id: 'trust',
          name: 'Trust Wallet',
          provider: () => getTrustProvider(),
        },
        shimDisconnect: true,
      }),
      injected({
        target: {
          id: 'okx',
          name: 'OKX Wallet',
          provider: () => getOkxProvider(),
        },
        shimDisconnect: true,
      }),
      injected({
        target: {
          id: 'metaMask',
          name: 'MetaMask',
          provider: () => getMetaMaskProvider(),
        },
        shimDisconnect: true,
      }),
      walletConnect({
        projectId: walletConnectProjectId,
        showQrModal: true,
        metadata: {
          name: 'SUBME Private Send',
          description: 'Private USDT sends through Hinkal on Polygon.',
          url: typeof window !== 'undefined' ? window.location.origin : 'https://subme.pro',
          icons: [],
        },
      }),
    ],
    transports: {
      [POLYGON_CHAIN_ID]: http(POLYGON_CHAIN.rpcUrls.default.http[0]),
    },
  });
}

function normalizeDecimalInput(value) {
  return String(value || '').trim().replace(',', '.');
}

function parseUsdtAmount(value) {
  const normalized = normalizeDecimalInput(value);
  if (!/^\d+(\.\d{1,6})?$/.test(normalized)) {
    return null;
  }

  try {
    const parsed = parseUnits(normalized, POLYGON_USDT.decimals);
    return parsed > 0n ? parsed : null;
  } catch (error) {
    return null;
  }
}

function compactAddress(address) {
  const value = String(address || '');
  if (value.length <= 14) {
    return value;
  }
  return `${value.slice(0, 6)}...${value.slice(-4)}`;
}

function formatUsdt(value) {
  const formatted = formatUnits(value < 0n ? 0n : value, POLYGON_USDT.decimals);
  return formatted.replace(/\.?0+$/, '');
}

function normalizeBalanceForInput(value) {
  const normalized = normalizeDecimalInput(value);
  if (!/^\d+(\.\d+)?$/.test(normalized)) {
    return '';
  }

  const [integerPart, decimalPart = ''] = normalized.split('.');
  const cleanDecimal = decimalPart.slice(0, POLYGON_USDT.decimals).replace(/0+$/, '');
  return cleanDecimal ? `${integerPart}.${cleanDecimal}` : integerPart;
}

function getErrorMessage(error) {
  const message = error && (error.shortMessage || error.message || error.toString());
  return message ? String(message) : 'The operation could not be completed.';
}

function connectorMatches(connector, terms) {
  const haystack = `${connector?.id || ''} ${connector?.name || ''}`.toLowerCase();
  return terms.some((term) => haystack.includes(String(term).toLowerCase()));
}

function PrivateSendInner({ options }) {
  const wagmiConfig = useConfig();
  const account = useAccount();
  const { connectAsync, connectors, isPending: isConnecting } = useConnect();
  const { disconnectAsync } = useDisconnect();
  const { switchChainAsync, isPending: isSwitching } = useSwitchChain();
  const { data: usdtBalance } = useBalance({
    address: account.address,
    token: POLYGON_USDT.erc20TokenAddress,
    chainId: POLYGON_CHAIN_ID,
    query: {
      enabled: Boolean(account.isConnected && account.address),
    },
  });
  const [amount, setAmount] = React.useState('');
  const [recipient, setRecipient] = React.useState('');
  const [status, setStatus] = React.useState('');
  const [statusType, setStatusType] = React.useState('info');
  const [txHash, setTxHash] = React.useState('');
  const [passportLinks, setPassportLinks] = React.useState([]);
  const [isSubmitting, setIsSubmitting] = React.useState(false);
  const [isWalletModalOpen, setIsWalletModalOpen] = React.useState(false);

  const feePercent = Number.isFinite(options.feePercent)
    ? Math.max(0, Math.min(10, options.feePercent))
    : 1;
  const feeWallet = String(options.feeWallet || '').trim();
  const amountUnits = parseUsdtAmount(amount);
  const feeBps = Math.round(feePercent * 100);
  const feeUnits = amountUnits ? (amountUnits * BigInt(feeBps)) / 10000n : 0n;
  const recipientUnits = amountUnits ? amountUnits - feeUnits : 0n;
  const hasAmountText = normalizeDecimalInput(amount) !== '';
  const amountInvalid = hasAmountText && !amountUnits;
  const recipientText = recipient.trim();
  const recipientInvalid = recipientText !== '' && !isAddress(recipientText);
  const balanceUnits = typeof usdtBalance?.value === 'bigint' ? usdtBalance.value : null;
  const balanceLabel = usdtBalance ? normalizeBalanceForInput(usdtBalance.formatted || '0') : '';
  const balanceText = balanceLabel !== '' ? `${balanceLabel} USDT` : (account.isConnected ? 'Loading balance...' : 'USDT on Polygon');
  const amountExceedsBalance = Boolean(amountUnits && balanceUnits !== null && amountUnits > balanceUnits);
  const walletOptions = React.useMemo(() => {
    const findConnector = (terms) => connectors.find((connector) => connectorMatches(connector, terms));
    const walletConnectConnector = findConnector(['walletconnect', 'wallet connect']);
    const trustConnector = getTrustProvider() ? findConnector(['trust']) : walletConnectConnector;
    const okxConnector = getOkxProvider() ? findConnector(['okx']) : walletConnectConnector;
    const metaMaskConnector = getMetaMaskProvider() ? findConnector(['metamask']) : walletConnectConnector;
    return [
      {
        key: 'trust',
        label: 'Trust Wallet',
        hint: getTrustProvider() ? 'Extension' : 'WalletConnect',
        icon: 'trust',
        connector: trustConnector,
      },
      {
        key: 'okx',
        label: 'OKX Wallet',
        hint: getOkxProvider() ? 'Extension' : 'WalletConnect',
        icon: 'okx',
        connector: okxConnector,
      },
      {
        key: 'metamask',
        label: 'MetaMask',
        hint: getMetaMaskProvider() ? 'Extension' : 'WalletConnect',
        icon: 'metamask',
        connector: metaMaskConnector,
      },
      {
        key: 'walletconnect',
        label: 'WalletConnect',
        hint: 'QR / mobile',
        icon: 'walletconnect',
        connector: walletConnectConnector,
      },
    ];
  }, [connectors]);
  const canSubmit = Boolean(
    account.isConnected
    && amountUnits
    && recipientUnits > 0n
    && isAddress(recipient)
    && isAddress(feeWallet)
    && balanceUnits !== null
    && !amountExceedsBalance
    && !isSubmitting
  );

  const connectWallet = async (connector) => {
    setStatus('');
    setPassportLinks([]);
    setTxHash('');
    try {
      await connectAsync({ connector, chainId: POLYGON_CHAIN_ID });
      setStatus('Wallet connected. Check the recipient address and amount before sending.');
      setStatusType('success');
      setIsWalletModalOpen(false);
    } catch (error) {
      setStatus(getErrorMessage(error));
      setStatusType('danger');
    }
  };

  const fillMaxAmount = () => {
    if (!usdtBalance?.formatted) {
      return;
    }

    const maxAmount = normalizeBalanceForInput(usdtBalance.formatted);
    if (maxAmount !== '') {
      setAmount(maxAmount);
    }
  };

  const submitPrivateSend = async (event) => {
    event.preventDefault();
    setStatus('');
    setPassportLinks([]);
    setTxHash('');

    if (!account.connector || !account.isConnected) {
      setStatus('Connect Trust Wallet or another EVM wallet first.');
      setStatusType('warning');
      return;
    }
    if (!amountUnits || recipientUnits <= 0n) {
      setStatus('Enter a valid USDT amount on Polygon.');
      setStatusType('warning');
      return;
    }
    if (!isAddress(recipient)) {
      setStatus('The recipient address must be a valid EVM/Polygon address.');
      setStatusType('warning');
      return;
    }
    if (!isAddress(feeWallet)) {
      setStatus('Set a valid Hinkal fee wallet in Settings (admin mode) to apply the configured fee.');
      setStatusType('warning');
      return;
    }
    if (balanceUnits === null) {
      setStatus('Wait until the USDT balance on Polygon is loaded.');
      setStatusType('info');
      return;
    }
    if (amountExceedsBalance) {
      setStatus(`The amount is higher than the USDT balance on Polygon. Available balance: ${balanceLabel || '0'} USDT.`);
      setStatusType('danger');
      return;
    }

    setIsSubmitting(true);
    try {
      if (account.chainId !== POLYGON_CHAIN_ID && switchChainAsync) {
        setStatus('Switching wallet to Polygon...');
        setStatusType('info');
        await switchChainAsync({ chainId: POLYGON_CHAIN_ID });
      }

      setStatus('Preparing Hinkal. Your wallet may ask you to sign a message and transaction.');
      setStatusType('info');
      const hinkal = await prepareWagmiHinkal(account.connector, wagmiConfig);
      const hasAccessToken = await hinkal.checkAccessToken(POLYGON_CHAIN_ID);
      if (!hasAccessToken) {
        const links = hinkal.getSupportedPassportLinks();
        setPassportLinks(Array.isArray(links) ? links : []);
        setStatus('This wallet must complete one-time Hinkal verification before sending a private transaction.');
        setStatusType('warning');
        return;
      }

      setStatus('Sending privately through Hinkal. Do not close this page until all signatures are completed.');
      setStatusType('info');
      const recipientAmounts = feeUnits > 0n ? [recipientUnits, feeUnits] : [amountUnits];
      const recipientAddresses = feeUnits > 0n ? [recipient, feeWallet] : [recipient];
      const hash = await hinkal.depositAndWithdraw(
        POLYGON_USDT,
        recipientAmounts,
        recipientAddresses,
        undefined,
        undefined,
        'subme-private-send'
      );
      setTxHash(String(hash || ''));
      setStatus('The transaction has been sent through Hinkal.');
      setStatusType('success');
    } catch (error) {
      setStatus(getErrorMessage(error));
      setStatusType('danger');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className={`admin-private-send${account.isConnected ? ' is-connected' : ' is-disconnected'}`}>
      <div className="admin-private-send__topbar">
        <div className="admin-private-send__brand">
          <img className="admin-private-send__brand-logo" src="/img/hinkal.jpeg" alt="" aria-hidden="true" />
          <div>
            <span className="admin-private-send__eyebrow">Hinkal private send</span>
            <h3>Send privately</h3>
          </div>
        </div>
        <div className="admin-private-send__network">
          <span className="admin-private-send__network-dot" aria-hidden="true"></span>
          <span>Polygon</span>
        </div>
      </div>

      <div className="admin-private-send__wallet">
        {account.isConnected ? (
          <div className="admin-private-send__connected">
            <div>
              <span>Connected wallet</span>
              <strong>{compactAddress(account.address)}</strong>
            </div>
            <button type="button" className="admin-private-send__ghost-button admin-private-send__disconnect btn-danger" onClick={() => disconnectAsync()}>
              Disconnect
            </button>
          </div>
        ) : (
          <div className="admin-private-send__connect">
            <div>
              <strong>Connect wallet</strong>
            </div>
            <button
              type="button"
              className="admin-private-send__connect-primary"
              disabled={isConnecting}
              onClick={() => setIsWalletModalOpen(true)}>
              {isConnecting ? 'Connecting...' : 'Connect Wallet'}
            </button>
          </div>
        )}
      </div>

      <div className={`admin-private-send__trade-card${amountExceedsBalance ? ' has-balance-error' : ''}`}>
        <div className="admin-private-send__amount-row">
          <label className={`admin-private-send__amount-field${amountInvalid || amountExceedsBalance ? ' has-error' : ''}`}>
            <span>Amount</span>
            <input
              type="text"
              inputMode="decimal"
              placeholder="0.00"
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              autoComplete="off" />
          </label>
          <div className="admin-private-send__amount-actions">
            <button
              type="button"
              className="admin-private-send__max"
              disabled={!usdtBalance?.formatted}
              onClick={fillMaxAmount}>
              Max
            </button>
            <span className="admin-private-send__token-pill">
              <span className="admin-private-send__token-icon" aria-hidden="true">₮</span>
              <span>USDT</span>
            </span>
          </div>
        </div>

        <div className="admin-private-send__meta-row">
          <span className={`admin-private-send__balance${amountExceedsBalance ? ' is-error' : ''}`}>{account.isConnected ? `Balance: ${balanceText}` : balanceText}</span>
          <span>Fee {feePercent.toFixed(2).replace(/\.00$/, '')}%</span>
        </div>

        <label className={`admin-private-send__recipient${recipientInvalid ? ' has-error' : ''}`}>
          <span className="admin-private-send__recipient-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path d="M12 12.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
              <path d="M4.8 20a7.2 7.2 0 0 1 14.4 0"></path>
            </svg>
          </span>
          <input
            type="text"
            placeholder="Recipient Polygon address 0x..."
            value={recipient}
            onChange={(event) => setRecipient(event.target.value.trim())}
            autoComplete="off" />
        </label>
      </div>

      <div className="admin-private-send__summary">
        <div>
          <span>Recipient gets</span>
          <strong>{recipientUnits > 0n ? formatUsdt(recipientUnits) : '0'} USDT</strong>
        </div>
        <div>
          <span>Fee</span>
          <strong>{feeUnits > 0n ? formatUsdt(feeUnits) : '0'} USDT</strong>
        </div>
      </div>

      <form className="admin-private-send__form" onSubmit={submitPrivateSend}>
        {!isAddress(feeWallet) && (
          <div className="admin-private-send__notice admin-private-send__notice--warning" role="alert">
            <span>Set the Hinkal fee wallet in Settings (admin mode). The module cannot send with a fee until this is configured.</span>
          </div>
        )}

        {amountInvalid && (
          <div className="admin-private-send__notice admin-private-send__notice--warning" role="alert">
            <span>The amount must be greater than zero and use no more than 6 decimal places.</span>
          </div>
        )}

        {recipientInvalid && (
          <div className="admin-private-send__notice admin-private-send__notice--warning" role="alert">
            <span>The recipient address must be a valid EVM/Polygon address.</span>
          </div>
        )}

        {amountExceedsBalance && (
          <div className="admin-private-send__notice admin-private-send__notice--danger" role="alert">
            <span>You do not have enough USDT on Polygon. Available balance: {balanceLabel || '0'} USDT.</span>
          </div>
        )}

        {status && (
          <div className={`admin-private-send__notice admin-private-send__notice--${statusType}`} role="alert">
            <span>{status}</span>
          </div>
        )}

        <button type="submit" className="admin-private-send__submit" disabled={!canSubmit || isSwitching}>
          {isSubmitting || isSwitching ? 'Processing...' : 'Send privately'}
        </button>
      </form>

      {passportLinks.length > 0 && (
        <div className="admin-private-send__passport">
          {passportLinks.map((link) => (
            <a key={link} className="admin-private-send__ghost-button" href={link} target="_blank" rel="noopener noreferrer">
              Open Hinkal verification
            </a>
          ))}
        </div>
      )}

      {txHash && (
        <a className="admin-private-send__tx" href={`https://polygonscan.com/tx/${txHash}`} target="_blank" rel="noopener noreferrer">
          View transaction on PolygonScan
        </a>
      )}

      <div className="admin-private-send__fineprint">
        <span>Private USDT transfer through Hinkal on Polygon</span>
      </div>

      {isWalletModalOpen && (
        <div className="admin-private-wallet-modal" role="dialog" aria-modal="true" aria-labelledby="adminPrivateWalletTitle">
          <button type="button" className="admin-private-wallet-modal__backdrop" aria-label="Close wallet selection" onClick={() => setIsWalletModalOpen(false)}></button>
          <div className="admin-private-wallet-modal__panel">
            <div className="admin-private-wallet-modal__header">
              <div>
                <span>Polygon / USDT</span>
                <h4 id="adminPrivateWalletTitle">Connect a wallet</h4>
              </div>
              <button type="button" className="admin-private-wallet-modal__close" aria-label="Close" onClick={() => setIsWalletModalOpen(false)}>
                <span aria-hidden="true"></span>
              </button>
            </div>
            <div className="admin-private-wallet-modal__list">
              {walletOptions.map((walletOption) => (
                <button
                  type="button"
                  key={walletOption.key}
                  className={`admin-private-wallet-modal__option admin-private-wallet-modal__option--${walletOption.icon}`}
                  disabled={!walletOption.connector || isConnecting}
                  onClick={() => walletOption.connector && connectWallet(walletOption.connector)}>
                  <span className={`admin-private-wallet-modal__icon admin-private-wallet-modal__icon--${walletOption.icon}`} aria-hidden="true">
                    <span></span>
                  </span>
                  <span className="admin-private-wallet-modal__copy">
                    <strong>{walletOption.label}</strong>
                    <small>{walletOption.connector ? walletOption.hint : 'Not detected'}</small>
                  </span>
                  <span className="admin-private-wallet-modal__chevron" aria-hidden="true"></span>
                </button>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function renderPrivateSend(mount, options = {}) {
  if (!mount) {
    return;
  }

  let root = mountedRoots.get(mount);
  if (!root) {
    root = createRoot(mount);
    mountedRoots.set(mount, root);
  }

  let queryClient = queryClients.get(mount);
  if (!queryClient) {
    queryClient = new QueryClient();
    queryClients.set(mount, queryClient);
  }

  const config = createPrivateSendConfig(options.walletConnectProjectId);
  root.render(
    <WagmiProvider config={config}>
      <QueryClientProvider client={queryClient}>
        <PrivateSendInner
          options={{
            feePercent: Number.parseFloat(String(options.feePercent || '1')),
            feeWallet: options.feeWallet || '',
          }} />
      </QueryClientProvider>
    </WagmiProvider>
  );
}

window.AdminHinkalPrivateSend = {
  render: renderPrivateSend,
};
