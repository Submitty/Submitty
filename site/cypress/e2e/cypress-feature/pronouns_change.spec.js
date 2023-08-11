
describe('Tests cases abut changing user pronouns', () => {

    let oldPronouns = '';

    //Set the stage by saving old pronouns and setting a desired pronoun to look for
    before(() => {
        cy.visit('/user_profile');
        cy.login('student');

        //open pronouns form
        cy.get('#pronouns_val').click();
        cy.get('#user-pronouns-change').as('e');

        //save old pronouns
        cy.get('@e').then(($pronounsInput) => {
            oldPronouns = $pronounsInput.val();
        });

        //type in new pronouns
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can
        cy.get('@e').type('They/Them');
        cy.get('#edit-pronouns-submit').click();

        //ensure pronouns changed on page
        cy.get('#pronouns_val').contains('They/Them');

        cy.logout();

    });

    //restore pronouns to previous value at the end
    after(() => {
        cy.visit('/user_profile');
        cy.login('student');

        //change back to old pronouns
        cy.get('#pronouns_val').click();
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can
        if (oldPronouns !== '') {
            cy.get('#user-pronouns-change').type(oldPronouns);
        }
        cy.get('#edit-pronouns-submit').first().click();

        //ensure pronouns changed on page
        if (oldPronouns !== '') {
            cy.get('#pronouns_val').contains(oldPronouns);
        }
    });

    it('Verifies changed pronouns as instructor in Manage Students', () => {
        cy.visit(['sample','users']);
        cy.login('instructor');

        //ensure pronouns column is on
        cy.get('#toggle-columns').click(); //open toggle columns form
        cy.get('#toggle-pronouns').check();
        cy.get('#toggle-student-col-submit').first().click();

        //Ensure correctness in table
        cy.get('.td-pronouns:eq( 12 )').should('have.text', 'They/Them');

    });

    it('Verifies changed pronouns as instructor in Student Photos', () => {

        cy.visit(['sample','student_photos']);
        cy.login('instructor');

        //Select text from photo area and parse to get pronoun
        cy.get('.student-image-container > .name').first().contains('They/Them');

    });

});
