const queueName = 'Cypress Office Hour Queue 1';
const queueName_random = 'Cypress Office Hour Queue Random';
const queueName1 = 'Cypress Office Hour Queue 2';
const queueCode = 'cypress_test';
const queueCode1 = 'cypress_test_fail';
const newQueueCode = 'cypress_update';
const enableQueue = () => {
    cy.visit(['sample']);
    cy.get('#nav-sidebar-course-settings').click();
    cy.get('#queue-enabled').check();
    cy.get('#queue-enabled').should('be.checked');
};
const deleteQueue = () => {
    cy.visit(['sample']);
    cy.get('#nav-sidebar-queue').click();
    cy.get('#toggle_filter_settings').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('.delete_queue_btn').last().click();
};
const disableQueue = () => {
    cy.logout();
    cy.login();
    cy.visit(['sample']);
    cy.get('#nav-sidebar-course-settings').click();
    cy.get('#queue-enabled').should('be.checked');
    cy.get('#queue-enabled').check();
};
const openNewQueue = (queueName, queueCode='') => {
    cy.get('#nav-sidebar-queue').click();
    cy.get('#toggle_new_queue').should('exist').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('#new_queue_code').type(queueName);
    if (queueCode.length > 0) {
        cy.get('#new-queue-token').type(queueCode);
    }
    else {
        cy.get('#new_queue_rand_token').click();
    }
    cy.get('#open_new_queue_btn').click();
};

const changeQueueCode = (queueName, queueCode='') => {
    cy.get('#toggle_filter_settings').should('exist').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('#old_queue_code').select(queueName);
    if (queueCode.length > 0) {
        cy.get('#old_queue_token').type(queueCode);
    }
    else {
        cy.get('#old_queue_rand_token').click(); //random
    }
    cy.get('#change_code_btn').click(); // update it
};
const expectedAlerts =  (success=0, error=0, success_text='', error_text='') => {
    if (success > 0) {
        cy.get('.alert-success').contains(success_text);
    }
    if (error > 0) {
        cy.get('.alert-error').contains(error_text);
    }
};
const switchUser = (account) => {
    cy.logout();
    cy.login(account);
    cy.visit(['sample']);
    cy.get('#nav-sidebar-queue').should('exist').click();
};
const studentJoinQueue = (queueName, queueCode, name) => {
    cy.get('#name_box').clear();
    cy.get('#name_box').type(name);
    cy.get('#queue_code').select(queueName).invoke('val'); // in which queue you want to join
    cy.get('#queue_code').should('contain', queueName);
    cy.get('#token-box').type(queueCode);
    cy.get('#join_queue_btn').should('exist').click();
};
const studentRemoveSelfFromQueue = () => {
    cy.get('#leave_queue').should('exist').click();
};
const studentFinishHelpSelf = () => {
    cy.get('#self_finish_help').should('exist').click();
};
const helpFirstStudent = () => {
    cy.get('.help_btn').first().should('exist').click();
};
const finishHelpFirstStudent = () => {
    cy.get('.finish_helping_btn').first().should('exist').click();
};
const removeFirstStudent = () => {
    cy.get('.remove_from_queue_btn').first().should('exist').click();
};
const restoreFirstStudent = () => {
    cy.get('.queue_restore_btn').first().should('exist').click();
};
const toggleFirstQueueFilter = () => {
    cy.get('.filter-buttons').first().should('exist').click();
};
const closeFirstQueue = () => {
    cy.get('.toggle-queue-checkbox').first().should('exist').click();
};
const emptyFirstQueue = () => {
    cy.get('.empty_queue_btn').first().should('exist').click();
    cy.wait(100);
};
const verifyElementMissing = (type, values) => {
    values.forEach((value) => {
        if (type === 'id') {
            cy.get(`#${value}`).should('not.exist');
        }
        else if (type === 'class') {
            cy.get(`.${value}`).should('not.exist');
        }
        else {
            console.log(`Invalid ${type}`);
        }
    });
};
const queueHistoryCount = (limited=true) => {
    cy.get('#view_history_button').click();
    const count = cy.get('.queue_history_row').its('length');
    cy.get('#view_history_button').click();
    return count;
};
const currentQueueCount = () => {
    const count = cy.get('shown_queue_row').its('length');
    return count;
};
const openAnnouncementSettings = () => {
    cy.get('#toggle_announcement_settings').click();
    cy.get('#announcement-settings').should('exist');
};
const saveAnnouncementSettings = () => {
    cy.get('#save_announcement').should('exist').click();
};
const editAnnouncement = (text='') => {
    openAnnouncementSettings();
    cy.get('#queue-announcement-message').clear();
    if (text.length > 0) {
        cy.get('#queue-announcement-message').type(text);
    }
    saveAnnouncementSettings();
};
describe('test office hours queue', () => {
    it('test_office_hours_queue', () => {
        cy.login();
        enableQueue();
        openNewQueue(queueName, queueCode);
        expectedAlerts(1, 0, 'New queue added', '');
        openNewQueue(queueName, queueCode1); // same name but used different code
        expectedAlerts(0, 1, '', 'Unable to add queue. Make sure you have a unique queue name');
        openNewQueue(queueName_random);
        expectedAlerts(1, 0, 'New queue added', '');
        changeQueueCode(queueName_random);
        expectedAlerts(1, 0, 'Queue Access Code Changed', '');
        changeQueueCode(queueName, newQueueCode);
        expectedAlerts(1, 0, 'Queue Access Code Changed', '');
        openNewQueue(queueName1, queueCode1);
        expectedAlerts(1, 0, 'New queue added', '');
    });
    it('switch to student to join queue', () => {
        switchUser('student');
        studentJoinQueue(queueName, newQueueCode, 'Joe');
        expectedAlerts(1, 0, 'Added to queue', '');
        studentRemoveSelfFromQueue();
        expectedAlerts(1, 0, 'Removed from queue', '');
        studentJoinQueue(queueName, newQueueCode, 'Joe');
        expectedAlerts(1, 0, 'Added to queue', '');
    });
    it('switch to instructor', () => {
        switchUser('instructor');
        helpFirstStudent();
        expectedAlerts(1, 0, 'Started helping student', '');

    });
    it('switch to student for finishing help', () => {
        switchUser('student');
        studentFinishHelpSelf();
        expectedAlerts(1, 0, 'Finished helping student', '');
        studentJoinQueue(queueName1, queueCode1, 'Joe');
        expectedAlerts(1, 0, 'Added to queue', '');
    });
    it('switch to student (aphacker) for joining queue', () => {
        switchUser('aphacker');
        studentJoinQueue(queueName1, queueCode1, 'Aphacker' );
        expectedAlerts(1, 0, 'Added to queue', '');
    });
    it('switch to instructor for helping, removing student and toggle to other queue', () => {
        switchUser('instructor');
        helpFirstStudent();
        expectedAlerts(1, 0, 'Started helping student', '');
        finishHelpFirstStudent();
        expectedAlerts(1, 0, 'Finished helping student', '');
        removeFirstStudent();
        expectedAlerts(1, 0, 'Removed from queue', '');
        restoreFirstStudent();
        expectedAlerts(1, 0, 'Student restored', '');
        restoreFirstStudent();
        expectedAlerts(1, 0, 'Student restored', '');
        restoreFirstStudent();
        expectedAlerts(0, 1, '', 'Cannot restore a user that is currently in the queue. Please remove them first.');
        toggleFirstQueueFilter(); // turn first "off"
        toggleFirstQueueFilter(); // turn first "on"
        cy.get('#toggle_filter_settings').first().click();
        closeFirstQueue();
        emptyFirstQueue();
        editAnnouncement('Submitty');
        expectedAlerts(1, 0, 'Updated announcement', '');
        editAnnouncement('');
        expectedAlerts(1, 0, 'Updated announcement', '');
        verifyElementMissing('id', ['announcement']);
    });
    it ('switch to student for verifyElementMissing', () => {
        switchUser('student');
        verifyElementMissing('class', ['help_btn', 'finish_helping_btn', 'remove_from_queue_btn', 'queue_restore_btn', 'close_queue_btn', 'empty_queue_btn']);
        verifyElementMissing('id', ['toggle_filter_settings', 'new_queue_code', 'new-queue-token', 'new_queue_rand_token', 'open_new_queue_btn']);
    });
    it('switch to instructor for disable and deleting queue', () => {
        disableQueue();
        // deleted all three queues
        deleteQueue();
        deleteQueue();
        deleteQueue();
        cy.logout();
    });
});
