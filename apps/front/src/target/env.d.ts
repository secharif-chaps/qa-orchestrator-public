/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_NAME: string
  readonly VITE_API_BASE_URL: string
  readonly OIDC_BASE_URL: string
  readonly OIDC_REALM: string
  readonly OIDC_CLIENT_ID: string
  readonly OIDC_PKCE_METHOD: string
  readonly OIDC_LOGOUT_REDIRECT_URI: string
  readonly VITE_ENABLE_PINIA_COLADA_DEVTOOL: string
  readonly VITE_APP_CLARITY_PROJECT_ID: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
