import { defineConfig } from 'cypress'

export default defineConfig({
  pageLoadTimeout: 120000,
  retries: {
    runMode: 2,
    openMode: 0,
  },
  e2e: {
    //plugins/index.js removed with cypress 10x
    //https://docs.cypress.io/guides/references/migration-guide#Plugins-File-Removed
    setupNodeEvents(on, config) {

    },
    baseUrl: 'http://localhost:1511',
    specPattern: './cypress/e2e/**/*.spec.js',
  },
})
