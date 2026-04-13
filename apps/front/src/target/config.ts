export const config = {
  appName: import.meta.env.VITE_APP_NAME || 'Target',
  enablePiniaColadaDevtool: import.meta.env.VITE_ENABLE_PINIA_COLADA_DEVTOOL === 'true',
  clarityKey: import.meta.env.VITE_APP_CLARITY_PROJECT_ID || '',
} as const
