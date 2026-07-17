import SearchBar from '../../vue/src/components/forum/SearchBar.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

describe('SearchBar', () => {
    describe('rendering', () => {
        it('renders the search input with placeholder text', () => {
            cy.mount(SearchBar, { props: { csrfToken: 'test-token' } });
            cy.get('[data-testid="search-bar-vue"] input').should('have.attr', 'placeholder', 'Search here...');
        });

        it('does not show clear button when input is empty', () => {
            cy.mount(SearchBar, { props: { csrfToken: 'test-token' } });
            cy.get('[data-testid="search-bar-vue"] #search-clear').should('not.exist');
        });

        it('shows clear button when text is entered and removes it after clearing', () => {
            cy.mount(SearchBar, { props: { csrfToken: 'test-token' } });
            cy.get('[data-testid="search-bar-vue"] input').type('hello');
            cy.get('[data-testid="search-bar-vue"] #search-clear').should('exist');
            cy.get('[data-testid="search-bar-vue"] #search-clear').click();
            cy.get('[data-testid="search-bar-vue"] #search-clear').should('not.exist');
            cy.get('[data-testid="search-bar-vue"] input').should('have.value', '');
        });
    });

    describe('emits', () => {
        it('emits search with trimmed value on Enter key', () => {
            mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
            cy.get('[data-testid="search-bar-vue"] input').type('  homework 1  {enter}');
            cy.get('@searchHandler').should('have.been.calledWith', 'homework 1');
        });

        it('emits search with empty string when clear button is clicked', () => {
            mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
            cy.get('[data-testid="search-bar-vue"] input').type('hello');
            cy.get('[data-testid="search-bar-vue"] #search-clear').click();
            cy.get('@searchHandler').should('have.been.calledWith', '');
        });
    });

    describe('edge cases', () => {
        it('emits search with empty string when input contains only whitespace', () => {
            mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
            cy.get('[data-testid="search-bar-vue"] input').type('   {enter}');
            cy.get('@searchHandler').should('have.been.calledWith', '');
        });

        it('passes special characters through unescaped in the emit payload', () => {
            mountWithEmitSpy(SearchBar, 'search', { csrfToken: 'test-token' }, 'searchHandler');
            cy.get('[data-testid="search-bar-vue"] input').type('O\'Brien & "The Boss" <test>{enter}');
            cy.get('@searchHandler').should('have.been.calledWith', 'O\'Brien & "The Boss" <test>');
        });
    });
});
