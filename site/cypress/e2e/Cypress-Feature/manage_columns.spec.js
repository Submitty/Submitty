// Cypress tests for the Manage Students / Manage Graders column toggles:
//   - User ID and Edit Student/Grader are always visible (and not in the toggle popup)
//   - Registration Subsection is a new column, hidden by default, toggleable
//
// Run locally FIRST (from the site/ directory):
//   npx cypress run --spec "cypress/e2e/<folder>/manage_columns.spec.js"
//   npx cypress open        (then E2E Testing -> pick this spec)

// NOTE: the table cell uses 'registration-subsection-td' on purpose. The Edit/New User form that is
// included on these same pages already owns data-testid="registration-subsection" (its input).
const saveStudentColumns = () => {
    cy.get('[data-testid="popup-save-button"]').click();
};

// Cypress >= 15.20.0 regression (cypress-io/cypress#35338): the popup writes the column cookie
// with document.cookie and reloads, but Cypress's server-side cookie jar re-sends the OLD value
// on that navigation, so the server renders the previous columns. Re-apply the browser's value
// through Cypress (which keeps the jar in sync as of 16.2.0), then reload once.
const reloadWithSavedColumns = (cookie) => {
    cy.getCookie(cookie).then(({ value, path }) => {
        cy.setCookie(cookie, value, { path });
    });
    cy.reload();
};

describe('Manage Students: column toggles', () => {
    // Cypress clears cookies before every test, so each test starts from the default columns.
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'users']);
    });

    it('always shows User ID and Edit Student, and hides Registration Subsection by default', () => {
        cy.get('[data-testid="student-id-td"]').should('exist');
        cy.get('[data-testid="edit-student-td"]').should('exist');
        cy.get('[data-testid="registration-subsection-td"]').should('not.exist');
    });

    it('does not offer User ID or Edit Student in the toggle popup', () => {
        cy.get('[data-testid="toggle-columns"]').click();
        // Positive assertion first: it proves the popup is open before we check for absences.
        cy.get('[data-testid="toggle-registration-subsection"]').should('be.visible').and('not.be.checked');
        cy.get('[data-testid="toggle-user-id"]').should('not.exist');
        cy.get('[data-testid="toggle-edit-student"]').should('not.exist');
    });

    it('shows the Registration Subsection column after it is toggled on', () => {
        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-registration-subsection"]').check();
        saveStudentColumns();
        reloadWithSavedColumns('active_student_columns');
        cy.get('[data-testid="registration-subsection-td"]').should('exist');
    });

    it('keeps User ID and Edit Student when every other column is turned off', () => {
        cy.get('[data-testid="toggle-columns"]').click();
        cy.contains('All Off').click();
        saveStudentColumns();
        reloadWithSavedColumns('active_student_columns');
        // Assert on the changed state first: this waits for the reload to finish...
        cy.get('[data-testid="student-given-name"]').should('not.exist');
        // ...so these two are checked against the reloaded page.
        cy.get('[data-testid="student-id-td"]').should('exist');
        cy.get('[data-testid="edit-student-td"]').should('exist');
    });
});

describe('Manage Graders: column toggles', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'graders']);
    });

    it('always shows User ID and Edit Grader, and hides Registration Subsection by default', () => {
        cy.get('[data-testid="grader-id-td"]').should('exist');
        cy.get('[data-testid="edit-grader-td"]').should('exist');
        cy.get('[data-testid="registration-subsection-td"]').should('not.exist');
    });

    it('does not offer User ID or Edit Grader in the toggle popup', () => {
        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-registration-subsection"]').should('be.visible').and('not.be.checked');
        cy.get('[data-testid="toggle-user-id"]').should('not.exist');
        cy.get('[data-testid="toggle-edit-grader"]').should('not.exist');
    });

    it('shows the Registration Subsection column after it is toggled on, and remembers it after a reload', () => {
        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-registration-subsection"]').check();
        cy.get('[data-testid="toggle-columns-submit"]').click();
        reloadWithSavedColumns('active_grader_columns');
        cy.get('[data-testid="registration-subsection-td"]').should('exist');

        // This is the check that would have caught the new Array(7) bug in manage-graders-columns.js.
        cy.reload();
        cy.get('[data-testid="registration-subsection-td"]').should('exist');
    });

    it('keeps User ID and Edit Grader when every other column is turned off', () => {
        cy.get('[data-testid="toggle-columns"]').click();
        cy.contains('All Off').click();
        cy.get('[data-testid="toggle-columns-submit"]').click();
        reloadWithSavedColumns('active_grader_columns');
        cy.get('[data-testid="grader-given-name"]').should('not.exist');
        cy.get('[data-testid="grader-id-td"]').should('exist');
        cy.get('[data-testid="edit-grader-td"]').should('exist');
    });
});
