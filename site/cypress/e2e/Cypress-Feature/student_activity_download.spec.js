const header = '"Registration Section","User ID","Given Name","Family Name","Gradeable Access Date","Gradeable Submission Date","Forum View Date","Forum Post Date","Number of Poll Responses","Office Hours Queue Date","Course Materials Access Date"';
const row1 = '1,adamsg,Gretchen,Adams,,"9996-12-29 23:59:59-05",,,2,,';

describe('Tests cases revolving around student activity download', () => {
    it('Should download file, and have some correct values', () => {
        cy.login('instructor');

        cy.visit(['sample', 'activity']);

        cy.get('[data-testid="student-activity-download-csv"]').click();

        cy.readFile('cypress/downloads/Student_Activity.csv')
            .then((data) => {
                const rows = data.split('\n');

                expect(rows[0]).to.eql(header);
                expect(rows[1]).to.eql(row1);
            });
    });
});
