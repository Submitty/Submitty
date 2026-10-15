const IndicatorType = {
    NotVisible: 1,
    Saving: 2,
    Saved: 3,
    Failed: 4
}

function testIndicator(indicatorId, indicatorType) {
    cy.get(`[data-testid="${indicatorId}-save-indicator"]`).as('Indicator');
    cy.get(`[data-testid="${indicatorId}-save-text"]`).as('SaveText');
    switch(indicatorType){
        case IndicatorType.NotVisible:
            cy.get('@Indicator').should('not.be.visible');
        case IndicatorType.Saved:
            cy.get('@Indicator').should('be.visible');
            cy.get('@SaveText').should('have.text', 'Saved!');
        case IndicatorType.Failed:
            cy.get('@Indicator').should('be.visible');
            cy.get('@SaveText').should('have.text', 'Failed to save.');
    }
}

describe('test field saved indicators on course settings page', () => {
    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);
    });

    it('test successful save (text box)', () => {
        testIndicator('course-name', IndicatorType.NotVisible);
        cy.get('[data-testid="course-name"]').type('Testing Course{enter}');
        testIndicator('course-name', IndicatorType.Saved);
    });

    it('test failed save (text box)', () => {
        testIndicator('course-home-url', IndicatorType.NotVisible);
        cy.get('[data-testid="course-home-url"]').type('fail');
        testIndicator('course-home-url', IndicatorType.Failed);
    });

    it('test successful save (check box)', () => {
        testIndicator('forum-enabled', IndicatorType.NotVisible);
        cy.get('[data-testid="forum-enabled"]').click();
        testIndicator('forum-enabled', IndicatorType.Saved);
    });

    it('test successful save (markup text box)', () => {
        testIndicator('queue-message', IndicatorType.NotVisible);
        cy.get('[data-testid="queue-message"]').type('Testing{enter}');
        testIndicator('queue-message', IndicatorType.Saved);
    });

    it('test successful save (select)', () => {
        testIndicator('all-self-registration', IndicatorType.NotVisible);
        cy.get('[data-testid="default-section-id"]').select('5');
        testIndicator('all-self-registration', IndicatorType.Saved);
    });

    after(() => {
        cy.logout();
    });
});
