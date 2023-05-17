const form_visible = (button_id, form_id) => {
    cy.get('.fa-pencil-alt').eq(button_id).click();
    for (let i = 0; i < form_id; i++) {
        cy.get('.popup-form').eq(i).should('not.be.visible');
    }
    cy.get('.popup-form').eq(form_id).should('be.visible');
    for (let i = form_id + 1; i < 4; i++) {
        cy.get('.popup-form').eq(i).should('not.be.visible');
    }
};

const makeId = (length = 5) => {
    const characters = 'abcdefghijklmnopqrstuvwxyz';
    return Array(length).fill().map(() => characters[Math.floor(Math.random() * characters.length)]).join('');
};

const givenName = makeId();
const familyName = makeId();
const pronouns = makeId();
const email = `${makeId()}@example.com`;

describe('Test cases revolving around user profile page', () => {
    it('Should check the visibility of the rows and popups', () => {
        cy.visit('/user_profile');
        cy.login();

        // fields should be visible
        cy.get('#username-row').should('be.visible');
        cy.get('#givenname-row').should('be.visible');
        cy.get('#familyname-row').should('be.visible');
        cy.get('#pronouns-row').should('be.visible');
        cy.get('#email-row').should('be.visible');
        cy.get('#secondary-email-row').should('be.visible');
        cy.get('#secondary-email-notify-row').should('be.visible');

        // popups should be hidden
        cy.get('#edit-username-form').should('not.be.visible');
        cy.get('#edit-pronouns-form').should('not.be.visible');
        cy.get('#edit-secondary-email-form').should('not.be.visible');
        cy.get('#edit-secondary-email-form').should('not.be.visible');
    });

    it('Should test every pop-up form', () => {
        cy.visit('/user_profile');
        cy.login();

        // check for edit buttons that open forms
        cy.get('.fa-pencil-alt').should('have.length', 5);

        // preferred name form
        form_visible(1, 0);
        cy.get('input[name=user_givenname_change]').clear().type(givenName);
        cy.get('input[name=user_familyname_change]').clear().type(familyName);
        cy.get('.btn-primary').eq(1).click();
        cy.get('#givenname-row > button').contains(givenName);
        cy.get('#familyname-row > button').contains(familyName);

        // pronouns form
        form_visible(2, 3);
        cy.get('input[name=user_pronouns_change]').clear().type(pronouns);
        cy.get('.btn-primary').eq(4).click();
        cy.get('#pronouns_val').contains(pronouns);

        // secondary email form
        form_visible(3, 2);
        cy.get('input[name=user_secondary_email_change]').clear().type(email);
        cy.get('input[name=user_secondary_email_notify_change]').click();
        cy.get('.btn-primary').eq(3).click();
        cy.get('#secondary-email-row > button').contains(email);
    });

    it('Should confirm that the database was updated', () => {
        cy.visit('/user_profile');
        cy.login();

        cy.get('#givenname-row > button').contains(givenName);
        cy.get('#familyname-row > button').contains(familyName);
        cy.get('#pronouns_val').contains(pronouns);
        cy.get('#secondary-email-row > button').contains(email);
    });
});
