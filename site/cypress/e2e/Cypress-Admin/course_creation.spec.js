describe('Test cases revolving around course creation through UI', () => {
    beforeEach(() => {
        cy.login();
    });

    it('Should see all default courses on home page', () => {
        // list of all default courses that should be on home page for instructor 
        const default_courses = ['blank', 'development', 'sample', 'tutorial', 'testing', 'archived'];
        for (const default_course of default_courses) {
            cy.get(`[data-testid=${default_course}-button]`).should('exist');
        }
    });
});
