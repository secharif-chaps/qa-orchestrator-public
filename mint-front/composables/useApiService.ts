export const useApiService = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.backendApi

  const fetchAPI = async (url: string, options: RequestInit = {}) => {
    const defaultHeaders: HeadersInit = {
      'Content-Type': 'application/json'
    }

    const response = await fetch(`${baseURL}${url}`, {
      ...options,
      headers: {
        ...defaultHeaders,
        ...options.headers
      }
    })

    // Handle HTTP errors
    if (!response.ok) {
      const errorData = await response.json().catch(() => null)
      const error = new Error(
        errorData?.detail || `API error: ${response.status} ${response.statusText}`
      )
      throw error
    }

    // Return null for 204 No Content
    if (response.status === 204) {
      return null
    }

    // Parse JSON response
    return await response.json()
  }

  return {
    get: <T>(url: string, options: RequestInit = {}): Promise<T> =>
      fetchAPI(url, { ...options, method: 'GET' }),

    post: <T>(url: string, data: any, options: RequestInit = {}): Promise<T> =>
      fetchAPI(url, {
        ...options,
        method: 'POST',
        body: JSON.stringify(data)
      }),

    put: <T>(url: string, data: any, options: RequestInit = {}): Promise<T> =>
      fetchAPI(url, {
        ...options,
        method: 'PUT',
        body: JSON.stringify(data)
      }),

    delete: <T>(url: string, options: RequestInit = {}): Promise<T> =>
      fetchAPI(url, { ...options, method: 'DELETE' })
  }
}
