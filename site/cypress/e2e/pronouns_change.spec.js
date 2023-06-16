
describe('Tests cases abut changing user pronouns', () => {

    it('Tests changing pronouns for student', () => {
        cy.visit('/user_profile');
        cy.login('student');



        //edit pronouns
        cy.get('#pronouns_val').click()
        const e = cy.get('#user-pronouns-change');
        e.clear();
        e.type('They/Them');
        cy.get('#pronouns-submit').click();

    });

    it('Verifies changed pronouns as instructor in Manage Students', () => {
        cy.visit(['sample','users']);
        cy.login('instructor');

        //ensure pronouns column is on
        cy.get('#toggle-columns-btn').click()
        cy.get('#toggle-pronouns').check()
        cy.get('#toggle-columns-submit').click();

        //Ensure correctness
        cy.get('.td-pronouns:eq( 12 )').should('have.text', 'They/Them');

    });

    it('Verifies changed pronouns as instructor in Student Photos', () => {
        
        cy.visit(['sample','student_photos']);
        cy.login('instructor');

        //select proper place and ensure correctness
        const e = cy.get('.student-image-container > .name').first();

    });

    it('Changes pronouns back', () => {
        cy.visit('/user_profile');
        cy.login('student');



        //edit pronouns
        cy.get('#pronouns_val').click()
        const e = cy.get('#user-pronouns-change');
        e.clear();
        cy.get('#pronouns-submit').click();

    });

});