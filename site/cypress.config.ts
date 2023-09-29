import { defineConfig } from 'cypress'
export default defineConfig({
  video: true,
  videoCompression: 15,
  pageLoadTimeout: 120000,
  retries: {
    runMode: 2,
    openMode: 0,
  },
  e2e: {
    
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: 'http://localhost:1511',
    specPattern: 'cypress/e2e/**/*.spec.js',
    projectId: 'es51qa'
  },
})
