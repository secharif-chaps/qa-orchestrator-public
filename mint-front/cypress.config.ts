import { defineConfig } from "cypress";

export default defineConfig({
  e2e: {
    baseUrl: 'http://localhost:3000',
    supportFile: 'cypress/support/e2e.ts',
    specPattern: 'cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    video: false,
    screenshotOnRunFailure: true,
    defaultCommandTimeout: 10000,
    retries: {
      runMode: 2,
      openMode: 0
    },
    env: {
      NUXT_PUBLIC_MISTRAL_AGENT_SOURCED: 'ag:cc2224b6:20250306:mint-sourced:f9c9d8b8',
      NUXT_PUBLIC_MISTRAL_AGENT_CHAT: 'ag:cc2224b6:20250326:mint-chatbot:ea285e7a',
      NUXT_PUBLIC_MISTRAL_AGENT_TIMELINE: 'ag:cc2224b6:20250313:mint-timeline:971c6949',
      NUXT_PUBLIC_MISTRAL_AGENT_PRODUCTS: 'ag:cc2224b6:20250314:mint-products:0cdfa3ef',
      NUXT_PUBLIC_MISTRAL_AGENT_JOBS: 'ag:cc2224b6:20250326:mint-jobs:2e796afd',
      defaultLocale: 'en-US',
      supportedLocales: ['en-US', 'fr-FR'],
    },
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
  viewportWidth: 1280,
  viewportHeight: 720,
});
