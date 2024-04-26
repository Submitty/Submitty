const queueName = 'Cypress Office Hour Queue 1';
const queueName_random = 'Cypress Office Hour Queue Random';
const queueName1 = 'Cypress Office Hour Queue 2';
const queueCode = 'cypress_test';
const queueCode1 = 'cypress_test_fail';
const newQueueCode = 'cypress_update';
const enableQueue = () => {
    cy.visit(['sample', 'config']); // course setting
    cy.get('#queue-enabled').check();
    cy.get('#queue-enabled').should('be.checked');
};
const deleteQueue = () => {
    cy.visit(['sample', 'office_hours_queue']); // office hours queue
    cy.get('#toggle_filter_settings').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('.delete_queue_btn').last().click();
};
const disableQueue = () => {
    cy.visit(['sample', 'config']);
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
        cy.get('#old_queue_rand_token').click(); //random code
    }
    cy.get('#change_code_btn').click(); // update it
};
const switchUser = (account) => {
    cy.logout();
    cy.login(account);
    cy.visit(['sample', 'office_hours_queue']);
};
const studentJoinQueue = (queueName, queueCode) => {
    cy.get('#queue_code').select(queueName).invoke('val'); // in which queue you want to join
    cy.get('#queue_code').should('contain', queueName);
    cy.get('#token-box').type(queueCode);
    cy.get('#join_queue_btn').should('exist').click();
};
const editAnnouncement = (text='') => {
    // openAnnouncementSettings
    cy.get('#toggle_announcement_settings').click();
    cy.get('#announcement-settings').should('exist');
    cy.get('#queue-announcement-message').clear();
    if (text.length > 0) {
        cy.get('#queue-announcement-message').type(text);
    }
    // saveAnnouncementSettings
    cy.get('#save_announcement').click();
};
describe('test office hours queue', () => {
    it('opened new queue, students joining queue, started and finished helping student', () => {
        cy.login();
        enableQueue();
        openNewQueue(queueName, queueCode);
        cy.get('.alert-success').contains('New queue added');
        openNewQueue(queueName, queueCode1); // same name but used different code
        cy.get('.alert-error').contains('Unable to add queue. Make sure you have a unique queue name');
        openNewQueue(queueName_random);
        cy.get('.alert-success').contains('New queue added');
        changeQueueCode(queueName_random);
        cy.get('.alert-success').contains('Queue Access Code Changed');
        changeQueueCode(queueName, newQueueCode);
        cy.get('.alert-success').contains('Queue Access Code Changed');
        openNewQueue(queueName1, queueCode1);
        cy.get('.alert-success').contains('New queue added');
        // switch to student to join queue
        switchUser('student');
        studentJoinQueue(queueName, newQueueCode);
        cy.get('.alert-success').contains('Added to queue');
        cy.get('#leave_queue').click(); // studentRemoveSelfFromQueue
        cy.get('.alert-success').contains('Removed from queue');
        studentJoinQueue(queueName, newQueueCode);
        cy.get('.alert-success').contains('Added to queue');
        // switch to instructor to help first student
        switchUser('instructor');
        cy.get('.help_btn').first().click(); // helpFirstStudent
        cy.get('.alert-success').contains('Started helping student');
        // switch to student for finishing help
        switchUser('student');
        cy.get('#self_finish_help').click(); // studentFinishHelpSelf
        cy.get('.alert-success').contains('Finished helping student');
        studentJoinQueue(queueName1, queueCode1);
        cy.get('.alert-success').contains('Added to queue');
        // switch to student (aphacker) for joining queue
        switchUser('aphacker');
        studentJoinQueue(queueName1, queueCode1);
        cy.get('.alert-success').contains('Added to queue');
    });
    it('switch to instructor for helping, removing student and toggle to other queue', () => {
        switchUser('instructor');
        cy.get('.help_btn').first().click(); // help first student
        cy.get('.alert-success').contains('Started helping student');
        cy.get('.finish_helping_btn').first().click(); // finished helping first student
        cy.get('.alert-success').contains('Finished helping student');
        cy.get('.remove_from_queue_btn').first().click(); // remove First Student
        cy.get('.alert-success').contains('Removed from queue');
        cy.get('.queue_restore_btn').first().click(); // restore first Student
        cy.get('.alert-success').contains('Student restored');
        cy.get('.queue_restore_btn').first().click();
        cy.get('.alert-success').contains('Student restored');
        cy.get('.queue_restore_btn').first().click();
        cy.get('.alert-error').contains('Cannot restore a user that is currently in the queue. Please remove them first.');
        cy.get('.filter-buttons').first().click(); // turn first "off"
        cy.get('.filter-buttons').first().click(); // turn first "on"
        cy.get('#toggle_filter_settings').first().click();
        cy.get('.toggle-queue-checkbox').first().click(); // closeFirstQueue
        cy.get('.empty_queue_btn').first().click(); // emptyFirstQueue
        editAnnouncement('Submitty');
        cy.get('.alert-success').contains('Updated announcement');
        editAnnouncement('');
        cy.get('#announcement').should('not.exist');
        cy.get('.alert-success').contains('Updated announcement');
        // diable and delete all queue
        disableQueue();
        deleteQueue();
        deleteQueue();
        deleteQueue();
        cy.logout();
    });
});
