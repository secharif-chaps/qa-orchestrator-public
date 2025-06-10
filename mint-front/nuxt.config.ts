import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  css: ['@owlint/feathers-vue/style.css', '~/assets/css/main.css'],

  runtimeConfig: {
    authSecret: process.env.NUXT_AUTH_SECRET || 'your-secret-key-here',
    keycloakClientId: process.env.KEYCLOAK_CLIENT_ID || 'mint-front',
    keycloakClientSecret: process.env.KEYCLOAK_CLIENT_SECRET || '',
    keycloakIssuer: process.env.KEYCLOAK_ISSUER || 'http://localhost:8080/realms/mint-dev',
    public: {
      backendApi: process.env.NUXT_PUBLIC_BACKEND_API || 'http://localhost:8000',
      authBaseUrl: process.env.NUXT_PUBLIC_AUTH_BASE_URL || 'http://localhost:3000',
      mistralApiKey: '',
      tilesApiKey: '',
      tilesApiUrl: '',
      mistralAgentSourced: '',
      mistralAgentChat: '',
      mistralAgentTimeline: '',
      mistralAgentProducts: '',
      mistralAgentJobs: '',
      mistralAgentTeam: ''
    }
  },

  app: {
    head: {
      script: [
        {
          src: 'https://kit.fontawesome.com/84dcc6b9fc.js',
          crossorigin: 'anonymous'
        }
      ],
      htmlAttrs: {
        'data-theme': 'indigo'
      }
    }
  },

  ssr: false,

  piniaPluginPersistedstate: {
    debug: true
  },

  vite: {
    plugins: [tailwindcss()]
  },
  modules: [
    '@nuxtjs/leaflet',
    '@pinia/nuxt',
    'pinia-plugin-persistedstate/nuxt'
  ],
})
