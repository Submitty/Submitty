describe('Test cases revolving around session management page', () => {
    beforeEach(() => {
        cy.visit('/manage_sessions');
        cy.login();
    });

    const softLogout = () => {
        cy.clearCookies();
        cy.reload();
        cy.login();
    };

    it('Should logout of a non-current session and redirect to login page if current session is terminated', () => {
        softLogout();
        cy.get('table[id=sessions-table] tbody tr').should('have.length.greaterThan', 1);
        cy.get('tr[id="current-session-row"]').find('input[value="Logout"]').click();
        cy.url().should('include', 'authentication/login');
        cy.login();
        cy.visit('/manage_sessions');
        cy.get('table[id=sessions-table] tbody tr').its('length').then((numSessions) => {
            cy.get('tr[id="current-session-row"]').siblings().eq(0).find('input[value="Logout"]').click();
            cy.get('table[id=sessions-table] tbody tr').should('have.length', numSessions - 1);
        });
    });

    it('Should terminate all sessions except current', () => {
        softLogout();
        cy.get('button[id=terminate-all-button]').click();
        cy.get('table[id=sessions-table] tbody tr').should('have.length', 1).and('have.id', 'current-session-row');
    });

    it('Should enforce single session', () => {
        softLogout();
        cy.get('table[id=sessions-table] tbody tr').should('have.length.greaterThan', 1);
        cy.get('input[name=single_session]').check();
        cy.get('table[id=sessions-table] tbody tr').should('have.length', 1);
        softLogout();
        cy.get('table[id=sessions-table] tbody tr').should('have.length', 1);
        cy.get('input[name=single_session]').uncheck();
    });
});
