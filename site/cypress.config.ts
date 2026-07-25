import { defineConfig } from 'cypress';
import { cypressBrowserPermissionsPlugin } from 'cypress-browser-permissions';
import codeCoverageTask from '@cypress/code-coverage/task';
import cypressPlugins from './cypress/plugins/index.js';
import viteConfig from './vue/vite.config.mts';
import * as fs from 'fs';
import * as path from 'path';

function setupNodeEvents(on: Cypress.PluginEvents, config: Cypress.PluginConfigOptions) {
    on('after:spec', (spec, results) => {
        if (results && results.video) {
            // Do we have failures for any retry attempts?
            const failures = results.tests.some((test) =>
                test.attempts.some((attempt) => attempt.state === 'failed'),
            );

            if (!failures) {
                // Specify the full path to the video file
                const videoPath = path.resolve(results.video);

                // Check if the video file exists before attempting to delete it
                if (fs.existsSync(videoPath)) {
                    // delete the video if the spec passed and no tests retried
                    fs.unlinkSync(videoPath);
                }
            }
        }
    });

    config = cypressBrowserPermissionsPlugin(on, config);

    cypressPlugins(on, config);

    codeCoverageTask(on, config);

    return config;
}

export default defineConfig({
    video: true,
    pageLoadTimeout: 120000,
    retries: {
        runMode: 2,
        openMode: 0,
    },

    e2e: {
        setupNodeEvents,
        baseUrl: 'http://localhost:1511',
        specPattern: 'cypress/e2e/**/*.spec.js',
        projectId: 'es51qa',
    },

    component: {
        setupNodeEvents,
        devServer: {
            framework: 'vue',
            bundler: 'vite',
            viteConfig,
        },
        supportFile: 'cypress/support/component.js',
        specPattern: 'cypress/component/**/*.cy.{js,jsx,ts,tsx}',
    },

    env: {
        browserPermissions: {
            notifications: 'allow',
            geolocation: 'allow',
            camera: 'block',
            microphone: 'block',
            images: 'allow',
        },
    },
});
