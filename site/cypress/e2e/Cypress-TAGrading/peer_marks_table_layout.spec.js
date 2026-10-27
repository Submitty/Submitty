// The peer marks table keeps the mark text (Points + Title) pinned on the left and scrolls the
// peer graders sideways, so long marks and many peers stay readable in a narrow panel.
describe('Peer marks table layout', () => {
    const gradeableId = 'grading_pdf_peer_homework';
    const anonId = 'ED9OAlz1ndBS6Um'; // haleyd, has peer grades in the sample data

    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'gradeable', gradeableId, 'grading', `grade?who_id=${anonId}&sort=id&direction=ASC`]);
        cy.get('[data-testid="peer-info-btn"]').click();
        cy.get('[data-testid="peer-info"]').should('be.visible');
        // The peer table is rendered in the browser after the page loads
        cy.get('[data-testid="peer-table-scroll"]', { timeout: 10000 }).should('exist');
    });

    it('does not make the whole peer panel scroll sideways', () => {
        cy.get('[data-testid="peer-info"]').should(($panel) => {
            expect($panel[0].scrollWidth).to.be.at.most($panel[0].clientWidth);
        });
    });

    it('keeps the mark titles visible when the peer columns are scrolled', () => {
        // The sample data has a single peer, so there is nothing to scroll. Add more peer columns to
        // the rendered table to make it overflow sideways like it does with many peers.
        cy.get('[data-testid="peer-table-scroll"]').first().then(($scroll) => {
            $scroll.find('tr').each((_, row) => {
                const $last = Cypress.$(row).find('[data-testid="peer-grader-header"], [data-testid="peer-grader-mark"]').last();
                for (let i = 0; i < 30; i++) {
                    $last.after($last.clone());
                }
            });
        });
        cy.get('[data-testid="peer-table-scroll"]').first().should(($scroll) => {
            expect($scroll[0].scrollWidth).to.be.greaterThan($scroll[0].clientWidth);
        });

        cy.get('[data-testid="peer-table-scroll"]').first().scrollTo('right');
        cy.get('[data-testid="peer-table-scroll"]').first().within(() => {
            // The pinned mark title is still in view, and the last peer column has been scrolled into view
            cy.get('[data-testid="peer-mark-title"]').first().should('be.visible');
            cy.get('[data-testid="peer-grader-header"]').last().should('be.visible');
        });
    });
});
