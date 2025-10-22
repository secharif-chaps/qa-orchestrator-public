import { computed } from "vue"

export interface Endpoints {
    baseUrl: string
    apiUrl: string
    keycloakRealm: string
    keycloakClientId: string
    keycloakUrl: string
}

export const useEndpointResolver = () => {

    const baseUrl =  window.location.origin


    const endpoints = computed(() => {

        const endpoints: Endpoints = {
            baseUrl: ``,
            apiUrl: ``,
            keycloakRealm: ``,
            keycloakClientId: ``,
            keycloakUrl: ``,
        }

        // if local dev 
        if( baseUrl.includes('localhost') ) {
            endpoints.apiUrl = import.meta.env.VITE_DEV_BACKEND_API
            endpoints.keycloakRealm = import.meta.env.VITE_DEV_KEYCLOAK_REALM
            endpoints.keycloakClientId = import.meta.env.VITE_DEV_KEYCLOAK_CLIENT_ID
            endpoints.keycloakUrl = import.meta.env.VITE_DEV_KEYCLOAK_URL
            endpoints.baseUrl = import.meta.env.VITE_DEV_BASE_URL

        // if preprod environment
        } else if( baseUrl.includes('10.0.1.2') ) {
            endpoints.apiUrl = import.meta.env.VITE_PREPROD_BACKEND_API
            endpoints.keycloakRealm = import.meta.env.VITE_PREPROD_KEYCLOAK_REALM
            endpoints.keycloakClientId = import.meta.env.VITE_PREPROD_KEYCLOAK_CLIENT_ID
            endpoints.keycloakUrl = import.meta.env.VITE_PREPROD_KEYCLOAK_URL
            endpoints.baseUrl = import.meta.env.VITE_PREPROD_BASE_URL

        // if prod environment
        } else if( baseUrl.includes('chapsmind.chapsvision.com') ) {
            endpoints.apiUrl = import.meta.env.VITE_PROD_BACKEND_API
            endpoints.keycloakRealm = import.meta.env.VITE_PROD_KEYCLOAK_REALM
            endpoints.keycloakClientId = import.meta.env.VITE_PROD_KEYCLOAK_CLIENT_ID
            endpoints.keycloakUrl = import.meta.env.VITE_PROD_KEYCLOAK_URL
            endpoints.baseUrl = import.meta.env.VITE_PROD_BASE_URL
        }

        return endpoints
    })

    return {
        endpoints,
    }
}