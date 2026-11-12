/// <reference types="cypress" />
// ***********************************************************
// This plugins/index.js is used to register Node event listeners,
// such as tasks that need access to the filesystem or other programs.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// https://on.cypress.io/task
// ***********************************************************

import { spawnSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';
import vnu from 'vnu-jar';

/**
 * @type {Cypress.PluginConfig}
 */
export default function (on, config) {
    // Resolve relative paths against the project root, the same as cy.readFile and cy.writeFile
    const resolvePath = (target) => path.resolve(config.projectRoot, target);

    on('task', {
        /**
         * Recursively remove a file or directory, doing nothing if it does not exist
         * @param {string} target path relative to the project root
         */
        removePath(target) {
            fs.rmSync(resolvePath(target), { recursive: true, force: true });
            return null;
        },

        /**
         * Run the Nu Html Checker (vnu) on an HTML file, which requires Java to be on the PATH.
         * vnu exits with a non-zero status when it finds errors, so the status is returned rather than thrown.
         * @param {string} file path relative to the project root
         * @returns {{ status: number | null, stdout: string, stderr: string }}
         */
        validateHtml(file) {
            const result = spawnSync('java', ['-jar', vnu, '--format', 'json', resolvePath(file)], {
                encoding: 'utf8',
                maxBuffer: 64 * 1024 * 1024,
            });
            if (result.error) {
                throw result.error;
            }
            return { status: result.status, stdout: result.stdout, stderr: result.stderr };
        },
    });
}
