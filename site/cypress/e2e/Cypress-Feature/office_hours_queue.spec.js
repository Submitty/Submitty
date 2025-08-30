import { isPermissionAllowed } from 'cypress-browser-permissions';
import { verifyWebSocketStatus } from '../../support/utils';

const queueName = 'Cypress Office Hour Queue 1';
const queueName_random = 'Cypress Office Hour Queue Random';
const queueName_blank = 'Cypress Office Hour Queue Blank';
const queueCode = 'cypress_test';
const queueCode1 = 'cypress_test_fail';
const newQueueCode = 'cypress_update';

const bitdiddleRows = [{
    state: 'done',
    queue: queueName,
    helpedBy: '-',
    removedBy: 'bitdiddle',
    removalMethod: 'self',
},
{
    state: 'done',
    queue: queueName_blank,
    helpedBy: '-',
    removedBy: 'bitdiddle',
    removalMethod: 'self',
},
{
    state: 'done',
    queue: queueName,
    helpedBy: 'instructor',
    removedBy: 'bitdiddle',
    removalMethod: 'self_helped',
},
{
    state: 'done',
    queue: queueName,
    helpedBy: '-',
    removedBy: 'instructor',
    removalMethod: 'emptied',
}];

const wisozaRows = [{
    state: 'waiting',
    queue: queueName_blank,
    helpedBy: '-',
    removedBy: '-',
    removalMethod: '-',
}];

const checkRows = (rows) => {
    cy.get('[data-testid="row-label"]')
        .its('length')
        .then((length) => {
            const startingRow = length - rows.length + 1;
            for (let i = 0; i < rows.length; i++) {
                cy.get(`[data-testid="student-row-${startingRow + i}"]`).first().as(`row-${i}`);
                cy.get(`@row-${i}`).find('[data-testid="row-label"]').should('contain', startingRow + i);
                cy.get(`@row-${i}`).find('[data-testid="current-state"]').should('contain', rows[i].state);
                cy.get(`@row-${i}`).find('[data-testid="queue"]').should('contain', rows[i].queue);
                if (rows[i].state !== 'waiting') {
                    // This checks if time entered and time removed are in fact times.
                    // We do not check for a specific time because this may change.
                    cy.get(`@row-${i}`).find('[data-testid="time-entered"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
                    cy.get(`@row-${i}`).find('[data-testid="time-removed"]').invoke('text').should('match', /\d{4}-\d\d-\d\d \d\d:\d\d:\d\d.*/);
                }
                cy.get(`@row-${i}`).find('[data-testid="helped-by"]').should('contain', rows[i].helpedBy);
                cy.get(`@row-${i}`).find('[data-testid="removed-by"]').should('contain', rows[i].removedBy);
                cy.get(`@row-${i}`).find('[data-testid="removal-method"]').should('contain', rows[i].removalMethod);
            }
        });
};

const deleteQueue = (queue_name) => {
    cy.visit(['sample', 'office_hours_queue']); // office hours queue
    cy.get('[data-testid="toggle-filter-settings"]').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('[data-testid="queue-name"]').contains(queue_name).parents('[data-testid="queue-item"]').find('[data-testid="delete-queue-btn"]').click();
};

const openNewQueue = (queueName, queueCode = '') => {
    cy.visit(['sample', 'office_hours_queue']);
    cy.get('[data-testid="toggle-new-queue"]').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('[data-testid="new-queue-code"]').type(queueName);
    if (queueCode === 'RANDOM') {
        cy.get('[data-testid="new-queue-rand-token"]').click(); // random code
        cy.get('[data-testid="new-queue-token"]').invoke('val').should('not.be.empty');
    }
    else if (queueCode !== '') {
        cy.get('[data-testid="new-queue-token"]').type(queueCode);
    }
    cy.get('[data-testid="open-new-queue-btn"]').click();
};

const changeQueueCode = (queueName, queueCode) => {
    cy.get('[data-testid="toggle-filter-settings"]').click();
    cy.get('[data-testid="popup-window"]').should('exist');
    cy.get('[data-testid="old-queue-code"]').select(queueName);
    if (queueCode === 'RANDOM') {
        cy.get('[data-testid="old-queue-rand-token"]').click(); // random code
        cy.get('[data-testid="old-queue-token"]').invoke('val').should('not.be.empty');
    }
    else if (queueCode !== '') {
        cy.get('[data-testid="old-queue-token"]').type(queueCode);
    }
    cy.get('[data-testid="change-code-btn"]').click(); // update it
};

const switchUser = (account) => {
    cy.logout();
    cy.login(account);
    cy.visit(['sample', 'office_hours_queue']);
    verifyWebSocketStatus();
};

const studentJoinQueue = (queueName, queueCode) => {
    cy.get('[data-testid="queue-code"]').select(queueName);
    cy.get('[data-testid="queue-code"]').invoke('val'); // in which queue you want to join
    cy.get('[data-testid="queue-code"]').should('contain', queueName);
    if (queueCode !== '') {
        cy.get('#token-box').type(queueCode);
    }
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
    before(() => {
        cy.login();

        // enable queue if not already enabled
        cy.visit(['sample', 'config']); // course setting
        cy.get('[data-testid="queue-enabled"]').check();
        cy.get('[data-testid="queue-enabled"]').should('be.checked');
        cy.reload();

        openNewQueue(queueName, queueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'New queue added');
        openNewQueue(queueName_random, 'RANDOM');
        cy.get('[data-testid="popup-message"]').should('contain', 'New queue added');
        openNewQueue(queueName_blank, '');
        cy.get('[data-testid="popup-message"]').should('contain', 'New queue added');
    });

    it('Creating queues and changing queue codes', () => {
        cy.login();
        // Create queue with same name but different code to ensure error
        openNewQueue(queueName, queueCode1);
        cy.get('[data-testid="popup-message"]').should('contain', 'Unable to add queue. Make sure you have a unique queue name');

        changeQueueCode(queueName_random, 'RANDOM');
        cy.get('[data-testid="popup-message"]').should('contain', 'Queue Access Code Changed');

        changeQueueCode(queueName_blank, '');
        cy.get('[data-testid="popup-message"]').should('contain', 'Queue Access Code Changed');

        // Change 1st queue's code
        changeQueueCode(queueName, newQueueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'Queue Access Code Changed');
    });

    it('Joining queues as student', () => {
        // switch to student to join queue
        switchUser('bitdiddle');

        // Join first queue created with user 'bitdiddle'
        studentJoinQueue(queueName, newQueueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
        // Remove user 'bitdiddle' from queue
        cy.get('[data-testid="leave-queue"]').click(); // studentRemoveSelfFromQueue
        cy.get('[data-testid="popup-message"]').should('contain', 'Removed from queue');

        // Join first queue created with blank code with user 'bitdiddle'
        studentJoinQueue(queueName_blank, '');
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
        // Remove user 'bitdiddle' from queue
        cy.get('[data-testid="leave-queue"]').click(); // studentRemoveSelfFromQueue
        cy.get('[data-testid="popup-message"]').should('contain', 'Removed from queue');

        // Rejoin first queue created with user 'bitdiddle'
        studentJoinQueue(queueName, newQueueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
    });
    it('Helping student', () => {
        // switch to instructor to help first student
        switchUser('instructor');
        // help 'bitdiddle' in first queue created
        cy.get('.help_btn').last().click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Started helping student');

        // switch to student for finishing help
        switchUser('bitdiddle');
        cy.get('[data-testid="self-finish-help"]').click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Finished helping student');
        // Rejoin queue as 'bitdiddle'
        studentJoinQueue(queueName, newQueueCode);
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');

        // switch to student (wisoza)
        switchUser('wisoza');
        // Join blank queue with user 'wisoza'
        studentJoinQueue(queueName_blank, '');
        cy.get('[data-testid="popup-message"]').should('contain', 'Added to queue');
    });

    it('Helping,removing student and toggle queue as Instructor', () => {
        // Switch to instructor for helping students and changing settings
        switchUser('instructor');
        // Start helping 'bitdiddle' in first queue created
        cy.get('.help_btn').eq(4).click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Started helping student');
        // Finish helping 'bitdiddle' in first queue created
        cy.get('.finish_helping_btn').last().click(); // finished helping first student
        cy.get('[data-testid="popup-message"]').should('contain', 'Finished helping student');
        // Remove 'wisoza' from queue
        cy.get('[data-testid="remove-from-queue-btn"]').last().click(); // remove First Student
        cy.get('[data-testid="popup-message"]').should('contain', 'Removed from queue');
        // Restore 'bitdiddle' to newly created queue
        cy.get('[data-testid="queue-restore-btn"]').first().click(); // restore first Student
        cy.get('[data-testid="popup-message"]').should('contain', 'Student restored');
        // Restore 'wisoza' to blank queue
        cy.get('[data-testid="queue-restore-btn"]').first().click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Student restored');
        // Attempt to restore 'bitdiddle' to newly created queue
        cy.get('[data-testid="queue-restore-btn"]').first().click();
        cy.get('[data-testid="popup-message"]').should('contain', 'Cannot restore a user that is currently in the queue. Please remove them first.');
        // Ensure Wally is shown in queue before filtered
        cy.contains('huelsw@sample.edu').should('be.visible');
        // Filter to show only 'Lab Help' queue
        cy.get('.filter-buttons').first().click(); // turn first "off"
        // Ensure Wally isn't shown in queue after filtered
        cy.contains('huelsw@sample.edu').should('not.be.visible');
        cy.get('.filter-buttons').first().click(); // turn first "on"
        cy.get('[data-testid="toggle-filter-settings"]').first().click();
        // Close the newly created queue
        cy.get('[data-testid="toggle-queue-checkbox"]').eq(2).click(); // closeFirstQueue
        // Empty newly created queue
        cy.get('[data-testid="empty-queue-btn"]').eq(2).click(); // emptyFirstQueue

        editAnnouncement('Submitty');
        cy.get('[data-testid="popup-message"]').should('contain', 'Updated announcement');

        editAnnouncement('');
        cy.get('[data-testid="announcement"]').should('not.exist');
        cy.get('[data-testid="popup-message"]').should('contain', 'Updated announcement');

        // Confirm student queue history
        cy.get('[data-testid="search-student-queue-input"]').first().type('bitdiddle');
        cy.get('[data-testid="search-student-queue-btn"]').first().click();
        cy.contains('(ID:bitdiddle)').should('be.visible');

        checkRows(bitdiddleRows);

        cy.get('#times-helped-cell').should('contain', '1 times helped.');

        // Confirm wisoza queue history
        // Use search autocomplete feature
        cy.get('[data-testid="search-student-queue-input"]').first().as('queue-search');
        cy.get('@queue-search').clear();
        cy.get('@queue-search').type('wisoza');

        cy.get('#ui-id-1').first().should('be.visible');
        cy.get('#ui-id-1').click();
        cy.get('[data-testid="search-student-queue-input"]').first().should('have.value', 'wisoza');
        cy.get('[data-testid="search-student-queue-btn"]').first().click();
        cy.contains('(ID:wisoza)').should('be.visible');

        checkRows(wisozaRows);

        cy.get('#times-helped-cell').should('contain', '0 times helped.');
    });

    it('Enabling push and sound notifications as Instructor', () => {
        // Ensure notifications are allowed
        expect(isPermissionAllowed('notifications')).to.be.true;

        switchUser('instructor');

        // Assert that switches exist and assign aliases
        cy.get('[data-testid="notification-switch-container"]').first().as('switch-container');
        cy.get('@switch-container').should('exist');
        cy.get('@switch-container').find('[data-testid="push-notification-switch"]').first().as('push-switch');
        cy.get('@switch-container').find('[data-testid="sound-notification-switch"]').first().as('sound-switch');
        cy.get('@push-switch').should('exist');
        cy.get('@sound-switch').should('exist');
        cy.window().its('push_notifications_enabled').as('push-enabled');
        cy.window().its('audible_notifications_enabled').as('audio-enabled');

        // Turn notification switches on, then turn them off
        cy.get('@push-enabled').should('equal', false);
        cy.get('@audio-enabled').should('equal', false);
        cy.get('@push-switch').click();
        cy.get('@sound-switch').click();
        cy.get('@push-enabled').should('equal', true);
        cy.get('@audio-enabled').should('equal', true);
        cy.get('@push-switch').click();
        cy.get('@sound-switch').click();
        cy.get('@push-enabled').should('equal', false);
        cy.get('@audio-enabled').should('equal', false);
    });

    after(() => {
        cy.login();
        // Delete all created queues
        deleteQueue(queueName);
        deleteQueue(queueName_random);
        deleteQueue(queueName_blank);
    });
});
