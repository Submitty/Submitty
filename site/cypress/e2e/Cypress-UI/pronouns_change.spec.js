
describe('Tests cases abut changing user pronouns', () => {

    let oldPronouns = '';
    const newPronouns = 'They/Them'; // Define constant for new pronouns

    //Set the stage by saving old pronouns and setting a desired pronoun to look for
    before(() => {
        cy.visit('/user_profile');
        cy.login('student');

        cy.get('#pronouns_val').as('pronounsVal').click(); // Alias for pronouns value
        cy.get('#user-pronouns-change').as('pronounsInput'); // Alias for pronouns input

        cy.get('@pronounsInput').then(($pronounsInput) => {
            oldPronouns = $pronounsInput.val();
        });

        cy.get('button[aria-label="Clear pronoun input"]').click();
        cy.get('@pronounsInput').type(newPronouns);
        cy.get('#edit-pronouns-submit').click();

        cy.get('@pronounsVal').contains(newPronouns);

        cy.logout();
    });

    //restore pronouns to previous value at the end
    after(() => {
        cy.visit('/user_profile');
        cy.login('student');

        //change back to old pronouns
        cy.get('#pronouns_val').as('pronounsVal').click();
        cy.get('#user-pronouns-change').as('pronounsInput');
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can
        if (oldPronouns !== '') {
            cy.get('@pronounsInput').type(oldPronouns);
        }
        cy.get('#edit-pronouns-submit').first().click();

        //ensure pronouns changed on page
        if (oldPronouns !== '') {
            cy.get('@pronounsVal').contains(oldPronouns);
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
        cy.get('.td-pronouns:eq( 12 )').should('have.text', newPronouns);

    });

    it('Verifies changed pronouns as instructor in Student Photos', () => {

        cy.visit(['sample','student_photos']);
        cy.login('instructor');

        //Select text from photo area and parse to get pronoun
        cy.get('.student-image-container > .name').first().contains(newPronouns);

    });

});
