const getCurrentTime = () => {
	const now = new Date();
	now.setSeconds(now.getSeconds() + 30);
	const year = now.getFullYear();
	const month = String(now.getMonth() + 1).padStart(2, '0');
	const day = String(now.getDate()).padStart(2, '0');
	const hours = String(now.getHours()).padStart(2, '0');
	const minutes = String(now.getMinutes()).padStart(2, '0');
	const seconds = String(now.getSeconds()).padStart(2, '0');
	const time = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
	return time;
};

//All test cases:
// 1- Make sure submission before 
// 0 allowed 0 late days, submi
describe('juste test for now', () => {
	
	it('Create Gradeable for testing', () => {
	/*	cy.login('instructor');
        cy.visit(['sample', 'gradeable']);
        // Enter gradeable info
       	cy.get('#g_title').type('Warning Messages');
        cy.get('#g_id').type('warning_messages');
        cy.get('#radio_ef_student_upload').check().click();
        // Create Gradeable
        cy.get('#create-gradeable-btn').click();
        //date page
        cy.get('#page_5_nav').should('exist').click();
        //
        const BetaTestingDate = '1992-06-15';
        const SubmissionOpenDate = '2004-12-18';
        const DueDate = getCurrentTime();
        cy.get('#date_ta_view')
            .should('exist')
    		.clear()
    		.type(BetaTestingDate)
    		.click();
    	cy.get('#late_days').click(); // Dismiss calender and trigger save
    	cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');
    	
    	cy.get('#date_submit')
    	    .should('exist')
    		.clear()
    		.type(SubmissionOpenDate)
       	cy.get('#late_days').click(); // Dismiss calender and trigger save
    	cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');

        cy.get('#date_due')
    	    .should('exist')
            .clear()
            .type(DueDate)
            .click();
        cy.get('#late_days').click(); // Dismiss calender and trigger save
        cy.get('#save_status', {timeout:10000}).should('have.text', 'All Changes Saved');
        */

        cy.login('student');
        cy.visit(['sample', 'gradeable', 'warning_messages']);
        
        const testfile = 'cypress/fixtures/file1.txt';
        //cy.wait(30000);
        // Make a new submission
        cy.get('#upload1').selectFile(testfile,{action: 'drag-drop'});
        cy.waitPageChange(() => {
            cy.get('#submit').click();
    let messageText;
    cy.get('#submission-message').invoke('text').then((text) => {
        messageText = text;
        console.error('Submission Message:', messageText); // Log the message
        cy.wait(30000);
    });
        });

        cy.get('#submitted-files > div').should('contain', 'file1.txt');

	});
	});

/*
it('Deletes a gradeable', () => {
            cy.visit(['sample']);
            cy.get('#deleteme > div > a.fa-trash').click();

            // Confirm delete
            cy.get('form[name="delete-confirmation"]')
                .find('input')
                .contains('Delete')
                .click();

            // Check that cache is deleted
            cy.visit(['sample', 'bulk_late_days']);
            cy.get('#late-day-table > tbody > tr > [header_id="Delete Me"]').should('have.length', 0);

        });
*/