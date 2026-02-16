export const config = {
  appName: import.meta.env.VITE_APP_NAME || 'Target',
  apiBaseUrl: import.meta.env.VITE_API_BASE_URL || 'https://basil.local/api',
  oidcBaseUrl: import.meta.env.OIDC_BASE_URL || 'https://auth.basil.local',
  oidcRealm: import.meta.env.OIDC_REALM || 'chapsmind-dev',
  oidcClientId: import.meta.env.OIDC_CLIENT_ID || 'basil-pwa',
  oidcPkceMethod: import.meta.env.OIDC_PKCE_METHOD || 'S256',
  oidcLogoutRedirectUri: import.meta.env.OIDC_LOGOUT_REDIRECT_URI || '/',
  enablePiniaColadaDevtool:
    import.meta.env.VITE_ENABLE_PINIA_COLADA_DEVTOOL === 'true',
  clarityKey: import.meta.env.VITE_CLARITY_KEY || '',
} as const;
