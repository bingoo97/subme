import React from 'react';
import { createRoot } from 'react-dom/client';
import { Widget } from '@rango-dev/widget-embedded';

const mountedRoots = new WeakMap();
const DEFAULT_WALLETCONNECT_PROJECT_ID = '5432e3507d41270bee46b7b85bbc2ef8';

const BTC = { blockchain: 'BTC', address: null, symbol: 'BTC' };
const DOGE = { blockchain: 'DOGE', address: null, symbol: 'DOGE' };
const POLYGON_USDT = { blockchain: 'POLYGON', address: '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', symbol: 'USDT' };
const BSC_USDT = { blockchain: 'BSC', address: '0x55d398326f99059fF775485246999027B3197955', symbol: 'USDT' };
const ETH_USDT = { blockchain: 'ETH', address: '0xdAC17F958D2ee523a2206206994597C13D831ec7', symbol: 'USDT' };

const parseAffiliateWallets = (value) => {
  if (!value) {
    return {};
  }

  if (typeof value === 'object' && !Array.isArray(value)) {
    return value;
  }

  try {
    const parsed = JSON.parse(String(value));
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch (error) {
    return {};
  }
};

const clampPercent = (value) => {
  const percent = Number(value);
  if (!Number.isFinite(percent)) {
    return 1;
  }

  return Math.max(0, Math.min(3, percent));
};

function RangoConverter({ options }) {
  const apiKey = String(options.apiKey || '').trim();
  const walletConnectProjectId = String(options.walletConnectProjectId || DEFAULT_WALLETCONNECT_PROJECT_ID).trim() || DEFAULT_WALLETCONNECT_PROJECT_ID;
  const affiliateRef = String(options.affiliateRef || '').trim();
  const affiliatePercent = clampPercent(options.affiliatePercent);
  const affiliateWallets = parseAffiliateWallets(options.affiliateWallets);
  const hasAffiliateWallets = Object.keys(affiliateWallets).length > 0;

  const config = {
    apiKey,
    walletConnectProjectId,
    title: 'BTC/DOGE Converter',
    amount: 0,
    language: 'pl',
    variant: 'default',
    multiWallets: true,
    customDestination: true,
    excludeLiquiditySources: false,
    wallets: ['trust-wallet', 'okx', 'wallet-connect-2', 'metamask'],
    from: {
      blockchain: 'BTC',
      token: BTC,
      blockchains: ['BTC', 'DOGE'],
      pinnedTokens: [BTC, DOGE],
      tokens: [BTC, DOGE],
    },
    to: {
      blockchain: 'POLYGON',
      token: POLYGON_USDT,
      blockchains: ['POLYGON', 'BSC', 'ETH'],
      pinnedTokens: [POLYGON_USDT, BSC_USDT, ETH_USDT],
      tokens: [POLYGON_USDT, BSC_USDT, ETH_USDT],
    },
    affiliate: affiliateRef
      ? {
          ref: affiliateRef,
          percent: affiliatePercent,
          wallets: hasAffiliateWallets ? affiliateWallets : undefined,
        }
      : undefined,
    routing: {
      maxLength: 4,
      enableCentralizedSwappers: options.enableCentralizedSwappers ? 'enabled' : 'disabled',
    },
    features: {
      theme: 'hidden',
      language: 'hidden',
      customTokens: 'hidden',
    },
    theme: {
      mode: 'light',
      singleTheme: true,
      borderRadius: 16,
      secondaryBorderRadius: 999,
      width: 480,
      height: 720,
      colors: {
        light: {
          primary: '#e83e8c',
          secondary: '#f8fafc',
          background: '#ffffff',
          foreground: '#101828',
          neutral: '#667085',
        },
      },
    },
  };

  return (
    <div className="admin-rango-widget-shell">
      <Widget config={config} />
    </div>
  );
}

const render = (mount, options = {}) => {
  if (!mount) {
    throw new Error('Missing Rango mount element.');
  }

  if (!String(options.apiKey || '').trim()) {
    throw new Error('Missing Rango API key.');
  }

  let root = mountedRoots.get(mount);
  if (!root) {
    root = createRoot(mount);
    mountedRoots.set(mount, root);
  }

  root.render(<RangoConverter options={options} />);
};

window.AdminRangoConverter = {
  render,
};
