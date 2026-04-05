/// <reference types="cypress" />
import Unknown from './Unknown.vue';

describe('<Unknown />', () => {
    it('renders with props', () => {
        cy.mount(Unknown, {
            props: {
                type: 'Component',
                name: 'MyWidget',
            },
        });

        cy.get('h1').should('contain', 'Unknown Component "MyWidget"');
    });
});
