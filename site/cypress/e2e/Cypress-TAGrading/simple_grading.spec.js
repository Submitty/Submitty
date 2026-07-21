import { verifyWebSocketStatus } from '../../support/utils';

function accurateSectionHeaders(sectionType, sectionLimit) {
    let sectionNumber = 1;
    cy.get('[data-testid="gradeable-sections"]').each(($element) => {
        // Specific number of sections then a group with no section,
        // so set sectionNumber to the string 'NULL'
        if (sectionNumber > sectionLimit) {
            sectionNumber = 'NULL';
        }
        cy.wrap($element).should('contain.text', sectionType.concat(' ', sectionNumber));
        sectionNumber++;
    });
}

describe('Test cases revolving around simple grading lab', () => {
    ['ta', 'instructor'].forEach((user) => {
        it(`${user} should have grader submission options`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_lab_rotating', 'grading']);
            accurateSectionHeaders('Students Assigned to Rotating Section', 5);
            cy.visit(['sample', 'gradeable', 'grading_lab', 'grading']);
            accurateSectionHeaders('Students Enrolled in Registration Section', 10);
            cy.get('#cell-1-0-0').invoke('attr', 'data-score').then((initialValue) => {
                verifyWebSocketStatus();
                cy.get('#cell-1-0-0').click();
                if (initialValue === '0') {
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '1');
                }
                else if (initialValue === '0.5') {
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '0');
                }
                else if (initialValue === '1') {
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '0.5');
                }
            });
            // Check Settings Tab
            cy.get('#settings-btn').click({ force: true });
            cy.get('#settings-popup').should('have.attr', 'style', '');
            cy.get('#settings-popup').find('[data-testid="close-button"]').click({ multiple: true });
            cy.get('#settings-popup').should('have.attr', 'style', 'display: none;');

            // Check Statistics Tab
            cy.get('#simple-stats-btn').click({ force: true });
            cy.get('#simple-stats-popup').should('have.attr', 'style', 'display: block;');
            cy.get('#simple-stats-popup').find('[data-testid="close-button"]').click({ multiple: true });
            cy.get('#settings-popup').should('have.attr', 'style', 'display: none;');
        });
    });
});

describe('Test cases revolving around simple grading test', () => {
    before(() => {
        cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
        cy.login('instructor');
        // reset values
        cy.get('#cell-1-0-0').clear();
        cy.get('#cell-1-0-0').type('2');
        cy.get('#cell-1-0-1').clear();
        cy.get('#cell-1-0-1').type('2.5');
        cy.get('#total-1-0').click();
        cy.logout();
    });

    ['ta', 'instructor'].forEach((user) => {
        beforeEach(() => {
            cy.visit(['sample', 'gradeable', 'grading_test', 'grading']);
        });

        it(`${user} should have grader submission options`, () => {
            cy.login(user);
            accurateSectionHeaders('Students Enrolled in Registration Section', 10);
            cy.get('#cell-1-0-0').invoke('attr', 'data-origval').then((initialValue) => {
                cy.get('#cell-1-0-1').invoke('attr', 'data-origval').then((cell2) => {
                    cy.get('#total-1-0').invoke('text').then((text) => {
                        const expectedValue = parseFloat(initialValue) + parseFloat(cell2);
                        expect(parseFloat(text)).to.equal(expectedValue);
                    });
                });
            });

            // Test different people can grade the same cell
            verifyWebSocketStatus();
            cy.get('#cell-1-0-0').clear();
            cy.get('#cell-1-0-0').type('3.4');
            cy.get('#cell-1-0-1').clear();
            cy.get('#cell-1-0-1').type('3.4');
            cy.get('#total-1-0').click();
            cy.get('#total-1-0').should('contain.text', '6.8');

            // Check Settings Tab
            cy.get('#settings-btn').click({ force: true });
            cy.get('#settings-popup').should('have.attr', 'style', '');
            cy.get('#settings-popup').find('[data-testid="close-button"]').click({ multiple: true });
            cy.get('#settings-popup').should('have.attr', 'style', 'display: none;');

            // Check Statistics Tab
            cy.get('#simple-stats-btn').click({ force: true });
            cy.get('#simple-stats-popup').should('have.attr', 'style', 'display: block;');
            cy.get('#simple-stats-popup').find('[data-testid="close-button"]').click({ multiple: true });
            cy.get('#settings-popup').should('have.attr', 'style', 'display: none;');
        });
    });
});
