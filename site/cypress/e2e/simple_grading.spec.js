
describe('Test cases revolving around simple grading lab', () => {
    ['ta', 'instructor'].forEach((user) => {
        beforeEach(() => {
            cy.visit('/');
        });

        it(`${user} should have grader submission options`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_lab','grading']);
            cy.get('#cell-1-0-0').invoke('attr','data-score').then((initialValue) => {
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
            cy.get('#settings-btn').click({force: true});
            cy.get('#settings-popup').should('have.attr','style','');
            cy.get('#settings-popup').find('.popup-box').find('div.popup-window.ui-draggable.ui-draggable-handle').find('.form-title').find('button.btn.btn-default.close-button.key_to_click').click({multiple: true});
            cy.get('#settings-popup').should('have.attr','style','display: none;');

            // Check Statistics Tab
            cy.get('#simple-stats-btn').click({force: true});
            cy.get('#simple-stats-popup').should('have.attr','style','display: block;');
            cy.get('#simple-stats-popup').find('.popup-box').find('div.popup-window.ui-draggable.ui-draggable-handle').find('.form-title').find('button.btn.btn-default.close-button.key_to_click').click({multiple: true});
            cy.get('#settings-popup').should('have.attr','style','display: none;');

            //Undo Button
            cy.get('#cell-1-0-0').invoke('attr','data-score').then((initialValue) => {
                cy.get('#cell-1-0-0').click();
                if (initialValue === '0') {
                    cy.get('#checkpoint-undo').click();
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '0');
                }
                else if (initialValue === '0.5') {
                    cy.get('#checkpoint-undo').click();
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '0.5');
                }
                else if (initialValue === '1') {
                    cy.get('#checkpoint-undo').click();
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '1');
                }
            });

            //Redo Button
            cy.get('#cell-1-0-0').invoke('attr','data-score').then((initialValue) => {
                if (initialValue === '0') {
                    cy.get('#checkpoint-redo').click();
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '0');
                }
                else if (initialValue === '0.5') {
                    cy.get('#checkpoint-redo').click();
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '0.5');
                }
                else if (initialValue === '1') {
                    cy.get('#checkpoint-redo').click();
                    cy.get('#cell-1-0-0').should('have.attr', 'data-score', '1');
                }
            });
        });

    });
});


describe('Test cases revolving around simple grading test', () => {
    ['ta', 'instructor'].forEach((user) => {
        beforeEach(() => {
            cy.visit('/');
        });

        it(`${user} should have grader submission options`, () => {
            cy.login(user);
            cy.visit(['sample', 'gradeable', 'grading_test','grading']);
            cy.get('#cell-1-0-0').invoke('attr','data-origval').then((initialValue) => {
                cy.get('#cell-1-0-1').invoke('attr','data-origval').then((cell2) => {
                    cy.get('#total-1-0').invoke('text').then((text) => {
                        const expectedValue = parseInt(initialValue) + parseInt(cell2);
                        expect(parseInt(text)).to.equal(expectedValue);
                    });
                });
            });
            // Check Settings Tab
            cy.get('#settings-btn').click({force: true});
            cy.get('#settings-popup').should('have.attr','style','');
            cy.get('#settings-popup').find('.popup-box').find('div.popup-window.ui-draggable.ui-draggable-handle').find('.form-title').find('button.btn.btn-default.close-button.key_to_click').click({multiple: true});
            cy.get('#settings-popup').should('have.attr','style','display: none;');

            // Check Statistics Tab
            cy.get('#simple-stats-btn').click({force: true});
            cy.get('#simple-stats-popup').should('have.attr','style','display: block;');
            cy.get('#simple-stats-popup').find('.popup-box').find('div.popup-window.ui-draggable.ui-draggable-handle').find('.form-title').find('button.btn.btn-default.close-button.key_to_click').click({multiple: true});
            cy.get('#settings-popup').should('have.attr','style','display: none;');
        });

    });
});
