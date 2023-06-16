
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
        cy.get('#edit-pronouns-form .form-button-container > .btn-primary').first().click();

    });

    it('Verifies changed pronouns as instructor in Manage Students', () => {
        cy.visit(['sample','users']);
        cy.login('instructor');

        //ensure pronouns column is on
        cy.get('#toggle-columns-btn').click();
        cy.get('#toggle-pronouns').check();
        cy.get('#toggle-columns-form .form-button-container > .btn-primary').first().click();

        //Ensure correctness in table
        cy.get('.td-pronouns:eq( 12 )').should('have.text', 'They/Them');

    });

    it('Verifies changed pronouns as instructor in Student Photos', () => {
        
        cy.visit(['sample','student_photos']);
        cy.login('instructor');

        //select Joe Student's photo
        const e = cy.get('.student-image-container > .name').first();

        //Select text from photo area and parse to get pronoun
        e.invoke('text')
            .then(text => text.split('\n'))                 // split by newline
            .then(texts => texts.map(text => text.trim()))  // trim whitespace
            .then(texts => texts.filter(text => text))      // remove empty
            .then(texts => {
                //texts stores split text, should be [Joe Student, student, They/Them]
                expect(texts[2]).to.equal('They/Them');
            });

    });

    it('Changes pronouns back', () => {
        cy.visit('/user_profile');
        cy.login('student');

        //change back to old pronouns
        cy.get('#pronouns_val').click();
        const e = cy.get('#user-pronouns-change');
        cy.get('button[aria-label="Clear pronoun input"]').click(); //clear input using trash can
        if (oldPronouns !== '') e.type(oldPronouns);
        cy.get('#edit-pronouns-form .form-button-container > .btn-primary').first().click();

    });

});
