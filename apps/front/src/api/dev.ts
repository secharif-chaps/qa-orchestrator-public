import { apiClient } from './client'

export interface TunnelURLResponse {
  url: string
  is_tunnel: boolean
  source: string
}

/**
 * Get the current tunnel URL for Dify callbacks (dev mode only).
 * Returns the backend URL if no tunnel is available.
 */
export const getTunnelUrl = async (): Promise<TunnelURLResponse> => {
  const response = await apiClient.get<TunnelURLResponse>('/dev/tunnel-url')
  return response
}

/**
 * Check if we're in development mode.
 */
export const isDev = (): boolean => {
  return import.meta.env.DEV
}
