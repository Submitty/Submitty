import { mount } from 'cypress/vue';

// Define the global Cypress command
declare global {
    // eslint-disable-next-line @typescript-eslint/no-namespace
    namespace Cypress {
        interface Chainable {
            mount: typeof mount;
        }
    }
}

Cypress.Commands.add('mount', mount);
