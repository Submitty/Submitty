import SearchBar from '../../vue/src/components/forum/SearchBar.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('SearchBar', () => {
    it('shows clear button only when input is non-empty, and clears on click', () => {
        cy.mount(SearchBar);
        cy.get('[data-testid="search-clear"]').should('not.exist');
        cy.get('[data-testid="search-content-input"]').type('hello');
        cy.get('[data-testid="search-clear"]').should('exist').click();
        cy.get('[data-testid="search-clear"]').should('not.exist');
        cy.get('[data-testid="search-content-input"]').should('have.value', '');
    });

    it('emits search with trimmed value on Enter', () => {
        mountWithEmitSpy(SearchBar, 'search', {}, 'searchHandler');
        cy.get('[data-testid="search-content-input"]').type('  homework 1  {enter}');
        cy.get('@searchHandler').should('have.been.calledWith', 'homework 1');
        cy.get('[data-testid="search-content-input"]').should('have.value', 'homework 1');
    });

    it('emits search with trimmed value on Search button click', () => {
        mountWithEmitSpy(SearchBar, 'search', {}, 'searchHandler');
        cy.get('[data-testid="search-content-input"]').type('  homework 1  ');
        cy.get('[data-testid="search-submit"]').click();
        cy.get('@searchHandler').should('have.been.calledWith', 'homework 1');
        cy.get('[data-testid="search-content-input"]').should('have.value', 'homework 1');
    });

    it('emits empty on clear-button click', () => {
        mountWithEmitSpy(SearchBar, 'search', {}, 'searchHandler');
        cy.get('[data-testid="search-content-input"]').type('hello');
        cy.get('[data-testid="search-clear"]').click();
        cy.get('@searchHandler').should('have.been.calledWith', '');
    });

    it('emits empty when input contains only whitespace on Enter', () => {
        mountWithEmitSpy(SearchBar, 'search', {}, 'searchHandler');
        cy.get('[data-testid="search-content-input"]').type('   {enter}');
        cy.get('@searchHandler').should('have.been.calledWith', '');
    });

    it('passes special characters through unescaped', () => {
        mountWithEmitSpy(SearchBar, 'search', {}, 'searchHandler');
        cy.get('[data-testid="search-content-input"]').type('O\'Brien & "The Boss" <test>{enter}');
        cy.get('@searchHandler').should('have.been.calledWith', 'O\'Brien & "The Boss" <test>');
    });

    it('accepts an initial searchQuery prop', () => {
        cy.mount(SearchBar, { props: { searchQuery: 'initial' } });
        cy.get('[data-testid="search-content-input"]').should('have.value', 'initial');
        cy.get('[data-testid="search-clear"]').should('exist');
    });

    it('trims value on change (blur)', () => {
        cy.mount(SearchBar);
        cy.get('[data-testid="search-content-input"]').type('  abc  ');
        cy.get('[data-testid="search-content-input"]').blur();
        cy.get('[data-testid="search-content-input"]').should('have.value', 'abc');
    });

    it('exposes accessible names for the search and clear buttons', () => {
        cy.mount(SearchBar);
        cy.get('[data-testid="search-submit"]').should('have.attr', 'title', 'Submit search');
        cy.get('[data-testid="search-content-input"]').type('a');
        cy.get('[data-testid="search-clear"]').should('have.attr', 'title', 'Clear search');
        cy.get('[data-testid="search-clear"]').should('have.attr', 'aria-label', 'Clear search');
    });

    it('clears via keyboard activation on the clear button', () => {
        mountWithEmitSpy(SearchBar, 'search', {}, 'searchHandler');
        cy.get('[data-testid="search-content-input"]').type('hello');
        cy.get('[data-testid="search-clear"]').focus();
        cy.get('[data-testid="search-clear"]').type('{enter}');
        cy.get('@searchHandler').should('have.been.calledWith', '');
        cy.get('[data-testid="search-content-input"]').should('have.value', '');
    });
});
