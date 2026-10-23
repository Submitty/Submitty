describe('Peer panel layout', () => {
    const gradingUrl = ['sample', 'gradeable', 'grading_pdf_peer_homework', 'grading', 'grade?who_id=ED9OAlz1ndBS6Um&sort=id&direction=ASC'];

    beforeEach(() => {
        cy.login('instructor');
        cy.visit(gradingUrl);
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
    });

    it('Mark points and titles are pinned before the peer grader columns', () => {
        // timeout needed since peer components take longer to load than the page
        cy.get('#peer-component-84 [data-testid="peer-grading-scroll-wrapper"]', { timeout: 10000 })
            .should('have.css', 'overflow-x', 'auto')
            .within(() => {
                cy.get('thead th').eq(0).should('contain.text', 'Points').and('have.css', 'position', 'sticky');
                cy.get('thead th').eq(1).should('contain.text', 'Title').and('have.css', 'position', 'sticky');
                cy.get('[data-testid="peer-grader-header"]').first().prevAll('th').should('have.length', 2);
                cy.get('tbody tr').each(($row) => {
                    cy.wrap($row).children().eq(0).should('have.attr', 'data-testid', 'peer-mark-points');
                    cy.wrap($row).children().eq(1).should('have.attr', 'data-testid', 'peer-mark-title').and('have.attr', 'scope', 'row');
                });
                // details.css numbers the first cell of table rows, which must not show next to the points
                cy.get('[data-testid="peer-mark-points"]').first().then(($cell) => {
                    const cellWindow = $cell[0].ownerDocument.defaultView;
                    expect(cellWindow.getComputedStyle($cell[0], '::after').content).to.equal('none');
                });
            });
    });
});
