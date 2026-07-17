import '@cypress/code-coverage/support';
import { mount } from 'cypress/vue';
import $ from 'jquery';

Cypress.Commands.add('mount', mount);

if (typeof window !== 'undefined') {
    window.$ = $;
    window.jQuery = $;
}
