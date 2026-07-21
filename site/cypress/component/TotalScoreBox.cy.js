import TotalScoreBox from '../../vue/src/components/ta_grading/TotalScoreBox.vue';

const baseProps = {
    userGroup: 3,
    peerOnlyGrader: false,
    decimalPrecision: 3,
};

describe('TotalScoreBox', () => {
    it('renders all non-peer rows with badge colors and peerGradeable label', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                peerGradeable: true,
                autoGradingEarned: 5,
                autoGradingTotal: 0,
                taGradingEarned: 10,
                taGradingTotal: 100,
            },
        });
        // earned>0 on zero-total → green
        cy.get('[data-testid="autograding-row"] .badge').should('have.class', 'green-background');
        // < 50% -> red
        cy.get('[data-testid="manual-grading-row"]').should('contain.text', '10.000 / 100.000');
        cy.get('[data-testid="manual-grading-row"] .badge').should('have.class', 'red-background');
        // peerGradeable label
        cy.get('[data-testid="manual-grading-row"]').should('contain.text', 'Non Peer Manual Grading Total');
    });

    it('renders peer rows, 0/0 no-badge, combinedPeerScore in total, Manual label', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                peerGradeable: false,
                autoGradingEarned: 0,
                autoGradingTotal: 0,
                taGradingEarned: 50,
                taGradingTotal: 100,
                peerGradeEarned: 30,
                peerTotal: 30,
                combinedPeerScore: 30,
            },
        });
        // 0/0 -> no badge class
        cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'red-background');
        cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'yellow-background');
        cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'green-background');
        // 50% -> yellow, label
        cy.get('[data-testid="manual-grading-row"] .badge').should('have.class', 'yellow-background');
        cy.get('[data-testid="manual-grading-row"]').should('contain.text', 'Manual Grading Total');
        // peer rows
        cy.get('[data-testid="individual-peer-row"]').should('contain.text', '30.000 / 30.000');
        cy.get('[data-testid="individual-peer-row"] .badge').should('have.class', 'green-background');
        cy.get('[data-testid="combined-peer-row"]').should('contain.text', '30.000 / 30.000');
        // total includes combinedPeerScore
        cy.get('[data-testid="total-row"]').should('contain.text', '80.000 / 130.000');
    });

    it('handles missing auto earned with minus and hides total when nothing active', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                autoGradingEarned: undefined,
                autoGradingTotal: 100,
            },
        });
        cy.get('[data-testid="autograding-row"]').should('contain.text', '\u2212');
        cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'red-background');
        cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'yellow-background');
        cy.get('[data-testid="autograding-row"] .badge').should('not.have.class', 'green-background');
        cy.get('[data-testid="total-row"]').should('not.exist');
    });

    it('shows only peer rows when auto and ta are absent', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                peerGradeEarned: 8,
                peerTotal: 16,
                combinedPeerScore: 8,
            },
        });
        // showAutoGrading = false, showTaGrading = false
        cy.get('[data-testid="autograding-row"]').should('not.exist');
        cy.get('[data-testid="manual-grading-row"]').should('not.exist');
        cy.get('[data-testid="individual-peer-row"]').should('contain.text', '8.000 / 16.000');
        cy.get('[data-testid="individual-peer-row"] .badge').should('have.class', 'yellow-background');
        cy.get('[data-testid="total-row"]').should('contain.text', '8.000 / 16.000');
    });

    it('shows only my-peer-row for userGroup 4 with minus for missing earned', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                userGroup: 4,
                peerTotal: 20,
            },
        });
        cy.get('[data-testid="my-peer-row"]').should('contain.text', '\u2212');
        cy.get('[data-testid="autograding-row"]').should('not.exist');
        cy.get('[data-testid="manual-grading-row"]').should('not.exist');
    });

    it('shows only my-peer-row for peerOnlyGrader with yellow badge', () => {
        cy.mount(TotalScoreBox, {
            props: {
                ...baseProps,
                peerOnlyGrader: true,
                peerGradeEarned: 18,
                peerTotal: 20,
            },
        });
        cy.get('[data-testid="my-peer-row"]').should('contain.text', '18.000 / 20.000');
        cy.get('[data-testid="my-peer-row"] .badge').should('have.class', 'yellow-background');
        cy.get('[data-testid="autograding-row"]').should('not.exist');
    });
});
