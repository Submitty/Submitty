/// <reference types="cypress" />
// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

// This function is called when a project is opened or re-opened (e.g. due to
// the project's config changing)

import vnuJar from 'vnu-jar';

/**
 * @type {Cypress.PluginConfig}
 */
export default function (on /* , config */) {
    on('task', {
        async vnuValidate(htmlPath) {
            let output;
            try {
                // Uses system Java >=11 if available, else downloads/caches Temurin 17.
                output = await vnuJar.vnu.check(['--format', 'json', htmlPath]);
            }
            catch (err) {
                // vnu exits non-zero when it emits messages; the JSON report is the message.
                output = err?.message ?? String(err);
            }
            if (!output.trimStart().startsWith('{')) {
                // Not a vnu JSON report — usually a Java resolve/download failure.
                throw new Error(`vnu did not return a JSON report:\n${output}`);
            }
            return { stderr: output };
        },
    });
}
