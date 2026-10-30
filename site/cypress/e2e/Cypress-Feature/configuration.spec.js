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

function verifyIndicator(indicatorId, indicatorType) {
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

function changeField(fieldTestId, fieldType, inputs) {
    const selector = `[data-testid="${fieldTestId}"]`;

    if (fieldType === FieldType.Text || fieldType === FieldType.MarkupText) {
        cy.get(selector).clear();
        cy.get(selector).blur();
        cy.get(selector).type(inputs[0]);
        cy.get(selector).blur();
    }
    else if (fieldType === FieldType.Checkbox) {
        if(inputs[0] == true){
            cy.get(selector).uncheck();
            cy.get(selector).check();
        }else if(!inputs[0]){
            cy.get(selector).check();
            cy.get(selector).uncheck();
        }
    }
    else if (fieldType === FieldType.Select) {
        cy.get(selector).select(inputs[0]);
        cy.get(selector).select(inputs[1]);
    }
}

/*
TODO:
Should replace all usages of this function with proper tests
(For example, when testing the Course Name, find a place where the
course name actually appears and check for the updated value)
*/
function genericVerifyFieldSaved(fieldTestId, fieldType, shoulds){
    const selector = `[data-testid="${fieldTestId}"]`;
    cy.reload();
    if (fieldType === FieldType.Checkbox) {
        cy.get(selector).should(shoulds[0]);
    }
    else {
        cy.get(selector).should(shoulds[0], shoulds[1]);
    }
}


describe('test editing each setting on course settings page', () => {
    beforeEach(() => {
        cy.login('instructor');
        cy.visit(['sample', 'config']);
    });
    afterEach(() => {
        cy.logout();
    });


    it('test successful save - course name', () => {
        const id = 'course-name';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });
    it('test successful save - course home url', () => {
        const id = 'course-home-url';
        changeField(id, FieldType.Text, ['https://www.google.com/'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'https://www.google.com/']);
    });
    it('test failed save - course home url', () => {
        const id = 'course-home-url';
        changeField(id, FieldType.Text, ['Bad input'], id);
        verifyIndicator(id, IndicatorType.Failed);
    });
    it('test successful save - discussion forum', () => {
        const id = 'forum-enabled';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - create thread message', () => {
        const id = 'create-thread-message';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });
    it('test successful save - enable office hours queue', () => {
        const id = 'queue-enabled';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });  
    it('test successful save - oh queue welcome message', () => {
        const id = 'queue-message';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });
    it('test successful save - online polls', () => {
        const id = 'polls-enabled';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - toggle live chat', () => {
        const id = 'chat-enabled';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - upload message', () => {
        const id = 'upload-message';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });
    it('test successful save - autograding config directory', () => {
        const id = 'private-repository';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });
    /*
    At the time of writing the VCS type field only has 1 option of git
    so it's impossible to change/test.
    */
    it('test successful save - version control system url', () => {
        //NOTE that at the time of writing this field does not prevent the user
        //inputting invalid links. If such validation is ever added this test may fail.
        const id = 'vcs-base-url';
        changeField(id, FieldType.Text, ['https://www.google.com/'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'https://www.google.com/']);
    });
    it('test successful save - toggle seeking team message', () => {
        const id = 'seek-message-enabled';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - seeking team instructions', () => {
        const id = 'seek-message-instructions';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });  
    it('test successful save - seeking team instructions', () => {
        const id = 'seek-message-instructions';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });  
    it('test successful save - initial late days', () => {
        const id = 'default-student-late-days';
        changeField(id, FieldType.Text, ['10'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', '10']);
    });  
    it('test failed save - initial late days', () => {
        const id = 'default-student-late-days';
        changeField(id, FieldType.Text, ['Bad input'], id);
        verifyIndicator(id, IndicatorType.Failed);
    });  
    it('test successful save - default max late days', () => {
        const id = 'default-hw-late-days';
        changeField(id, FieldType.Text, ['10'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', '10']);
    });  
    it('test failed save - default max late days', () => {
        const id = 'default-hw-late-days';
        changeField(id, FieldType.Text, ['Bad input'], id);
        verifyIndicator(id, IndicatorType.Failed);
    });  
    it('test successful save - grades returned message', () => {
        const id = 'course-email';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });  
    it('test successful save - grades inquiry warning message', () => {
        const id = 'grade-inquiry-message';
        changeField(id, FieldType.Text, ['Testing'], id);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Text, ['have.value', 'Testing']);
    });  
    it('test successful save - toggle submission clustering', () => {
        const id = 'submission-clustering-enabled';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - toggle display rainbow grades summary', () => {
        const id = 'display-rainbow-grades-summary';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - toggle automatic rainbow grades updating', () => {
        const id = 'auto-rainbow-grades';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    /*
    By default there are no options to select from for the
    Display Room Seating Guide gradable select field.
    */
    it('test successful save - toggle room seating guide visible only to instructors', () => {
        const id = 'seating-only-for-instructor';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator('room-seating-gradeable-id', IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - display custom message', () => {
        const id = 'display-custom-message';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - toggle self registration', () => {
        const id = 'all-self-registration';
        changeField(id, FieldType.Checkbox, [true]);
        verifyIndicator(id, IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Checkbox, ['be.checked'])
    });
    it('test successful save - self registration default section', () => {
        const id = 'default-section-id';
        changeField(id, FieldType.Select, ['1', '2']);
        verifyIndicator('all-self-registration', IndicatorType.Saved);
        genericVerifyFieldSaved(id, FieldType.Select, ['have.value', '2'])
    });
});
