import tailwindcss from "@tailwindcss/vite";

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  css: [  '@owlint/feathers-vue/style.css', '~/assets/css/main.css'],

  runtimeConfig: {
    public:{
      mistralApiKey: '',
      tilesApiKey: '',
      tilesApiUrl: '',
      mistralAgentSourced: '',
      mistralAgentChat: '',
      mistralAgentTimeline: '',
      mistralAgentProducts: '',
      mistralAgentJobs: ''
    }
  },

  app: {
    head: {
      script: [
        {
          src: "https://kit.fontawesome.com/84dcc6b9fc.js",
          crossorigin: "anonymous",
        },
      ],
      htmlAttrs: {
        "data-theme": "indigo",
      },
    },
  },

  routeRules: {
    '/cards/**': {
      ssr: false
    },
  },

  piniaPluginPersistedstate: {
    debug: true
  },

  vite: {
    plugins: [
      tailwindcss(),
    ],
  },
  modules: [
    '@nuxtjs/leaflet',
    '@pinia/nuxt',
    'pinia-plugin-persistedstate/nuxt',
    '@sidebase/nuxt-auth'
  ],

  auth: {
    baseURL: process.env.AUTH_ORIGIN,
    provider: {
      type: 'local',
      endpoints: {
        signIn: { path: '/api/auth/login', method: 'post' },
        signOut: { path: '/api/auth/logout', method: 'post' },
        signUp: { path: '/api/auth/register', method: 'post' },
        getSession: { path: '/api/auth/session', method: 'get' }
      },
      pages: {
        login: '/auth/login',
        signup: '/auth/signup',
        forgotPassword: '/auth/forgot-password',
        resetPassword: '/auth/reset-password'
      },
      token: {
        signInResponseTokenPointer: '/accessToken'
      }
    }
  }
})