export const useApiService = () => {
  const config = useRuntimeConfig()
  const baseURL = config.public.backendApi
  const { getAccessToken } = useAuth()

  const fetchAPI = async (url: string, options: RequestInit = {}) => {
    const defaultHeaders: HeadersInit = {
      'Content-Type': 'application/json'
    }

    // Add authorization header if user is authenticated
    const accessToken = await getAccessToken()
    console.log('Access token for API call:', accessToken ? `${accessToken.substring(0, 20)}...` : 'null')

    if (accessToken) {
      defaultHeaders['Authorization'] = `Bearer ${accessToken}`
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
      // Handle unauthorized - redirect to login
      if (response.status === 401) {
        await navigateTo('/login')
        return
      }
      
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
