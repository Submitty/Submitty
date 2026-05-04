/// <reference types="cypress" />

import type { MountingOptions, MountReturn } from 'cypress/vue';
import type { Component } from 'vue';

declare global {
    namespace Cypress {
        interface Chainable {
            mount(component: Component, options?: MountingOptions<any>): Chainable<MountReturn>;
        }
    }
}

export {};
