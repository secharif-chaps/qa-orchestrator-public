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
  ],
})
