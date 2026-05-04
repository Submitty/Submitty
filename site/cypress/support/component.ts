import { mount } from 'cypress/vue';
import $ from 'jquery';
import '../../public/css/server.css';

Cypress.Commands.add('mount', mount);

if (typeof window !== 'undefined') {
    window.$ = $;
    window.jQuery = $;
}
