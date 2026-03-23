import { computed } from 'vue'

export interface Endpoints {
  baseUrl: string
  apiUrl: string
  keycloakRealm: string
  keycloakClientId: string
  keycloakUrl: string
}

export const useEndpointResolver = () => {
  const baseUrl = window.location.origin

  const endpoints = computed(() => {
    const endpoints: Endpoints = {
      baseUrl: import.meta.env.VITE_BASE_URL || baseUrl,
      apiUrl: import.meta.env.VITE_API_URL,
      keycloakRealm: import.meta.env.VITE_KEYCLOAK_REALM,
      keycloakClientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID,
      keycloakUrl: import.meta.env.VITE_KEYCLOAK_URL,
    }

    return endpoints
  })

  return {
    endpoints,
  }
}
