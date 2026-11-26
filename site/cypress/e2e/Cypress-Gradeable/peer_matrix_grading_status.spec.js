describe('Peer matrix grading progress', () => {
    const statuses = ['complete', 'started', 'none'];

    beforeEach(() => {
        cy.login('instructor');
    });

    it('should highlight each grader and student by grading progress once peer grading has started', () => {
        cy.visit(['sample', 'gradeable', 'grading_pdf_peer_homework', 'update?nav_tab=4']);
        cy.get('[data-testid="peer-status-legend"]').should('be.visible');

        cy.get('[data-testid="peer-table-student"]').each(($student) => {
            const status = $student.attr('data-grading-status');
            expect(status).to.be.oneOf(statuses);
            expect($student).to.have.class(`peer-status-${status}`);
        });

        // A grader is complete or not started only when all of their students are, otherwise partially graded
        cy.get('[data-testid="peer-table-grader"]').each(($grader) => {
            const studentStatuses = [...new Set($grader.closest('tr').find('[data-testid="peer-table-student"]')
                .map((i, el) => el.getAttribute('data-grading-status')).get())];
            const expected = studentStatuses.length === 1 && studentStatuses[0] !== 'started' ? studentStatuses[0] : 'started';
            expect($grader.attr('data-grading-status')).to.eq(expected);
            expect($grader).to.have.class(`peer-status-${expected}`);
        });
    });

    it('should not highlight before peer grading starts', () => {
        cy.visit(['sample', 'gradeable', 'future_peer_grading', 'update?nav_tab=4']);
        cy.get('[data-testid="peer-control"]').should('be.visible');
        cy.get('[data-testid="peer-status-legend"]').should('not.exist');
        cy.get('[data-grading-status]').should('not.exist');
    });
});
