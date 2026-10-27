/*
TODO
Actually test course settings saving when fields
in the Course Settings page are changed.
Right now, this spec only tests save indicators appearing on the page.
*/

const IndicatorType = {
    NotVisible: 1,
    Saving: 2,
    Saved: 3,
    Failed: 4,
};
const FieldType = {
    Text: 1,
    MarkupText: 2,
    Checkbox: 3,
    Select: 4,
};

function testIndicator(indicatorId, indicatorType) {
    cy.get(`[data-testid="${indicatorId}-save-indicator"]`).then(($indicator) => {
        cy.get(`[data-testid="${indicatorId}-save-text"]`).then(($savetext) => {
            switch (indicatorType) {
                case IndicatorType.NotVisible:
                    cy.wrap($indicator).should('not.be.visible');
                    break;
                case IndicatorType.Saved:
                    cy.wrap($indicator).should('be.visible');
                    cy.wrap($savetext).should('have.text', 'Saved!');
                    break;
                case IndicatorType.Failed:
                    cy.wrap($indicator).should('be.visible');
                    cy.wrap($savetext).should('have.text', 'Failed to save.');
                    break;
            }
        });
    });
}

function testField(fieldTestId, indicatorTestId, fieldType,
    inputs, shoulds, resultIndicator) {
    const selector = `[data-testid="${fieldTestId}"]`;
    testIndicator(indicatorTestId, IndicatorType.NotVisible);

    if (fieldType === FieldType.Text || fieldType === FieldType.MarkupText) {
        cy.get(selector).clear();
        cy.get(selector).blur();
        cy.get(selector).type(inputs[0]);
        cy.get(selector).blur();
    }
    else if (fieldType === FieldType.Checkbox) {
        cy.get(selector).uncheck();
        cy.get(selector).check();
    }
    else if (fieldType === FieldType.Select) {
        cy.get(selector).select(inputs[0]);
        cy.get(selector).select(inputs[1]);
    }

    testIndicator(indicatorTestId, resultIndicator);

    cy.reload();
    if (fieldType === FieldType.Checkbox) {
        cy.get(selector).should(shoulds[0]);
    }
    else {
        cy.get(selector).should(shoulds[0], shoulds[1]);
    }
}

describe('test field saved indicators on course settings page', () => {
    context('verifies indicators appear, and truthfully reports save state', () => {
        beforeEach(() => {
            cy.login('instructor');
            cy.visit(['sample', 'config']);
        });

        it('test successful save (text box)', () => {
            testField(
                'course-name', 'course-name', FieldType.Text,
                ['Testing'], ['have.value', 'Testing'], IndicatorType.Saved,
            );
        });

        it('test failed save (text box)', () => {
            testField(
                'course-home-url', 'course-home-url', FieldType.Text,
                ['Fail'], ['not.have.value', 'Fail'], IndicatorType.Failed,
            );
        });

        it('test successful save (check box)', () => {
            testField(
                'forum-enabled', 'forum-enabled', FieldType.Checkbox,
                [], ['be.checked'], IndicatorType.Saved,
            );
        });

        it('test successful save (markup text box)', () => {
            testField(
                'queue-message', 'queue-message', FieldType.MarkupText,
                ['Testing'], ['have.value', 'Testing'], IndicatorType.Saved,
            );
        });

        it('test successful save (select)', () => {
            testField(
                'default-section-id', 'all-self-registration', FieldType.Select,
                ['1', '2'], ['have.value', '2'], IndicatorType.Saved,
            );
        });

        afterEach(() => {
            cy.logout();
        });
    });
});
