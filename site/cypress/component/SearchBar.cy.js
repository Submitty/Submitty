import { h, defineComponent } from 'vue';
import SearchBar from '../../vue/src/components/forum/SearchBar.vue';

function mountWithEmitSpy(alias = 'searchHandler') {
    const handler = cy.stub().as(alias);
    const Wrapper = defineComponent({
        setup() {
            return () => h(SearchBar, {
                csrfToken: 'test-token',
                onSearch: handler,
            });
        },
    });
    cy.mount(Wrapper);
    return handler;
}

describe('SearchBar', () => {
    describe('rendering', () => {
        it('renders the search input with placeholder text', () => {
            mountWithEmitSpy();
            cy.get('[data-testid="search-bar-vue"] input').should('have.attr', 'placeholder', 'Search here...');
        });

        it('hides clear button when input is empty', () => {
            mountWithEmitSpy();
            cy.get('[data-testid="search-bar-vue"] [title="Clear search"]').should('not.be.visible');
        });

        it('shows clear button when text is entered', () => {
            mountWithEmitSpy();
            cy.get('[data-testid="search-bar-vue"] input').type('hello');
            cy.get('[data-testid="search-bar-vue"] [title="Clear search"]').should('be.visible');
        });
    });

    describe('search submission', () => {
        it('emits search with trimmed value on Enter key', () => {
            mountWithEmitSpy('searchHandler');
            cy.get('[data-testid="search-bar-vue"] input').type('  homework 1  {enter}');
            cy.get('@searchHandler').should('have.been.calledWith', 'homework 1');
        });

        it('emits search with trimmed value on submit button click', () => {
            mountWithEmitSpy('searchHandler');
            cy.get('[data-testid="search-bar-vue"] input').type('hello');
            cy.get('[data-testid="search-submit"]').click();
            cy.get('@searchHandler').should('have.been.calledWith', 'hello');
        });

        it('emits search with empty string when clear button is clicked', () => {
            mountWithEmitSpy('searchHandler');
            cy.get('[data-testid="search-bar-vue"] input').type('hello');
            cy.get('[data-testid="search-bar-vue"] [title="Clear search"]').click();
            cy.get('@searchHandler').should('have.been.calledWith', '');
        });
    });

    describe('input behavior', () => {
        it('clears the input when clear button is clicked', () => {
            mountWithEmitSpy();
            cy.get('[data-testid="search-bar-vue"] input').type('hello');
            cy.get('[data-testid="search-bar-vue"] [title="Clear search"]').click();
            cy.get('[data-testid="search-bar-vue"] input').should('have.value', '');
        });

        it('hides clear button after clearing', () => {
            mountWithEmitSpy();
            cy.get('[data-testid="search-bar-vue"] input').type('hello');
            cy.get('[data-testid="search-bar-vue"] [title="Clear search"]').click();
            cy.get('[data-testid="search-bar-vue"] [title="Clear search"]').should('not.be.visible');
        });
    });
});
