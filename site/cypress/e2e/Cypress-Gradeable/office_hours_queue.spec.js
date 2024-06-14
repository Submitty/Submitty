const queueName = 'Cypress Office Hour Queue 1';
const queueName_random = 'Cypress Office Hour Queue Random';
const queueName1 = 'Cypress Office Hour Queue 2';
const queueCode = 'cypress_test';
const queueCode1 = 'cypress_test_fail';
const newQueueCode = 'cypress_update';
const enableQueue = () => {
    cy.visit(['sample', 'config']); // course setting
    cy.get('[data-testid="queue-enabled"]').check();
    cy.get('[data-testid="queue-enabled"]').should('be.checked');
};
const deleteQueue = () => {
    cy.visit(['sample', 'office_hours_queue']); // office hours queue
    cy.get('[data-testid="toggle-filter-settings"]').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('[data-testid="delete-queue-btn"]').last().click();
};
const disableQueue = () => {
    cy.visit(['sample', 'config']);
    cy.get('[data-testid="queue-enabled"]').should('be.checked');
    cy.get('[data-testid="queue-enabled"]').uncheck();
};
const openNewQueue = (queueName, queueCode='') => {
    cy.get('#nav-sidebar-queue').click();
    cy.get('[data-testid="toggle-new-queue"]').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('[data-testid="new-queue-code"]').type(queueName);
    if (queueCode.length > 0) {
        cy.get('[data-testid="new-queue-token"]').type(queueCode);
    }
    else {
        cy.get('[data-testid="new-queue-rand-token"]').click();
    }
    cy.get('[data-testid="open-new-queue-btn"]').click();
};

const changeQueueCode = (queueName, queueCode='') => {
    cy.get('[data-testid="toggle-filter-settings"]').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('[data-testid="old-queue-code"]').select(queueName);
    if (queueCode.length > 0) {
        cy.get('[data-testid="old-queue-token"]').type(queueCode);
    }
    else {
        cy.get('[data-testid="old-queue-rand-token"]').click(); // random code
    }
    cy.get('[data-testid="change-code-btn"]').click(); // update it
};
const switchUser = (account) => {
    cy.logout();
    cy.login(account);
    cy.visit(['sample', 'office_hours_queue']);
};
const studentJoinQueue = (queueName, queueCode) => {
    cy.get('[data-testid="queue-code"]').select(queueName).invoke('val'); // in which queue you want to join
    cy.get('[data-testid="queue-code"]').should('contain', queueName);
    cy.get('#token-box').type(queueCode);
    cy.get('[data-testid="join-queue-btn"]').click();
};
const editAnnouncement = (text='') => {
    // openAnnouncementSettings
    cy.get('[data-testid="toggle-announcement-settings"]').click();
    cy.get('#announcement-settings').should('exist');
    cy.get('#queue-announcement-message').clear();
    if (text.length > 0) {
        cy.get('#queue-announcement-message').type(text);
    }
    // saveAnnouncementSettings
    cy.get('[data-testid="save-announcement"]').click();
};
describe('test office hours queue', () => {
    it('Testing queue as student', () => {
        cy.login();
        enableQueue();
        // deleting the Lab help and homework debugging
        deleteQueue();
        deleteQueue();
        openNewQueue(queueName, queueCode);
        openNewQueue(queueName, queueCode1); // same name but used different code
        cy.get('[data-testid="popup-message"]').should('contain', 'Unable to add queue. Make sure you have a unique queue name');
        openNewQueue(queueName_random);
        changeQueueCode(queueName_random);
        cy.get('[data-testid="popup-message"]').should('contain', 'Queue Access Code Changed');
        changeQueueCode(queueName, newQueueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'Queue Access Code Changed');
        openNewQueue(queueName1, queueCode1);
        // switch to student to join queue
        switchUser('student');
        // cy.get('#leave_queue').click();
        studentJoinQueue(queueName, newQueueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
        cy.get('[data-testid="leave-queue"]').click(); // studentRemoveSelfFromQueue
        cy.get('[data-testid="popup-message"]').should('contain', 'Removed from queue');
        studentJoinQueue(queueName, newQueueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
        // switch to instructor to help first student
        switchUser('instructor');
        cy.get('.help_btn').first().click(); // helpFirstStudent
        cy.get('[data-testid="popup-message"]').should('contain', 'Started helping student');
        // switch to student for finishing help
        switchUser('student');
        cy.get('[data-testid="self-finish-help"]').click(); // studentFinishHelpSelf
        cy.get('[data-testid="popup-message"]').should('contain', 'Finished helping student');
        studentJoinQueue(queueName1, queueCode1);
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
        // switch to student (aphacker) for joining queue
        switchUser('aphacker');
        studentJoinQueue(queueName1, queueCode1);
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
    });
    it('Helping,removing student and toggle queue as Instructor', () => {
        switchUser('instructor');
        cy.get('.help_btn').first().click(); // help first student
        cy.get('[data-testid="popup-message"]').should('contain', 'Started helping student');
        cy.get('.finish_helping_btn').first().click(); // finished helping first student
        cy.get('[data-testid="popup-message"]').should('contain', 'Finished helping student');
        cy.get('[data-testid="remove-from-queue-btn"]').first().click();  // remove First Student
        cy.get('[data-testid="popup-message"]').should('contain', 'Removed from queue');
        cy.get('[data-testid="queue-restore-btn"]').first().click(); // restore first Student
        cy.get('[data-testid="popup-message"]').should('contain', 'Student restored');
        cy.get('[data-testid="queue-restore-btn"]').first().click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Student restored');
        cy.get('[data-testid="queue-restore-btn"]').first().click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Cannot restore a user that is currently in the queue. Please remove them first.');
        cy.get('.filter-buttons').first().click(); // turn first "off"
        cy.get('.filter-buttons').first().click(); // turn first "on"
        cy.get('[data-testid="toggle-filter-settings"]').first().click();
        cy.get('[data-testid="toggle-queue-checkbox"]').first().click(); // closeFirstQueue
        cy.get('[data-testid="empty-queue-btn"]').first().click(); // emptyFirstQueue
        editAnnouncement('Submitty');
        cy.get('[data-testid="popup-message"]').should('contain', 'Updated announcement');
        editAnnouncement('');
        cy.get('[data-testid="announcement"]').should('not.exist');
        cy.get('[data-testid="popup-message"]').should('contain', 'Updated announcement');

        // Confirm student queue history
        cy.get('[data-testid="search-student-queue-input"]').first().type('student');
        cy.get('[data-testid="search-student-queue-btn"]').first().click();
        cy.get('[data-testid="student-row-1"]')
            .contains('[data-testid="row-label"]', '1').siblings()
            .contains('[data-testid="current-state"]', 'done').siblings()
            .contains('[data-testid="queue"]', 'Lab Help').siblings()
            // This checks if time entered and time removed are in fact times.
            // We do not check for a specific time because this may change.
            .contains('[data-testid="time-entered"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="time-removed"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="helped-by"]', 'grader').siblings()
            .contains('[data-testid="removed-by"]', 'grader').siblings()
            .contains('[data-testid="removal-method"]', 'helped');
        cy.get('[data-testid="student-row-2"]').contains('[data-testid="row-label"]', '2')
            .contains('[data-testid="row-label"]', '2').siblings()
            .contains('[data-testid="current-state"]', 'done').siblings()
            .contains('[data-testid="queue"]', 'Lab Help').siblings()
            .contains('[data-testid="time-entered"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="time-removed"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="helped-by"]', '-').siblings()
            .contains('[data-testid="removed-by"]', 'instructor').siblings()
            .contains('[data-testid="removal-method"]', 'emptied');
        cy.get('[data-testid="student-row-3"]').contains('[data-testid="row-label"]', '3')
            .contains('[data-testid="row-label"]', '3').siblings()
            .contains('[data-testid="current-state"]', 'done').siblings()
            .contains('[data-testid="queue"]', 'Cypress Office Hour Queue 1').siblings()
            .contains('[data-testid="time-entered"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="time-removed"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="helped-by"]', '-').siblings()
            .contains('[data-testid="removed-by"]', 'student').siblings()
            .contains('[data-testid="removal-method"]', 'self');
        cy.get('[data-testid="student-row-4"]').contains('[data-testid="row-label"]', '4')
            .contains('[data-testid="row-label"]', '4').siblings()
            .contains('[data-testid="current-state"]', 'done').siblings()
            .contains('[data-testid="queue"]', 'Cypress Office Hour Queue 1').siblings()
            .contains('[data-testid="time-entered"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="time-removed"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="helped-by"]', 'instructor').siblings()
            .contains('[data-testid="removed-by"]', 'student').siblings()
            .contains('[data-testid="removal-method"]', 'self_helped');
        cy.get('[data-testid="student-row-5"]').contains('[data-testid="row-label"]', '5')
            .contains('[data-testid="row-label"]', '5').siblings()
            .contains('[data-testid="current-state"]', 'waiting').siblings()
            .contains('[data-testid="queue"]', 'Cypress Office Hour Queue 2').siblings()
            .contains('[data-testid="time-entered"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="time-removed"]', '-').siblings()
            .contains('[data-testid="helped-by"]', '-').siblings()
            .contains('[data-testid="removed-by"]', '-').siblings()
            .contains('[data-testid="removal-method"]', '-');
        cy.get('#times-helped-cell').should('contain', '1 times helped.');

        // Confirm aphacker queue history
        // Use search autocomplete feature
        cy.get('[data-testid="search-student-queue-input"]').first().clear().type('hack');
        cy.get('#ui-id-1').first().click();
        cy.get('[data-testid="search-student-queue-input"]').first().should('have.value', 'aphacker');
        cy.get('[data-testid="search-student-queue-btn"]').first().click();
        cy.get('[data-testid="student-row-1"]')
            .contains('[data-testid="row-label"]', '1').siblings()
            .contains('[data-testid="current-state"]', 'done').siblings()
            .contains('[data-testid="queue"]', 'Homework Debugging').siblings()
            .contains('[data-testid="time-entered"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="time-removed"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="helped-by"]', 'ta').siblings()
            .contains('[data-testid="removed-by"]', 'instructor').siblings()
            .contains('[data-testid="removal-method"]', 'emptied');
        cy.get('[data-testid="student-row-2"]').contains('[data-testid="row-label"]', '2')
            .contains('[data-testid="row-label"]', '2').siblings()
            .contains('[data-testid="current-state"]', 'waiting').siblings()
            .contains('[data-testid="queue"]', 'Cypress Office Hour Queue 2').siblings()
            .contains('[data-testid="time-entered"]', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/).siblings()
            .contains('[data-testid="time-removed"]', '-').siblings()
            .contains('[data-testid="helped-by"]', '-').siblings()
            .contains('[data-testid="removed-by"]', '-').siblings()
            .contains('[data-testid="removal-method"]', '-');
        cy.get('#times-helped-cell').should('contain', '0 times helped.');

        // Disable and delete all queue
        disableQueue();
    });
});
