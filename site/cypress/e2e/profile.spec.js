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

const makeid = () => {
    let result = '';
    const characters = 'abcdefghijklmnopqrstuvwxyz';
    let counter = 0;
    while (counter < 5) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
        counter += 1;
    }
    return result;
};

const given = makeid();
const family = makeid();
const pronouns = makeid();
var mail = makeid();
mail += '@rpi.edu';

describe('Test cases revolving around user profile page', () => {
    it('Should verify the basic info column\'s visibility', () => {
        cy.visit('/user_profile');
        cy.login();
        cy.get('#username-row').should('be.visible');
        cy.get('#givenname-row').should('be.visible');
        cy.get('#familyname-row').should('be.visible');
        cy.get('#pronouns-row').should('be.visible');
        cy.get('#email-row').should('be.visible');
        cy.get('#secondary-email-row').should('be.visible');
        cy.get('#secondary-email-notify-row').should('be.visible');
        // verify that every pop-up form's display originally is none
        cy.get('#edit-username-form').should('not.be.visible');
        cy.get('#edit-pronouns-form').should('not.be.visible');
        cy.get('#edit-secondary-email-form').should('not.be.visible');
        cy.get('#edit-secondary-email-form').should('not.be.visible');
    });

    //verify each input form
    it('Verify every pop-up form', () => {
        cy.visit('/user_profile');
        cy.login();

        // verify that every form can be intrigued
        cy.get('.fa-pencil-alt').should('have.length', 5);

        // verify prederred name form
        form_visible(1, 0);
        cy.get('input[name=user_givenname_change]').clear().type(given);
        cy.get('input[name=user_familyname_change]').clear().type(family);
        cy.get('.btn-primary').eq(1).click();
        cy.get('#givenname-row > button').contains(given);
        cy.get('#familyname-row > button').contains(family);

        // verify pronouns form
        form_visible(2, 3);
        cy.get('input[name=user_pronouns_change]').clear().type(pronouns);
        cy.get('.btn-primary').eq(4).click();
        cy.get('#pronouns_val').contains(pronouns);

        // verify secondary_email form
        form_visible(3, 2);
        cy.get('input[name=user_secondary_email_change]').clear().type(mail);
        cy.get('input[name=user_secondary_email_notify_change]').click();
        cy.get('.btn-primary').eq(3).click();
        cy.get('#secondary-email-row > button').contains(mail);
    });

    // verify database was updated
    it('verify that database was updated', () => {
        cy.visit('/user_profile');
        cy.login();
        cy.get('#givenname-row > button').contains(given);
        cy.get('#familyname-row > button').contains(family);
        cy.get('#pronouns_val').contains(pronouns);
        cy.get('#secondary-email-row > button').contains(mail);
    });
});
