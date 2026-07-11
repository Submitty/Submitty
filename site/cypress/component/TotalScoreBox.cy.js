import TotalScoreBox from '../../vue/src/components/ta_grading/TotalScoreBox.vue';

const baseProps = {
    userGroup: 3,
    peerOnlyGrader: false,
    peerGradeable: false,
    decimalPrecision: 3,
};

describe('TotalScoreBox', () => {
    it('renders auto, manual, and total for non-peer grader', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                autoGradingEarned: 50,
                autoGradingTotal: 100,
                taGradingEarned: 30,
                taGradingTotal: 80,
            },
        });
        cy.get('[data-testid="autograding-row"]').should('contain.text', '50.000 / 100.000');
        cy.get('[data-testid="manual-grading-row"]').should('contain.text', '30.000 / 80.000');
        cy.get('[data-testid="total-row"]').should('contain.text', '80.000 / 180.000');
    });

    it('renders only My Peer Grading Total for userGroup === 4', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                userGroup: 4,
                peerGradeEarned: 12,
                peerTotal: 20,
            },
        });
        cy.get('[data-testid="my-peer-row"]').should('contain.text', '12.000 / 20.000');
        cy.get('[data-testid="autograding-row"]').should('not.exist');
    });

    describe('badge colors', () => {
        it('applies red-background for < 50%', () => {
            cy.mount(TotalScoreBox, { props: { ...baseProps, taGradingEarned: 10, taGradingTotal: 100 } });
            cy.get('[data-testid="grading-total"]').should('have.class', 'red-background');
        });

        it('applies yellow-background for 50-99%', () => {
            cy.mount(TotalScoreBox, { props: { ...baseProps, taGradingEarned: 50, taGradingTotal: 100 } });
            cy.get('[data-testid="grading-total"]').should('have.class', 'yellow-background');
        });

        it('applies green-background for >= 100%', () => {
            cy.mount(TotalScoreBox, { props: { ...baseProps, taGradingEarned: 100, taGradingTotal: 100 } });
            cy.get('[data-testid="grading-total"]').should('have.class', 'green-background');
        });

        it('applies no badge class when both earned and total are 0 (EC)', () => {
            cy.mount(TotalScoreBox, { props: { ...baseProps, autoGradingEarned: 0, autoGradingTotal: 0 } });
            cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'red-background');
            cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'yellow-background');
            cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'green-background');
        });
    });

    it('shows minus sign for undefined earned values', () => {
        cy.mount(TotalScoreBox, { props: { ...baseProps, userGroup: 4, peerTotal: 20 } });
        cy.get('[data-testid="my-peer-row"]').should('contain.text', '\u2212');
    });
});
