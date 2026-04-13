/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_NAME: string
  readonly VITE_ENABLE_PINIA_COLADA_DEVTOOL: string
  readonly VITE_APP_CLARITY_PROJECT_ID: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
