import { defineConfig } from 'cypress'
import * as fs from 'fs'

export default defineConfig({
 // video: true,
  pageLoadTimeout: 120000,
  retries: {
    runMode: 2,
    openMode: 0,
  },
  e2e: {
    
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      on('after:spec', (spec, results) => {
        if (results && results.video) {
          // Do we have failures for any retry attempts?
          const failures = results.tests.some((test) =>
            test.attempts.some((attempt) => attempt.state === 'failed')
          )
          if (!failures) {
            // delete the video if the spec passed and no tests retried
            fs.unlinkSync(results.video)
          }
        }
      })
      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: 'http://localhost:1511',
    specPattern: 'cypress/e2e/**/*.spec.js',
    projectId: 'es51qa'
  },
})
