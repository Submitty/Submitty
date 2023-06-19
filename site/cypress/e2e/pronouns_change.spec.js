
describe('Tests cases abut changing user pronouns', () => {

    let oldPronouns = '';

    it('Tests changing pronouns for student', () => {
        cy.visit('/user_profile');
        cy.login('student');

        //open pronouns form
        cy.get('#pronouns_val').click();
        const e = cy.get('#user-pronouns-change');

        //save old pronouns
        e.then(($pronounsInput) => {
            oldPronouns = $pronounsInput.val();
        });

        //type in new pronouns
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can
        e.type('They/Them');
        cy.get('#submit').click();

    });

    it('Verifies changed pronouns as instructor in Manage Students', () => {
        cy.visit(['sample','users']);
        cy.login('instructor');

        //ensure pronouns column is on
        cy.get('#toggle-columns').click(); //open toggle columns form
        cy.get('#toggle-pronouns').check();
        cy.get('#submit').first().click();

        //Ensure correctness in table
        cy.get('.td-pronouns:eq( 12 )').should('have.text', 'They/Them');

    });

    it('Verifies changed pronouns as instructor in Student Photos', () => {

        cy.visit(['sample','student_photos']);
        cy.login('instructor');

        //select Joe Student's photo
        const e = cy.get('.student-image-container > .name').first();

        //Select text from photo area and parse to get pronoun
        e.contains('They/Them');

    });

    it('Changes pronouns back', () => {
        cy.visit('/user_profile');
        cy.login('student');

        //change back to old pronouns
        cy.get('#pronouns_val').click();
        const e = cy.get('#user-pronouns-change');
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can
        if (oldPronouns !== '') {
            e.type(oldPronouns);
        }
        cy.get('#submit').first().click();

    });

});
