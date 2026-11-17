const IndicatorType = {
    NotVisible: 1,
    Saving: 2,
    Saved: 3,
    Failed: 4,
};
const FieldType = {
    Text: 1,
    Select: 2,
    Checkbox: 3,
};

function verifyIndicator(fieldId, indicatorType) {
    cy.get(`[data-testid="${fieldId}"]`).invoke('attr', 'data-usesaveindicator').then((indicatorId) => {
        const indicatorSelector = `[data-testid="${indicatorId}-save-indicator"]`;
        const indicatorTextSelector = `[data-testid="${indicatorId}-save-text"]`;
        switch (indicatorType) {
            case IndicatorType.NotVisible:
                cy.get(indicatorSelector).should('not.be.visible');
                break;
            case IndicatorType.Saved:
                cy.get(indicatorSelector).should('be.visible');
                cy.get(indicatorTextSelector).should('have.text', 'Saved!');
                break;
            case IndicatorType.Failed:
                cy.get(indicatorSelector).should('be.visible');
                cy.get(indicatorTextSelector).should('have.text', 'Failed to save.');
                break;
        }
    });
}

function changeField(fieldTestId, fieldType, input) {
    const selector = `[data-testid="${fieldTestId}"]`;

    if (fieldType === FieldType.Text) {
        if (input === '' || input === undefined) {
            cy.get(selector).clear();
            cy.get(selector).blur({ force: true });
        }
        else {
            cy.get(selector).clear();
            cy.get(selector).type(input, { parseSpecialCharSequences: false });
            cy.get(selector).blur({ force: true });
        }
    }
    else if (fieldType === FieldType.Checkbox) {
        if (input === true) {
            cy.get(selector).uncheck({ force: true });
            cy.get(selector).check({ force: true });
            cy.get(selector).blur({ force: true });
        }
        else if (!input) {
            cy.get(selector).check({ force: true });
            cy.get(selector).uncheck({ force: true });
            cy.get(selector).blur({ force: true });
        }
    }
    else if (fieldType === FieldType.Select) {
        cy.get(selector).select(input);
        cy.get(selector).blur({ force: true });
    }
}

describe('test editing each setting on course settings page', () => {
    const allFields = {
        [FieldType.Text]: [
            'course-name', 'course-home-url', 'create-thread-message',
            'queue-message', 'upload-message', 'private-repository',
            'vcs-base-url', 'seek-message-instructions', 'default-student-late-days',
            'default-hw-late-days', 'course-email', 'grade-inquiry-message',
        ],
        [FieldType.Select]: [
            'default-section-id',
        ],
        [FieldType.Checkbox]: [
            'forum-enabled', 'queue-enabled', 'polls-enabled',
            'chat-enabled', 'seek-message-enabled', 'submission-clustering-enabled',
            'display-rainbow-grades-summary', 'auto-rainbow-grades',
            'display-custom-message', 'all-self-registration', 'seating-only-for-instructor',
        ],
    };
    const defaultFieldValues = {};

    function verifyPageIsReady() {
        for (const [fieldType, arrOfFieldIds] of Object.entries(allFields)) {
            arrOfFieldIds.forEach((fieldId) => {
                cy.get(`[data-testid="${fieldId}"]`).should('have.attr', 'data-usesaveindicator');
            });
        }
    }

    before(() => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);

        verifyPageIsReady();

        // Save default values
        for (const [fieldType, arrOfFieldIds] of Object.entries(allFields)) {
            arrOfFieldIds.forEach((fieldId) => {
                cy.get(`[data-testid="${fieldId}"]`).then(($field) => {
                    const fieldTypeN = Number(fieldType);
                    let originalVal = $field.val();
                    if (fieldTypeN === FieldType.Checkbox) {
                        originalVal = $field[0].checked;
                    }
                    defaultFieldValues[fieldId] = {
                        FieldType: fieldTypeN,
                        OriginalValue: originalVal,
                    };
                });
            });
        }

        cy.logout();
    });
    after(() => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);

        // Restore default values after testing
        for (const [fieldId, field] of Object.entries(defaultFieldValues)) {
            cy.get(`[data-testid="${fieldId}"]`).then(($field) => {
                const fieldTypeN = field.FieldType;
                let currentVal = $field.val();
                let originalVal = field.OriginalValue;
                if (fieldTypeN === FieldType.Select) {
                    const optionExists = $field.find(`option[value="${field.OriginalValue}"`).length > 0;
                    if (!optionExists) {
                        originalVal = 0;
                    }
                }
                else if (fieldTypeN === FieldType.Checkbox) {
                    currentVal = $field[0].checked;
                }
                if (currentVal !== field.OriginalValue) {
                    changeField(fieldId, field.FieldType, originalVal);
                    verifyIndicator(fieldId, IndicatorType.Saved);
                }
            });
        }

        cy.logout();
    });

    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);

        verifyPageIsReady();
    });
    afterEach(() => {
        cy.logout();
    });

    /*
        TODO:
        Replace this test with tests that actually verify
        that fields saved. For example, editing course name,
        checking places where course name appears,
        and making sure the value is updated after it says it is.
    */
    it('generic verify successful field saves', () => {
        const testFields = {
            [FieldType.Text]: [
                // fieldId, testInput
                ['course-name', 'Testing'],
                ['course-home-url', 'https://www.google.com/'],
                ['create-thread-message', 'Testing'],
                ['queue-message', 'Testing'],
                ['upload-message', 'Testing'],
                ['private-repository', 'Testing'],
                ['vcs-base-url', 'https://www.google.com/'],
                ['seek-message-instructions', 'Testing'],
                ['default-student-late-days', '10'],
                ['default-hw-late-days', '10'],
                ['course-email', 'Testing'],
                ['grade-inquiry-message', 'Testing'],
            ],
            [FieldType.Select]: [
                ['default-section-id', '2'],
            ],
            [FieldType.Checkbox]: [
                ['forum-enabled', true],
                ['queue-enabled', true],
                ['polls-enabled', true],
                ['chat-enabled', true],
                ['seek-message-enabled', true],
                ['submission-clustering-enabled', true],
                ['display-rainbow-grades-summary', true],
                ['auto-rainbow-grades', true],
                ['display-custom-message', true],
                ['all-self-registration', true],
                ['seating-only-for-instructor', true],
            ],
        };

        // Set each field's value to the above defined test value
        for (const [fieldType, arrOfFields] of Object.entries(testFields)) {
            arrOfFields.forEach((fieldArr) => {
                const fieldId = fieldArr[0];
                const input = fieldArr[1];
                changeField(fieldId, Number(fieldType), input);
                verifyIndicator(fieldId, IndicatorType.Saved);
            });
        }

        cy.reload();
        verifyPageIsReady();

        // Verify field actually saved by seeing if the field is the same
        for (const [fieldType, arrOfFields] of Object.entries(testFields)) {
            arrOfFields.forEach((fieldArr) => {
                const fieldId = fieldArr[0];
                const input = fieldArr[1];
                const fieldTypeN = Number(fieldType);
                const selector = `[data-testid="${fieldId}"]`;
                if (fieldTypeN === FieldType.Text) {
                    cy.get(selector).should('have.value', input);
                }
                else if (fieldTypeN === FieldType.Checkbox) {
                    cy.get(selector).should(input ? 'be.checked' : 'not.be.checked');
                }
                else if (fieldTypeN === FieldType.Select) {
                    cy.get(selector).should('have.value', input);
                }
            });
        }
    });

    it('test failed save on text fields', () => {
        const textTests = [
            ['course-home-url', 'Bad'],
            ['default-student-late-days', 'Bad'],
            ['default-hw-late-days', 'Bad'],
        ];
        textTests.forEach((val) => {
            const fieldId = val[0];
            const input = val[1];
            cy.get(`[data-testid="${fieldId}"]`).then(($field) => {
                val.push($field.val());
                changeField(fieldId, FieldType.Text, input);
                verifyIndicator(fieldId, IndicatorType.Failed);
            });
        });
    });
});
