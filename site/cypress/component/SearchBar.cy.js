import SearchBar from '../../vue/src/components/forum/SearchBar.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('SearchBar', () => {
    it('shows clear button only when input is non-empty, and clears on click', () => {
        cy.mount(SearchBar, { props: { csrfToken: 'test-token' } });
        cy.get('[data-testid="search-bar-vue"] #search-clear').should('not.exist');
        cy.get('[data-testid="search-bar-vue"] input').type('hello');
        cy.get('[data-testid="search-bar-vue"] #search-clear').should('exist').click();
        cy.get('[data-testid="search-bar-vue"] #search-clear').should('not.exist');
        cy.get('[data-testid="search-bar-vue"] input').should('have.value', '');
    });

    it('emits search with trimmed value on Enter', () => {
        mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
        cy.get('[data-testid="search-bar-vue"] input').type('  homework 1  {enter}');
        cy.get('@searchHandler').should('have.been.calledWith', 'homework 1');
        cy.get('[data-testid="search-bar-vue"] input').should('have.value', 'homework 1');
    });

    it('emits empty on clear-button click', () => {
        mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
        cy.get('[data-testid="search-bar-vue"] input').type('hello');
        cy.get('[data-testid="search-bar-vue"] #search-clear').click();
        cy.get('@searchHandler').should('have.been.calledWith', '');
    });

    it('emits empty when input contains only whitespace on Enter', () => {
        mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
        cy.get('[data-testid="search-bar-vue"] input').type('   {enter}');
        cy.get('@searchHandler').should('have.been.calledWith', '');
    });

    it('passes special characters through unescaped', () => {
        mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
        cy.get('[data-testid="search-bar-vue"] input').type('O\'Brien & "The Boss" <test>{enter}');
        cy.get('@searchHandler').should('have.been.calledWith', 'O\'Brien & "The Boss" <test>');
    });

    it('accepts an initial searchQuery prop', () => {
        cy.mount(SearchBar, { props: { csrfToken: 'test-token', searchQuery: 'initial' } });
        cy.get('[data-testid="search-bar-vue"] input').should('have.value', 'initial');
        cy.get('[data-testid="search-bar-vue"] #search-clear').should('exist');
    });

    it('trims value on change (blur)', () => {
        cy.mount(SearchBar, { props: { csrfToken: 'test-token' } });
        cy.get('[data-testid="search-bar-vue"] input').type('  abc  ');
        cy.get('[data-testid="search-bar-vue"] input').blur();
        cy.get('[data-testid="search-bar-vue"] input').should('have.value', 'abc');
    });
});
