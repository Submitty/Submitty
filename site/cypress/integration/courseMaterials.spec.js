import {buildUrl, getCurrentSemester} from '../support/utils.js';

const coursePath = `${getCurrentSemester()}/sample/uploads/course_materials`;
const defaultFilePath = `/var/local/submitty/courses/${coursePath}`;
const downloadsFolder = Cypress.config('downloadsFolder');

describe('Test cases revolving around course material uploading and access control', () => {
	before(() => {
		cy.visit('/');
		cy.login();
		cy.visit(['sample', 'course_materials']);
	});

	describe('Test upload and viewing files' , () => {
		it('Should upload a file and be able to view and download it', () => {
			cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
			cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
			cy.get('#submit-materials').click();

			cy.get('.file-viewer').contains('file1.txt');
			
			const fileTgt = `${buildUrl(['sample', 'display_file'])}?dir=course_materials&path=${encodeURIComponent(defaultFilePath)}/file1.txt`;

			cy.visit(fileTgt);
			cy.get('pre').should('have.text', 'a\n');
			cy.visit(['sample', 'course_materials']);

			//a href tags should be for navigation only, workaround to prevent cypress from expecting a page change
			//https://github.com/cypress-io/cypress/issues/14857
			//TODO: handle download

			cy.get('.fa-trash').click();
			cy.get('.btn-danger').click();
			cy.get('.file-viewer').should('not.exist');
		});
	});


	describe('Test adding course material links', () => {
		it.only('Should allow uploading links', () => {
			cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
			cy.get('#url_selection_radio').click();
			cy.get('#url_title').type('Test URL');
			cy.get('#url_url').type(buildUrl(['sample', 'users'], true));
			cy.get('#submit-materials').click();
		})
	});
});
