describe('Tests cases revolving around modifying gradeables', () => {
    beforeEach(() => {
        cy.visit('/');
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
    });
    
    it('Should test settings page 0-2', () => {
        cy.get('#no_ta_grade').click();
        cy.get('#discussion_grading_enable_container').should('not.be.visible');
        cy.get('#yes_ta_grade').click();
        cy.get('#discussion_grading_enable_container').should('be.visible');
        cy.get('#syllabus_bucket').should('exist');

        cy.get('#page_1_nav').click();
        cy.get('#no_student_view').click();
        cy.get('#student_download_view').should('not.be.visible');
        cy.get('#student_submit_view').should('not.be.visible');
        cy.get('#no_student_view_after_grades').click();

        cy.get('#yes_student_download').click();
        cy.get('#yes_student_submit').click();

        cy.get('#gradeable-lock').contains('Select prerequisite gradeable (Off)');

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

        // cy.get('#grade_by_count_down_118').click();
        cy.get('[value="Add New Mark"]').eq(-1).click();

        cy.get('[title="Delete this component"]').eq(-1).click().then(()=>{
            cy.on('window:confirm', (str) =>{
                expect(str).to.equal('Are you sure you want to delete this component?');
            });
        });
    });

    it('Should test settings page 3-5', ()=>{
        cy.get('#page_3_nav').click();
        cy.get('#all_access').click();
        cy.get('#registration_section').click();

        cy.get('#page_4_nav').click();
        cy.get('#peer_graders_list').should('exist');
        
        cy.get('#clear_peer_matrix').click().then(()=>{
            cy.on('window:confirm', () => false);
        });

        cy.get('#download_peer_csv').click();

        cy.get('#hidden_files').should('exist');
        cy.get('#add-peer-grader').click();
        cy.get('.form-button-container > a').click({force:true,multiple:true});

        cy.get('#page_5_nav').click();
        cy.get('#has_due_date_no').click();
        //Check for hidden elements
        cy.get('#has_due_date_yes').click();
        //check if the hidden elements are back

        cy.get('#has_release_date_yes').click();
        //check for hidden elements
        cy.get('#has_release_date_no').click();
        //check for the hidden elements are back

        cy.get('#no_late_submission').click();
        //check for hidden elements
        cy.get('#yes_late_submission').click();
        //check for elements
        //Goes back to general
        cy.get('#page_0_nav').click();
    });

    it('Should test the change of settings in student view', ()=> {
        cy.get('#page_1_nav').click();
        cy.get('#no_student_view').click();
        //student should not be able to see open_peer_homework

        cy.login('student');
        cy.visit('sample');
        cy.get('#open_peer_homework').should('not.exist');

        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
        cy.get('#page_1_nav').click();
        cy.get('#yes_student_view').click();

        cy.login('student');
        cy.visit(['sample','gradeable','open_peer_homework']);

        cy.get('#upload1').should('not.exist');
        cy.get('#submission-version-select').should('not.exist');
        cy.contains('Submissions are no longer being accepted for this assignment').should('exist');
        cy.contains('No submissions for this assignment.').should('exist');
        
        //Makes sure we can undo the setting change
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
        cy.get('#yes_student_download').click();
        cy.get('#yes_student_submit').click();

        cy.login('student');
        cy.visit(['sample','gradeable','open_peer_homework']);
        cy.get('#upload1').should('exist');
        cy.get('#submission-version-select').should('exist');
        cy.contains('Submissions are no longer being accepted for this assignment').should('not.exist');
        cy.contains('No submissions for this assignment.').should('not.exist');

    });
    
    it('Should test locking the gradeable',() =>{
        //testing 
        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
        cy.get('#page_1_nav').click();
        //Locking the gradeable
        cy.get('[value="grades_released_homework_autota"]').click();
        //unlocking the gradeable
        cy.get('[value=""]').click();
        //Relocks the gradeable
        cy.get('[value="grades_released_homework_autota"]').click();

        ['student','grader','ta'].forEach((user) => {
            cy.visit('/');
            cy.login(user);
            cy.get('[title="Please complete C Malloc Not Allowed first"]').should('exist');
            if (user === 'ta'){
                cy.get('[title="Please complete C Malloc Not Allowed first"]').click();
                cy.get('#upload1').should('exist');
            }else{
                cy.get('#upload1').should('not.exist');
            }
        });

        cy.login('instructor');
        cy.visit(['sample','gradeable','open_peer_homework','update']);
        cy.get('#page_1_nav').click();
        cy.get('[value=""]').click();
        
        ['student','grader','ta'].forEach((user) => {
            cy.login(user);
            cy.visit(['sample','gradeable','open_peer_homework']);
        });

    });
});
