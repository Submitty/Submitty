const { defineConfig } = require('cypress');

module.exports = defineConfig({
  e2e: {
    specPattern: 'site/cypress/e2e/**/*.js',
    baseUrl: 'http://localhost:1511', // Change this to your local Submitty dev server URL if different
    supportFile: 'site/cypress/support/e2e.js',
    fixturesFolder: 'site/cypress/fixtures',
    screenshotsFolder: 'site/cypress/screenshots',
    videosFolder: 'site/cypress/videos',
    downloadsFolder: 'site/cypress/downloads',
  },
});
