describe('Tests cases revolving around modifying gradeables', () => {
    beforeEach(() => {
        cy.visit('/');
        cy.login('instructor');
    });
    it('Should make sure edit gradeables does not contain errors', () => {
        cy.visit(['sample','gradeable','open_peer_homework','update']);

        cy.get('#no_ta_grade').click();
        cy.get('#discussion_grading_enable_container').should('not.be.visible');
        cy.get('#yes_ta_grade').click();
        cy.get('#discussion_grading_enable_container').should('be.visible');
        cy.get('#syllabus_bucket').should('exist');

        cy.get('#page_1_nav').click();
        cy.get('#no_student_view').click();
        cy.get('#student_download_view').should('not.be.visible');
        cy.get('#student_submit_view').should('not.be.visible');
        cy.get('#yes_student_download').click();
        cy.get('#yes_student_submit').click();

        cy.get('#gradeable-lock').contain('Select prerequisite gradeable (Off)');

        //Change to button when the link changes to button
        cy.get('.settings > a').click();
        cy.get('.content > a').click();
        cy.get('#rebuild-log-button').click();

        cy.get('#page_2_nav').click();
        cy.get('#point_precision_id');
        cy.get('#no_custom_marks').click();
        cy.get('#yes_custom_marks').click();

        cy.get('#yes_pdf_page').click();
        cy.get('#pdf_page').should('be.visible');
        cy.get('#no_pdf_page').click();
        cy.get('#pdf_page').should('not.be.visible');
        cy.get('[value="Add New Component"]').click();

        cy.get('#grade_by_count_down_120').click();
        cy.get('[value="Add New Mark"]').click();

        cy.get('[title="Delete this component"]').click();
        cy.on('window:confirm', (str) =>{
            expect(str).to.equal('Are you sure you want to delete this component?');
        });
        cy.on('window:confirm', () => true);

        cy.get('#page_3_nav').click();
        cy.get('#all_access').click();
        cy.get('#registration_section').click();

        cy.get('#page_4_nav').click();
        cy.get('#peer_graders_list').should('exist');
        
        cy.get('#clear_peer_matrix').click();

        cy.on('window:confirm', (str) =>{
            expect(str).to.equal('Each student grades every other student! Continue?');
        });
        cy.on('window:confirm', () => false);

        cy.on('window:confirm', (str) =>{
            expect(str).to.equal('This will update peer matrix. Are you sure?');
        });
        cy.on('window:confirm', () => false);

        cy.get('#download_peer_csv').click();

        const newFile = after.filter(file => !before.includes(file))[0]
        cy.readFile('cypress/downloads/' + newFile);
        cy.get('#hidden_files').should('exist');
        cy.get('#add-peer-grader').click();
        cy.get('.form-button-container > a').click();

        cy.get('#has_due_date_no').click();

        cy.get('#has_due_date_yes').click();

        cy.get('has_release_date_no').click();
        cy.get('has_release_date_yes').click();

        cy.get('#no_late_submission').click();
        cy.get('#yes_late_submission').click();

        cy.get('#page_5_nav').click();

        //Goes back to general
        cy.get('#page_0_nav').click();
    });

    it('Should verify changed settings in edit gradeables', ()=> {

    });
});
