describe('Notebook Builder: Short Answer cell', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'notebook_builder', 'notebook_filesubmission', 'edit']);
    });

    it('renders a Short Answer cell when added to an existing notebook', () => {
        // Regression test for #13234: a typo in short-answer-widget.js (codemirror_langauges vs
        // codemirror_languages) caused an uncaught TypeError in render(), silently preventing the
        // Short Answer cell from appearing in the notebook builder's edit view.
        cy.get('[data-testid="short-answer"]').click();

        cy.get('.short-answer-widget').should('be.visible');
        cy.get('.short-answer-widget .filename-input').should('exist');
        cy.get('.short-answer-widget .answer-type').should('exist');

        // The language dropdown should be populated (Default plus at least one CodeMirror mode),
        // which only happens if builder_data.codemirror_languages was read correctly.
        cy.get('.short-answer-widget .answer-type option').should('have.length.greaterThan', 1);
    });
});
