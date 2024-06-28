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
const openNewQueue = (queueName, queueCode = '') => {
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

const changeQueueCode = (queueName, queueCode = '') => {
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
const editAnnouncement = (text = '') => {
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
        cy.get('[data-testid="remove-from-queue-btn"]').first().click(); // remove First Student
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

        cy.get('[data-testid="student-row-1"]').first().as('row-1');
        cy.get('@row-1').find('[data-testid="row-label"]').should('contain', '1');
        cy.get('@row-1').find('[data-testid="current-state"]').should('contain', 'done');
        cy.get('@row-1').find('[data-testid="queue"]').should('contain', 'Lab Help');
        // This checks if time entered and time removed are in fact times.
        // We do not check for a specific time because this may change.
        cy.get('@row-1').find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-1').find('[data-testid="time-removed"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-1').find('[data-testid="helped-by"]').should('contain', 'grader');
        cy.get('@row-1').find('[data-testid="removed-by"]').should('contain', 'grader');
        cy.get('@row-1').find('[data-testid="removal-method"]').should('contain', 'helped');

        cy.get('[data-testid="student-row-2"]').first().as('row-2');
        cy.get('@row-2').find('[data-testid="row-label"]').should('contain', '2');
        cy.get('@row-2').find('[data-testid="current-state"]').should('contain', 'done');
        cy.get('@row-2').find('[data-testid="queue"]').should('contain', 'Lab Help');
        cy.get('@row-2').find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-2').find('[data-testid="time-removed"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-2').find('[data-testid="helped-by"]').should('contain', '-');
        cy.get('@row-2').find('[data-testid="removed-by"]').should('contain', 'instructor');
        cy.get('@row-2').find('[data-testid="removal-method"]').should('contain', 'emptied');

        cy.get('[data-testid="student-row-3"]').first().as('row-3');
        cy.get('@row-3').find('[data-testid="row-label"]').should('contain', '3');
        cy.get('@row-3').find('[data-testid="current-state"]').should('contain', 'done');
        cy.get('@row-3').find('[data-testid="queue"]').should('contain', queueName);
        cy.get('@row-3').find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-3').find('[data-testid="time-removed"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-3').find('[data-testid="helped-by"]').should('contain', '-');
        cy.get('@row-3').find('[data-testid="removed-by"]').should('contain', 'student');
        cy.get('@row-3').find('[data-testid="removal-method"]').should('contain', 'self');

        cy.get('[data-testid="student-row-4"]').first().as('row-4');
        cy.get('@row-4').find('[data-testid="row-label"]').should('contain', '4');
        cy.get('@row-4').find('[data-testid="current-state"]').should('contain', 'done');
        cy.get('@row-4').find('[data-testid="queue"]').should('contain', queueName);
        cy.get('@row-4').find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-4').find('[data-testid="time-removed"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-4').find('[data-testid="helped-by"]').should('contain', 'instructor');
        cy.get('@row-4').find('[data-testid="removed-by"]').should('contain', 'student');
        cy.get('@row-4').find('[data-testid="removal-method"]').should('contain', 'self_helped');

        cy.get('[data-testid="student-row-5"]').first().as('row-5');
        cy.get('@row-5').find('[data-testid="row-label"]').should('contain', '5');
        cy.get('@row-5').find('[data-testid="current-state"]').should('contain', 'waiting');
        cy.get('@row-5').find('[data-testid="queue"]').should('contain', queueName1);
        cy.get('@row-5').find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-5').find('[data-testid="time-removed"]').should('contain', '-');
        cy.get('@row-5').find('[data-testid="helped-by"]').should('contain', '-');
        cy.get('@row-5').find('[data-testid="removed-by"]').should('contain', '-');
        cy.get('@row-5').find('[data-testid="removal-method"]').should('contain', '-');

        cy.get('#times-helped-cell').should('contain', '1 times helped.');

        // Confirm aphacker queue history
        // Use search autocomplete feature
        cy.get('[data-testid="search-student-queue-input"]').first().clear().type('hack');
        cy.get('#ui-id-1').first().should('be.visible');
        cy.get('#ui-id-1').click();
        cy.get('[data-testid="search-student-queue-input"]').first().should('have.value', 'aphacker');
        cy.get('[data-testid="search-student-queue-btn"]').first().click();

        cy.get('[data-testid="student-row-1"]').first().as('row-1');
        cy.get('@row-1').find('[data-testid="row-label"]').should('contain', '1');
        cy.get('@row-1').find('[data-testid="current-state"]').should('contain', 'done');
        cy.get('@row-1').find('[data-testid="queue"]').should('contain', 'Homework Debugging');
        cy.get('@row-1').find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-1').find('[data-testid="time-removed"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-1').find('[data-testid="helped-by"]').should('contain', 'ta');
        cy.get('@row-1').find('[data-testid="removed-by"]').should('contain', 'instructor');
        cy.get('@row-1').find('[data-testid="removal-method"]').should('contain', 'emptied');

        cy.get('[data-testid="student-row-2"]').first().as('row-2');
        cy.get('@row-2').find('[data-testid="row-label"]').should('contain', '2');
        cy.get('@row-2').find('[data-testid="current-state"]').should('contain', 'waiting');
        cy.get('@row-2').find('[data-testid="queue"]').should('contain', 'Cypress Office Hour Queue 2');
        cy.get('@row-2').find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
        cy.get('@row-2').find('[data-testid="time-removed"]').should('contain', '-');
        cy.get('@row-2').find('[data-testid="helped-by"]').should('contain', '-');
        cy.get('@row-2').find('[data-testid="removed-by"]').should('contain', '-');
        cy.get('@row-2').find('[data-testid="removal-method"]').should('contain', '-');

        cy.get('#times-helped-cell').should('contain', '0 times helped.');

        // Disable and delete all queue
        disableQueue();
    });
});
