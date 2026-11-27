describe('Notebook Builder: Short Answer cell', () => {
    const gradeable_id = 'NotebookShortAnswerTest';

    beforeEach(() => {
        cy.login('instructor');

        // Create a disposable Electronic File gradeable so this test doesn't depend on any
        // particular sample gradeable already having an uploaded (vs. provided-example)
        // autograding config -- that assumption broke this test on a clean setup, since
        // Notebook Builder's "edit" mode requires an uploaded config to already exist.
        cy.visit(['sample', 'gradeable']);
        cy.get('[data-testid=create-gradeable-title]').type(gradeable_id);
        cy.get('[data-testid=create-gradeable-id]').type(gradeable_id);
        cy.get('[data-testid=radio-student-upload]').check({ force: true });
        cy.get('[data-testid=create-gradeable-btn]').click();

        // "new" mode always creates a fresh uploaded config and redirects to "edit", regardless
        // of whatever autograding config the gradeable previously had.
        cy.visit(['sample', 'notebook_builder', gradeable_id, 'new']);
    });

    afterEach(() => {
        cy.visit(['sample']);
        cy.get(`[data-testid="${gradeable_id}"] [title="Delete Gradeable"]`).click();
        cy.get('[data-testid="confirm-delete-gradeable"]').click();
    });

    it('renders a Short Answer cell when added to a notebook', () => {
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

        // Regression test for the widgets_array corruption found in review: a cell that fails to
        // render should never be left in a state that breaks Save for the whole notebook.
        cy.get('[data-testid="notebook-save"]').click();
        cy.contains('has been successfully saved').should('be.visible');
    });
});
