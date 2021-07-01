import {buildUrl, getCurrentSemester} from '../support/utils.js';

const coursePath = `${getCurrentSemester()}/sample/uploads/course_materials`;
const defaultFilePath = `/var/local/submitty/courses/${coursePath}`;

describe('Test cases revolving around course material uploading and access control', () => {
    before(() => {
        cy.visit('/');
        cy.login();
        cy.visit(['sample', 'course_materials']);
    });

    // afterEach(() => {
    // 	cy.reload();
    // 	cy.logout();
    // 	cy.login();
    // 	cy.visit(['sample', 'course_materials']);
    // });

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

    it('Support optional file locations', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('option1');
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#submit-materials').click();

        cy.get('.file-viewer').contains('file1.txt');
        const fileTgt = `${buildUrl(['sample', 'display_file'])}?dir=course_materials&path=${encodeURIComponent(defaultFilePath)}/option1/file1.txt`;

        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });

        const fpath = 'option1/1234/!@#$%^&*()/';
        cy.get('#input-provide-full-path').type(fpath);
        cy.get('#submit-materials').click();
        cy.get('.file-viewer').should('have.length', 2);

        const fileTgt2 = `${buildUrl(['sample', 'display_file'])}?dir=course_materials&path=${encodeURIComponent(defaultFilePath)}/${encodeURIComponent(fpath)}/file1.txt`;
        cy.visit(fileTgt2);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        cy.get('.div-viewer .fa-trash').first().click();
        cy.get('.btn-danger').click();
    });

    it('Should allow uploading links', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#url_selection_radio').click();
        cy.get('#url_title').type('Test URL');
        cy.get('#url_url').type(buildUrl(['sample', 'users'], true));
        cy.get('#submit-materials').click();

        cy.get('.file-viewer > [href="http://localhost:1511/courses/s21/sample/users"]').click();
        cy.location().should((loc) => {
            expect(loc.href).to.eq(buildUrl(['sample', 'users'],true));
        });

        cy.visit(['sample', 'course_materials']);
        cy.get('.key_to_click > .fa-trash').click();
        cy.get('.btn-danger').click();
        cy.get('.file-viewer').should('not.exist');
    });

    it('Should release course materials by date', () => {
        const date = '2021-06-29 21:37:53';
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload_picker').clear().type(date);
        cy.get('#upload1').attachFile(['file1.txt', 'file2.txt'] , { subjectType: 'drag-n-drop' });
        cy.get('#submit-materials').click();

        cy.get('#date_to_release_sd1f1').should('have.value', date);
        cy.get('#date_to_release_sd1f1').should('have.value', date);

        cy.reload(); //dom elements become detatched after uploading?

        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-picker').clear().type('9998-01-01 00:00:00');
        cy.get('#submit-edit').click({force: true}); //div covering button
        cy.get('#date_to_release_sd1f1').should('have.value', '9998-01-01 00:00:00');

        cy.reload();

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer').should('have.length', 1);

        const fileTgt = `${buildUrl(['sample', 'display_file'])}?dir=course_materials&path=${encodeURIComponent(defaultFilePath)}/file2.txt`;

        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'b\n');
        cy.visit(['sample', 'course_materials']);

        const fileTgt2 = `${buildUrl(['sample', 'display_file'])}?dir=course_materials&path=${encodeURIComponent(defaultFilePath)}/file1.txt`;
        cy.visit(fileTgt2);
        cy.get('.content').contains('Reason: You may not access this file until it is released');

        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);
        cy.reload();

        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.fa-trash').click();
        cy.get('.btn-danger').click();
    });

    it('Should hide course materials visually', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#upload_picker').clear().type('2021-06-29 21:37:53');
        cy.get('#hide-materials-checkbox').check();
        cy.get('#submit-materials').click();

        cy.reload();

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('option1');
        cy.get('#upload1').attachFile('file2.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#hide-materials-checkbox').check();
        cy.get('#submit-materials').click();

        cy.reload();

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('not.exist');
        const fileTgt = `${buildUrl(['sample', 'display_file'])}?dir=course_materials&path=${encodeURIComponent(defaultFilePath)}/file1.txt`;
        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        const fileTgt2 = `${buildUrl(['sample', 'display_file'])}?dir=course_materials&path=${encodeURIComponent(defaultFilePath)}/option1/file2.txt`;
        cy.visit(fileTgt2);
        cy.get('.content').contains('Reason: You may not access this file until it is released');

        cy.logout();
        cy.login();
        cy.visit(['sample', 'course_materials']);

        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();
        cy.get('.file-viewer').should('not.exist');
    });

    it('should upload and unzip zip files', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#expand-zip-checkbox').check();
        cy.get('#upload1').attachFile('zip.zip' , { subjectType: 'drag-n-drop' });
        cy.get('#submit-materials').click();

        cy.reload();
        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();
        cy.get('.file-viewer').should('have.length', 23);

        cy.get('#file-container .btn').eq(9).click();
        cy.get('#date_to_release').clear().type('2021-06-29 21:37:53');
        cy.get('#submit_time').click();

        cy.reload();

        for (let i = 0; i < 17; i++){
            cy.get('[name="release_date"]').eq(i).should('have.value', '9998-01-01 00:00:00');
        }

        for (let i = 17; i < 22; i++){
            cy.get('[name="release_date"]').eq(i).should('have.value', '2021-06-29 21:37:53');
        }
    });
});
