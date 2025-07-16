import { getCurrentSemester, getApiKey } from '../../support/utils.js';

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
            'Error: This is a test error message',
        );
    }
    else {
        expect($email.find('[data-testid="email-error"]').length).to.equal(0);
    }
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

        cy.get('#email-faculty')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.faculty = extractUserCount(normalized);
                expect(normalized).to.match(
                    /^Email all Faculty and all Superusers \(\d+ users\)$/,
                );
            });

        cy.get('#email-instructor')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.instructor = extractUserCount(normalized);
                expect(normalized).to.match(
                    /^Email all Instructors of Active\/Non-Archived Courses \(\d+ users\)$/,
                );
            });

        cy.get('#email-full-access')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.fullAccess = extractUserCount(normalized);
                expect(normalized).to.match(
                    /^Email all Full Access Graders of Active\/Non-Archived Courses \(\d+ users\)$/,
                );
            });

        cy.get('#email-limited-access')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.limitedAccess = extractUserCount(normalized);
                expect(normalized).to.match(
                    /^Email all Limited Access Graders of Active\/Non-Archived Courses \(\d+ users\)$/,
                );
            });

        cy.get('#email-student')
            .should('be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                recipientCounts.student = extractUserCount(normalized);
                expect(normalized).to.match(
                    /^Email all Students in Active\/Non-Archived Courses \(\d+ users\)$/,
                );
            });

        cy.get('#email-to-secondary')
            .should('not.be.checked')
            .parent()
            .invoke('text')
            .then((text) => {
                const normalized = normalizeText(text);
                expect(normalized).to.match(
                    /^Send email to each user's primary and secondary email addresses, regardless of the user's notification selection$/,
                );
            });

        // Wait for the total recipients count to be fully parsed
        cy.then(() => {
            // Instructors should not be included in the total recipients as they're included in the faculty bucket
            const totalRecipients = recipientCounts.faculty + recipientCounts.fullAccess + recipientCounts.limitedAccess + recipientCounts.student;

            let uniqueSubject = `Test Email - ${Date.now()}`;
            cy.get('[data-testid="email-subject"]').type(uniqueSubject);
            cy.get('[data-testid="email-content"]').type('This is a test email body.');

            cy.get('[data-testid="send-email"]').click();

            cy.get('[data-testid="sidebar"]')
                .contains('Email Status')
                .click();

            // Verify the email subject email prefix addition and total recipients count
            uniqueSubject = `[Submitty Admin Announcement]: ${uniqueSubject}`;
            cy.contains('.button-container', uniqueSubject)
                .should('contain', `(${totalRecipients})`)
                .closest('.status-container')
                .within(() => {
                    verifyEmail(uniqueSubject, 'Submitty Administrator Email');

                    // btn-primary (blue) implies awaiting to be sent
                    cy.get('button.status-btn.btn-primary')
                        .contains(/Show Details|Hide Details/)
                        .click();
                });

            // Verify each recipient is properly rendered
            cy.get('#collapse1')
                .should('exist')
                .within(() => {
                    cy.get('li.status.status-warning')
                        .should('have.length', totalRecipients)
                        .then(($emails) => {
                            $emails.each((_, email) => {
                                verifyEmailRecipient(email);
                            });
                        });
                });
        });
    });

    it('sends an email via Email All and verifies emails that are queued, failed to send, and have been sent', () => {
        cy.login('superuser');

        cy.get('[data-testid="sidebar"]')
            .contains('Email All')
            .click();

        let uniqueSubject = `Test Email - ${Date.now()}`;
        cy.get('[data-testid="email-subject"]').type(uniqueSubject);
        cy.get('[data-testid="email-content"]').type('This is a test email body.');
        cy.get('[data-testid="send-email"]').click();

        cy.get('[data-testid="sidebar"]')
            .contains('Email Status')
            .click();

        // Apply an email error for testing purposes
        uniqueSubject = `[Submitty Admin Announcement]: ${uniqueSubject}`;
        cy.contains('.button-container', uniqueSubject)
            .closest('.status-container')
            .then(() => {
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
                            error: 'This is a test error message',
                        },
                    }).then((res) => {
                        // Verify the server response is successful
                        expect(res.status).to.eq(200);
                        expect(res.body).to.have.property('status', 'success');
                        expect(res.body).to.have.property('data', null);

                        // Verify the email error is displayed after a full page reload
                        cy.reload().then(() => {
                            cy.contains('.button-container', uniqueSubject)
                                .closest('.status-container')
                                .within(() => {
                                    verifyEmail(uniqueSubject, 'Submitty Administrator Email');

                                    // btn-danger (red) implies email error
                                    cy.get('button.status-btn.btn-danger')
                                        .contains(/Show Details|Hide Details/)
                                        .click();
                                });

                            cy.get('#collapse1')
                                .should('exist')
                                .within(() => {
                                    cy.get('li.status.status-error')
                                        .then(($emails) => {
                                            $emails.each((_, email) => {
                                                verifyEmailRecipient(email, true);
                                            });
                                        });
                                });
                        });
                    });
                });
            }).as('email-error');

        // Set an email as sent for testing purposes
        cy.get('@email-error').then(() => {
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
                    // Verify the server response is successful
                    expect(res.status).to.eq(200);
                    expect(res.body).to.have.property('status', 'success');
                    expect(res.body).to.have.property('data', null);

                    // Verify the email sent status is displayed after a full page reload
                    cy.reload().then(() => {
                        cy.contains('.button-container', uniqueSubject)
                            .closest('.status-container')
                            .within(() => {
                                verifyEmail(uniqueSubject, 'Submitty Administrator Email');

                                // btn-success (green) implies email sent
                                cy.get('button.status-btn.btn-success')
                                    .contains(/Show Details|Hide Details/)
                                    .click();
                            });

                        cy.get('#collapse1')
                            .should('exist')
                            .within(() => {
                                cy.get('li.status.status-success')
                                    .then(($emails) => {
                                        $emails.each((_, email) => {
                                            verifyEmailRecipient(email);
                                        });
                                    });
                            });
                    });
                });
            });
        });
    });
});

describe('Test cases involving instructor send email via thread announcement functionality', () => {
    it('sends an email via thread announcement and verifies the email is queued or sent', () => {
        cy.login('instructor');
        cy.visit(['sample', 'forum', 'threads', 'new']);

        const announcement = 'This is a test announcement';
        const content = 'This is a test content';

        // Enter the thread announcement details
        cy.get('[data-testid="title"]').type(announcement);
        cy.get('[data-testid="reply_box_1"]').type(content);

        // Pick an arbitrary category
        cy.get('[data-testid="categories-pick-list"] .cat-buttons').first().click({ force: true });

        // Ensure the announcement checkbox is checked
        cy.get('#Announcement').click();

        // Pin thread should automatically be checked based on the announcement checkbox selection
        cy.get('#pinThread').should('be.checked');

        // Submit the thread announcement
        cy.get('[data-testid="forum-publish-thread"]').click({ force: true });

        // Expect a redirection
        cy.url().should('not.include', '/sample/forum/threads/new').then(() => {
            cy.visit(['sample', 'email_status']).then(() => {
                // Verify the email was sent or is queued with the proper prefix
                const uniqueSubject = `New Announcement: ${announcement}`;
                cy.contains('.button-container', uniqueSubject)
                    .within(() => {
                        cy.get('button.status-btn')
                            .should('exist')
                            .and(($btn) => {
                                // Verify the email status is awaiting to be sent or has been sent
                                const classList = $btn[0].classList;
                                const isQueued = classList.contains('btn-primary');
                                const hasBeenSent = classList.contains('btn-success');
                                const hasError = classList.contains('btn-danger');
                                expect((isQueued || hasBeenSent) && !hasError).to.be.true;

                                // Return for further verification within the Cypress command chain
                                return $btn;
                            })
                            .closest('.status-container')
                            .within(() => {
                                verifyEmail(uniqueSubject, `Course: ${getCurrentSemester()} sample`);

                                cy.get('button.status-btn')
                                    .contains(/Show Details|Hide Details/)
                                    .click();
                            });
                    });

                cy.get('#collapse1')
                    .should('exist')
                    .within(() => {
                        cy.get('li.status')
                            .then(($emails) => {
                                $emails.each((_, email) => {
                                    verifyEmailRecipient(email);
                                });
                            });
                    });

                // Clean up the test thread
                cy.visit(['sample', 'forum', 'threads']).then(() => {
                    cy.get('[data-testid="thread-list-item"]').contains(announcement).click();
                    cy.get('[data-testid="thread-dropdown"]').first().click();
                    cy.get('[data-testid="delete-post-button"]').first().click({ force: true });
                });
            });
        });
    });
});

describe('Test cases involving instructor email pagination functionality', () => {
    it('verifies the email status page pagination', () => {
        // Superuser and instructor pagination share the same backend logic, so we can use the same test case
        cy.login('instructor');
        cy.visit(['sample', 'email_status']);

        let page = 1;
        let lastPage = -1;
        // The last DOM child represents the last page button (>>), so fetch the second to last child
        cy.get('.pagination').children().eq(-2).then(($lastPage) => {
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
                    console.log(response);
                    expect(response.status).to.eq('success');
                    cy.get('.status-container')
                        .should('have.length', response.data.length)
                        .then(($emails) => {
                            $emails.each((index, email) => {
                                const $email = Cypress.$(email);

                                expect($email.find('[data-testid="email-subject"]').text().trim()).to.equal(
                                    `Email Subject: ${response.data[index].subject}`,
                                );
                                expect($email.find('[data-testid="email-time-created"]').text().trim()).to.equal(
                                    `Time Created: ${response.data[index].created}`,
                                );
                                expect($email.find('[data-testid="email-source"]').text().trim()).to.equal(
                                    `Course: ${getCurrentSemester()} sample`,
                                );
                                // Total recipients for the current email drop-down
                                cy.get(`#collapse${index + 1} li`).should('have.length', response.data[index].count);
                            });
                        });

                    // Total email drop-downs on the page
                    cy.get('.expand').should('have.length', response.data.length);
                });

                page++;

                if (page > lastPage) {
                    return;
                }

                // Visit the next page via the next pagination button
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
