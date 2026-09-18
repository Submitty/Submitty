import ToggleColumns from '../../vue/src/components/ToggleColumns.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

describe('ToggleColumns', () => {
    it('toggles the matching checkbox when its visible label is clicked', () => {
        cy.mount(ToggleColumns, {
            props: {
                columns: ['first-name', 'last-name'],
                labels: ['Given Name', 'Family Name'],
                cookie: 'test_column_labels',
            },
        });
        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-first-name"]').should('be.checked');
        cy.get('[data-testid="toggle-last-name"]').should('be.checked');

        cy.contains('label', 'Given Name').click();
        cy.get('[data-testid="toggle-first-name"]').should('not.be.checked');
        cy.get('[data-testid="toggle-last-name"]').should('be.checked');

        cy.contains('label', 'Family Name').click();
        cy.get('[data-testid="toggle-last-name"]').should('not.be.checked');
        cy.get('[data-testid="toggle-first-name"]').should('not.be.checked');

        cy.contains('label', 'Given Name').click();
        cy.get('[data-testid="toggle-first-name"]').should('be.checked');
    });

    it('keeps forced columns disabled when their labels are clicked', () => {
        cy.mount(ToggleColumns, {
            props: {
                columns: ['user-id'],
                labels: ['User ID'],
                forced: ['user-id'],
                cookie: 'test_forced_column_labels',
            },
        });
        cy.get('[data-testid="toggle-columns"]').click();
        cy.get('[data-testid="toggle-user-id"]').should('be.checked').and('be.disabled');
        cy.contains('label', 'User ID').click();
        cy.get('[data-testid="toggle-user-id"]').should('be.checked').and('be.disabled');
    });

    ['bits', 'json'].forEach((format) => {
        it(`saves mandatory and optional selections in ${format} preferences`, () => {
            const cookie = `test_saved_columns_${format}`;
            mountWithEmitSpy(ToggleColumns, 'save', {
                columns: ['user-id', 'first-name', 'last-name'],
                labels: ['User ID', 'Given Name', 'Family Name'],
                forced: ['user-id'],
                cookie,
                format,
                buttonWrapped: true,
            });
            cy.get('[data-testid="toggle-columns"]').click();
            cy.contains('a', 'All Off').click();
            cy.contains('a', 'All On').click();
            cy.get('[data-testid="toggle-first-name"]').should('be.checked');
            cy.get('[data-testid="toggle-last-name"]').should('be.checked');
            cy.contains('label', 'Given Name').click();
            cy.get('[data-testid="popup-save-button"]').click();
            cy.get('@eventHandler').should('have.been.calledOnce');
            cy.getCookie(cookie).should((saved) => {
                expect(saved).not.to.be.null;
                const value = decodeURIComponent(saved.value);
                if (format === 'bits') {
                    expect(value).to.equal('1-0-1');
                }
                else {
                    expect(JSON.parse(value)).to.deep.equal({
                        'user-id': true,
                        'first-name': false,
                        'last-name': true,
                    });
                }
            });
        });

        it(`restores forced selections from saved ${format} preferences`, () => {
            const cookie = `test_forced_${format}`;
            const value = format === 'bits'
                ? '0-0-1'
                : JSON.stringify({
                        'user-id': false,
                        'first-name': false,
                        'last-name': true,
                    });
            cy.setCookie(cookie, value);
            cy.mount(ToggleColumns, {
                props: {
                    columns: ['user-id', 'first-name', 'last-name'],
                    labels: ['User ID', 'Given Name', 'Family Name'],
                    forced: ['user-id'],
                    cookie,
                    format,
                },
            });
            cy.get('[data-testid="toggle-columns"]').click();
            cy.get('[data-testid="toggle-user-id"]').should('be.checked').and('be.disabled');
            cy.get('[data-testid="toggle-first-name"]').should('not.be.checked');
            cy.get('[data-testid="toggle-last-name"]').should('be.checked');
            cy.contains('a', 'All Off').click();
            cy.get('[data-testid="toggle-user-id"]').should('be.checked');
            cy.get('[data-testid="toggle-last-name"]').should('not.be.checked');
            cy.get('[data-testid="popup-close-button"]').click();
            cy.get('[data-testid="toggle-columns"]').click();
            cy.get('[data-testid="toggle-user-id"]').should('be.checked').and('be.disabled');
            cy.get('[data-testid="toggle-last-name"]').should('be.checked');
        });
    });
});
