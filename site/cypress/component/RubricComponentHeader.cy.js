import RubricComponentHeader from '../../vue/src/components/ta_grading/RubricComponentHeader.vue';

describe('RubricComponentHeader', () => {
    it('renders score / max_value as integers', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: 7.5, maxValue: 10 } });
        cy.get('[data-testid="grading-total"]').should('contain', '8 / 10');
    });

    it('renders em-dash when totalScore is null (ungraded)', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: null, maxValue: 10 } });
        cy.get('[data-testid="grading-total"]').should('contain', '\u2014 / 10');
    });

    it('applies no extra class when total=0 and max=0', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: 0, maxValue: 0 } });
        cy.get('[data-testid="grading-total"]')
            .should('not.have.class', 'red-background')
            .and('not.have.class', 'yellow-background')
            .and('not.have.class', 'green-background');
    });

    it('applies red-background when score < 50%', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: 3, maxValue: 10 } });
        cy.get('[data-testid="grading-total"]').should('have.class', 'red-background');
    });

    it('applies yellow-background when 50% <= score < 100%', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: 5, maxValue: 10 } });
        cy.get('[data-testid="grading-total"]').should('have.class', 'yellow-background');
    });

    it('applies green-background when score >= 100%', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: 10, maxValue: 10 } });
        cy.get('[data-testid="grading-total"]').should('have.class', 'green-background');
    });

    it('applies green-background when score exceeds max (extra credit)', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: 12, maxValue: 10 } });
        cy.get('[data-testid="grading-total"]').should('have.class', 'green-background');
    });

    it('applies red-background when totalScore is 0 but maxValue > 0', () => {
        cy.mount(RubricComponentHeader, { props: { totalScore: 0, maxValue: 10 } });
        cy.get('[data-testid="grading-total"]').should('have.class', 'red-background');
    });
});
