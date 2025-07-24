import { getCurrentSemester, getApiKey } from '../../support/utils.js';

const detailsButtonText = /Show Details|Hide Details/;

const emailContent = 'This is a test email body.';
const errorMessage = 'This is a test error message';

const threadAnnouncement = 'This is a test announcement';
const threadContent = 'This is a test content';

const normalizeText = (text) => {
    return text.trim().replace(/\s+/g, ' ');
};

const extractUserCount = (text) => {
    const match = text.match(/\((\d+)\s+users\)/);
    return match ? parseInt(match[1], 10) : 0;
};

const verifyEmail = (subject, source) => {
    cy.get('[data-testid="email-subject"]')
        .invoke('text')
        .then((text) => expect(text.trim()).to.equal(
            `Email Subject: ${subject}`,
        ));
    cy.get('[data-testid="email-time-created"]')
        .invoke('text')
        .then((text) => expect(text.trim()).to.match(
            /Time Created: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/,
        ));
    cy.get('[data-testid="email-source"]')
        .invoke('text')
        .then((text) => expect(text.trim()).to.equal(source));
};

const verifyEmailRecipient = (email, error = false) => {
    // Use native DOM methods to improve parsing performance on large recipient lists
    const $email = Cypress.$(email);

    expect($email.find('[data-testid="email-recipient"]').text().trim()).to.match(
        /^Recipient: .+$/,
    );
    // System could send emails during the UI verification, so we need to check for both potential states
    expect($email.find('[data-testid="email-time-sent"]').text().trim()).to.match(
        /Time Sent: (Not Sent|\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})$/,
    );
    expect($email.find('[data-testid="email-address"]').text().trim()).to.match(
        /^Email Address: .+@.+\..+$/,
    );

    if (error) {
        expect($email.find('[data-testid="email-error"]').text().trim()).to.equal(
            `Error: ${errorMessage}`,
        );
    }
    else {
        expect($email.find('[data-testid="email-error"]').length).to.equal(0);
    }
};

const verifyEmails = (status = '', error = false, length = null) => {
    cy.get('#collapse1')
        .should('exist')
        .within(() => {
            if (length !== null) {
                cy.get(`li.status${status}`)
                    .should('have.length', length);
            }

            cy.get(`li.status${status}`)
                .then(($emails) => {
                    $emails.each((_, email) => {
                        verifyEmailRecipient(email, error);
                    });
                });
        });
};

const verifyEmailDetails = (subject, source, status, error, totalRecipients = null) => {
    cy.contains('[data-testid="email-button-container"]', subject)
        .closest('[data-testid="email-status-container"]')
        .within(() => {
            if (totalRecipients !== null) {
                cy.get('[data-testid="email-button-container"]')
                    .should('contain', `(${totalRecipients})`);
            }
            // Verify the email subject and source
            verifyEmail(subject, source);

            // Open the details list
            cy.get('[data-testid="email-button-container"]')
                .contains(detailsButtonText)
                .click({ force: true });
        });

    // Verify each recipient is properly rendered in the details list
    verifyEmails(status, error, totalRecipients);
};

const verifyPaginatedEmailStatus = (email, response, index) => {
    const $email = Cypress.$(email);
    const expectedEmail = response.data[index];
    // Verify the base email status information
    expect($email.find('[data-testid="email-subject"]').text().trim()).to.equal(
        `Email Subject: ${expectedEmail.subject}`,
    );
    expect($email.find('[data-testid="email-time-created"]').text().trim()).to.equal(
        `Time Created: ${expectedEmail.created}`,
    );
    expect($email.find('[data-testid="email-source"]').text().trim()).to.equal(
        `Course: ${getCurrentSemester()} sample`,
    );
    // Verify the total recipients for the current email drop-down matches the server response count
    cy.get(`#collapse${index + 1} li`).should('have.length', expectedEmail.count);
};

describe('Test cases involving the superuser Email All functionality', () => {
    it('sends an email via Email All and verifies Email Status page', () => {
        cy.login('superuser');
        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        cy.url().should('include', '/superuser/email');
        cy.get('[data-testid="system-wide-email-title"]').should('contain', 'System Wide Email');

        // Store user counts based on their access group to determine the total recipients
        const recipientCounts = {
            instructor: 0,
            fullAccess: 0,
            limitedAccess: 0,
            student: 0,
            faculty: 0,
        };

        const verifyDefaultRecipients = (input, key, expected) => {
            cy.get(input)
                .should(key === 'secondary' ? 'not.be.checked' : 'be.checked')
                .parent()
                .invoke('text')
                .then((text) => {
                    const normalized = normalizeText(text);
                    if (key !== 'secondary') {
                        recipientCounts[key] = extractUserCount(normalized);
                    }
                    expect(normalized).to.match(expected);
                });
        };

        const defaultRecipients = {
            faculty: ['#email-faculty', /^Email all Faculty and all Superusers \(\d+ users\)$/],
            instructor: ['#email-instructor', /^Email all Instructors of Active\/Non-Archived Courses \(\d+ users\)$/],
            fullAccess: ['#email-full-access', /^Email all Full Access Graders of Active\/Non-Archived Courses \(\d+ users\)$/],
            limitedAccess: ['#email-limited-access', /^Email all Limited Access Graders of Active\/Non-Archived Courses \(\d+ users\)$/],
            student: ['#email-student', /^Email all Students in Active\/Non-Archived Courses \(\d+ users\)$/],
            secondary: ['#email-to-secondary', /^Send email to each user's primary and secondary email addresses, regardless of the user's notification selection$/],
        };

        Object.entries(defaultRecipients).forEach(([recipient, [input, expected]]) => {
            verifyDefaultRecipients(input, recipient, expected);
        });

        // Wait for the total recipients count to be fully parsed
        cy.then(() => {
            // Instructors should not be included in the total recipients as they're included in the faculty bucket
            const totalRecipients = recipientCounts.faculty + recipientCounts.fullAccess + recipientCounts.limitedAccess + recipientCounts.student;

            let uniqueSubject = `Test Email - ${Date.now()}`;
            cy.get('[data-testid="email-subject"]').type(uniqueSubject);
            cy.get('[data-testid="email-content"]').type(emailContent);
            cy.get('[data-testid="send-email"]').click();
            cy.get('[data-testid="sidebar"]')
                .contains('Email Status')
                .click();

            // Verify the automatic email subject prefix insertion and total recipients count
            uniqueSubject = `[Submitty Admin Announcement]: ${uniqueSubject}`;
            verifyEmailDetails(uniqueSubject, 'Submitty Administrator Email', '.status-warning', false, totalRecipients);
        });
    });

    it('sends an email via Email All and verifies emails that are queued, failed to send, and have been sent', () => {
        cy.login('superuser');
        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        let uniqueSubject = `Test Email - ${Date.now()}`;
        cy.get('[data-testid="email-subject"]').type(uniqueSubject);
        cy.get('[data-testid="email-content"]').type(emailContent);
        cy.get('[data-testid="send-email"]').click();
        cy.get('[data-testid="sidebar"]')
            .contains('Email Status')
            .click();

        uniqueSubject = `[Submitty Admin Announcement]: ${uniqueSubject}`;
        cy.contains('[data-testid="email-button-container"]', uniqueSubject)
            .closest('[data-testid="email-status-container"]')
            .then(() => {
                // Apply an email error for testing purposes
                getApiKey('superuser', 'superuser').then((apiKey) => {
                    cy.request({
                        method: 'PUT',
                        url: `${Cypress.config('baseUrl')}/api/superuser/email/error`,
                        headers: {
                            'Authorization': apiKey,
                            'Content-Type': 'application/json',
                        },
                        body: {
                            subject: uniqueSubject,
                            error: errorMessage,
                        },
                    }).then((res) => {
                        expect(res.status).to.eq(200);
                        expect(res.body).to.have.property('status', 'success');
                        expect(res.body).to.have.property('data', null);
                        cy.reload();
                        verifyEmailDetails(uniqueSubject, 'Submitty Administrator Email', '.status-error', true);
                    });
                });
            }).as('email-error');

        cy.get('@email-error').then(() => {
            // Set an email as sent for testing purposes
            getApiKey('superuser', 'superuser').then((apiKey) => {
                cy.request({
                    method: 'PUT',
                    url: `${Cypress.config('baseUrl')}/api/superuser/email/sent`,
                    headers: {
                        'Authorization': apiKey,
                        'Content-Type': 'application/json',
                    },
                    body: {
                        subject: uniqueSubject,
                    },
                }).then((res) => {
                    expect(res.status).to.eq(200);
                    expect(res.body).to.have.property('status', 'success');
                    expect(res.body).to.have.property('data', null);
                    cy.reload();
                    verifyEmailDetails(uniqueSubject, 'Submitty Administrator Email', '.status-success', false);
                });
            });
        });
    });
});

describe('Test cases involving instructor send email via thread announcement functionality', () => {
    it('sends an email via thread announcement and verifies the email is queued or sent', () => {
        cy.login('instructor');
        cy.visit(['sample', 'forum', 'threads', 'new']);

        // Enter the thread announcement details
        cy.get('[data-testid="title"]').type(threadAnnouncement);
        cy.get('[data-testid="reply_box_1"]').type(threadContent);
        // Pick an arbitrary category
        cy.get('[data-testid="categories-pick-list"] .cat-buttons').first().click({ force: true });
        // Ensure the announcement checkbox is checked
        cy.get('#Announcement').click();
        // Pin thread should automatically be checked based on the announcement checkbox selection
        cy.get('#pinThread').should('be.checked');
        // Submit the thread announcement
        cy.get('[data-testid="forum-publish-thread"]').click({ force: true });

        // Expect a redirection to the forum threads page
        cy.url().should('not.include', '/sample/forum/threads/new').then(() => {
            cy.visit(['sample', 'email_status']).then(() => {
                // Verify the email status is awaiting to be sent or has been sent
                const uniqueSubject = `New Announcement: ${threadAnnouncement}`;
                verifyEmailDetails(uniqueSubject, `Course: ${getCurrentSemester()} sample`, '.status-warning', false);

                // Clean up the testing thread
                cy.visit(['sample', 'forum', 'threads']).then(() => {
                    cy.get('[data-testid="thread-list-item"]').contains(threadAnnouncement).click();
                    cy.get('[data-testid="thread-dropdown"]').first().click();
                    cy.get('[data-testid="delete-post-button"]').first().click({ force: true });
                });
            });
        });
    });
});

describe('Test cases involving instructor email pagination functionality', () => {
    it('verifies the email status page pagination', () => {
        // Superuser and instructor pagination share the same pagination logic
        cy.login('instructor');
        cy.visit(['sample', 'email_status']);

        let page = 1;
        let lastPage = -1;

        cy.get('.pagination [data-testid="page-num-button"]').last().then(($lastPage) => {
            lastPage = parseInt($lastPage.text(), 10);
        }).as('lastPage');

        // Recursively verify all pages on the email status page
        const verifyPage = () => {
            getApiKey('instructor', 'instructor').then((apiKey) => {
                cy.request({
                    method: 'GET',
                    url: `${Cypress.config('baseUrl')}/api/courses/${getCurrentSemester()}/sample/email/email_status_page?page=${page}&format=json`,
                    headers: {
                        Authorization: apiKey,
                    },
                }).then((res) => {
                    const response = JSON.parse(res.body);
                    expect(response.status).to.eq('success');
                    expect(Array.isArray(response.data)).to.be.true;

                    // Verify each email is properly rendered based on it's page item index
                    cy.get('[data-testid="email-status-container"]')
                        .should('have.length', response.data.length)
                        .then(($emails) => {
                            $emails.each((index, email) => verifyPaginatedEmailStatus(email, response, index));
                        });
                });

                page++;

                if (page > lastPage) {
                    return;
                }

                // Visit the next page via the pagination buttons
                cy.get(`#${page}`).click({ force: true });
                cy.get('.page-btn.selected').then(($selected) => {
                    expect($selected.attr('id')).to.equal(page.toString());
                    verifyPage();
                });
            });
        };

        cy.get('@lastPage').then(() => {
            verifyPage();
        });
    });
});
