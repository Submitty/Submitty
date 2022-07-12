import {buildUrl} from '../support/utils.js';

describe('Test cases revolving around course material uploading and access control', () => {
    beforeEach(() => {
        cy.visit(['sample', 'course_materials']);
        cy.login();
    });

    it('Should upload a file and be able to view and download it', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('.file-viewer').contains('file1.txt');

        const fileTgt = buildUrl(['sample', 'course_material', 'file1.txt']);

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

    it('Should support optional file locations', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('option1');
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('.file-viewer').contains('file1.txt');
        const fileTgt = buildUrl(['sample', 'course_material', 'option1', 'file1.txt']);

        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });

        const fpath = 'option1/1234/!@#$%^&*()';
        cy.get('#input-provide-full-path').type(fpath);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });
        cy.get('.file-viewer').should('have.length', 2);

        const fileTgt2 = buildUrl(['sample', 'course_material', 'option1', '1234', encodeURIComponent('!@#$%^&*()'), 'file1.txt']);
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
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get(`.file-viewer > [href="${buildUrl(['sample', 'users'],true)}"]`).click();
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
        cy.get('#input-provide-full-path').click();
        cy.get('#upload1').attachFile(['file1.txt', 'file2.txt'] , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#date_to_release_sf1').should('have.value', date);
        cy.get('#date_to_release_sf1').should('have.value', date);

        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-picker').clear().type('9998-01-01 00:00:00');
        cy.waitPageChange(() => {
            cy.get('#submit-edit').click({force: true}); //div covering button
        });
        cy.get('#date_to_release_sf1').should('have.value', '9998-01-01 00:00:00');

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer').should('have.length', 1);

        const fileTgt = buildUrl(['sample', 'course_material', 'file2.txt']);

        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'b\n');
        cy.visit(['sample', 'course_materials']);

        const fileTgt2 = buildUrl(['sample', 'course_material', 'file1.txt']);
        cy.visit(fileTgt2);

        cy.get('.content').contains('Reason: You may not access this file until it is released');

        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);

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
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('option1');
        cy.get('#upload1').attachFile('file2.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#hide-materials-checkbox').check();
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('not.exist');
        const fileTgt = buildUrl(['sample', 'course_material', 'file1.txt']);
        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        const fileTgt2 = buildUrl(['sample', 'course_material', 'option1', 'file2.txt']);
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

    it('Should upload and unzip zip files', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#expand-zip-checkbox').check();
        cy.get('#upload1').attachFile('zip.zip' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();
        cy.get('.file-viewer').should('have.length', 23);

        cy.get('#file-container .btn').eq(6).click();
        cy.get('#date_to_release').clear().type('2021-06-29 21:37:53');
        cy.waitPageChange(() => {
            cy.get('#submit_time').click();
        });

        for (let i = 0; i < 3; i++) {
            cy.get('[name="release_date"]').eq(i).should('have.value', '9998-01-01 00:00:00');
        }

        for (let i = 3; i < 6; i++) {
            cy.get('[name="release_date"]').eq(i).should('have.value', '2021-06-29 21:37:53');
        }

        for (let i = 6; i < 22; i++) {
            cy.get('[name="release_date"]').eq(i).should('have.value', '9998-01-01 00:00:00');
        }

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample','course_materials']);

        cy.get('.file-viewer').should('have.length', 3);

        const fileTgt = buildUrl(['sample', 'course_material', 'zip', '2', '3', '7', '9', '10', '10_1.txt']);
        cy.visit(fileTgt);
        cy.get('body').should('have.text','');

        const fileTgt2 = buildUrl(['sample', 'course_material', 'zip', '1_1.txt']);
        cy.visit(fileTgt2);
        cy.get('.content').contains('Reason: You may not access this file until it is released');

        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();
        cy.get('.file-viewer').should('not.exist');
    });

    it('Should restrict course materials by section', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#all_Sections_Showing_yes').click();
        cy.get('#upload1').attachFile(['file1.txt', 'file2.txt'] , { subjectType: 'drag-n-drop' });
        cy.get('#section-upload-1').check();
        cy.get('#upload_picker').clear().type('2021-06-29 21:37:53');
        cy.get('#input-provide-full-path').click();
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('.fa-pencil-alt').last().click();
        cy.get('#section-edit-2').check();
        cy.waitPageChange(() => {
            cy.get('#submit-edit').click();
        });

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 2);

        cy.logout();
        cy.login('browna');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 1);

        const fileTgt2 = buildUrl(['sample', 'course_material', 'file1.txt']);

        cy.visit(fileTgt2);
        cy.get('.content').contains('Reason: Your section may not access this file');

        cy.visit('/');
        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.fa-trash').click();
        cy.get('.btn-danger').click();
        cy.get('.file-viewer').should('not.exist');

    });

    it('Should not upload file when no section selected for restrict course materials', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#all_Sections_Showing_yes').click();
        cy.get('#upload1').attachFile(['file1.txt', 'file2.txt'] , { subjectType: 'drag-n-drop' });
        cy.get('#upload_picker').clear().type('2021-06-29 21:37:53');
        cy.get('#input-provide-full-path').click();
        cy.get('#submit-materials').click();
        cy.on('window:alert', (alert) => {
            expect(alert).eq('Select at least one section');
        });

        cy.reload();
        cy.get('.file-viewer').should('not.exist');
    });

    it('Should restrict course materials within folders', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#all_Sections_Showing_yes').click();
        cy.get('#upload1').attachFile('zip.zip' , { subjectType: 'drag-n-drop' });
        cy.get('#section-upload-1').check();
        cy.get('#upload_picker').clear().type('2021-06-29 21:37:53');
        cy.get('#expand-zip-checkbox').check();
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();
        cy.get('.fa-pencil-alt').eq(24).click();
        cy.get('#all-sections-showing-yes').click();
        cy.get('#section-edit-2').check();
        cy.waitPageChange(() => {
            cy.get('#submit-edit').click();
        });

        cy.logout();
        cy.login('browna');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 1);
        const fileTgt2 = buildUrl(['sample', 'course_material', 'zip', '1_1.txt']);
        cy.visit(fileTgt2);

        cy.get('.content').contains('Reason: Your section may not access this file');
        cy.visit('/');
        cy.logout();

        cy.login();
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();
    });

    it('Should sort course materials', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#upload_sort').clear().type('50000');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file2.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#upload_sort').clear().type('10');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file3.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#upload_sort').clear().type('5.5');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file4.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#upload_sort').clear().type('5.4');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file5.txt' , { subjectType: 'drag-n-drop' });
        cy.get('#upload_sort').clear().type('0');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });
        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();


        for (let i = 5; i > 0; i--) {
            cy.get(`:nth-child(${6-i}) > .file-viewer`).contains(`file${i}.txt` );
        }

        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();
    });

    it('Should sort course materials folders', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a/b1');
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a/b2');
        cy.get('#upload1').attachFile('file2.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();

        // Edit folder b1 sorting order
        cy.get('.fa-pencil-alt').eq(1).click();
        cy.get('#edit-folder-sort').clear().type('1');
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit').click();
        });

        // Confirm change to folder sorting order
        for (let i = 2; i > 0; i--) {
            cy.get('.fa-pencil-alt').eq((2 - i) * 2 + 1).click();
            cy.get('#edit-folder-sort').should('have.value', `${2 - i}`);
            cy.get('#edit-course-materials-folder-form > .popup-box > .popup-window > .form-title > .btn').click();
            cy.get(`#div_viewer_sd1d${3 - i} > .file-container > .file-viewer`).contains(`file${i}.txt`);
        }

        // Clean up files
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.file-viewer').should('not.exist');
    });

    it('Should release course materials in folder by date', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a/b');
        cy.get('#upload1').attachFile('file2.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();

        // Check that student cannot view unreleased files
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 0);

        cy.visit('/');
        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);

        // Set release date for files
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-folder-picker').clear().type('2021-06-29 21:37:53');
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click({force: true}); //div covering button
        });

        // Check if recursive updates were applied
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(3-i).click();
            cy.get('#edit-picker').should('have.value', '2021-06-29 21:37:53');
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Check that student cannot view the now released files
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 2);

        cy.logout();
        cy.login();

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.file-viewer').should('not.exist');
    });

    it('Should restrict course materials in folder', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a/b');
        cy.get('#upload1').attachFile('file2.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();

        // Restrict course materials in folder to section 1
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-folder-picker').clear().type('2021-06-29 21:37:53');
        cy.get('#all-sections-showing-yes-folder').click();
        cy.get('#section-folder-edit-1').check();
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click();
        });

        // Check if recursive updates were applied
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(3-i).click();
            cy.get('#all-sections-showing-yes').should('be.checked');
            cy.get('#section-edit-1').should('be.visible').should('be.checked');
            cy.get('#edit-picker').should('have.value', '2021-06-29 21:37:53');
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Check that a student in section 1 can view the files
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 2);

        // Check that a student not in section 1 cannot view the files
        cy.logout();
        cy.login('browna');
        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer').should('not.exist');

        const fileTgt = buildUrl(['sample', 'course_material', 'a', 'file1.txt']);

        cy.visit(fileTgt);
        cy.get('.content').contains('Reason: Your section may not access this file');

        cy.logout();
        cy.login();

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.file-viewer').should('not.exist');
    });

    it('Should hide course materials in folder visually', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#upload1').attachFile('file1.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a/b');
        cy.get('#upload1').attachFile('file2.txt' , { subjectType: 'drag-n-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();

        // Visually hide course materials in folder from students
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-folder-picker').clear().type('2021-06-29 21:37:53');
        cy.get('#hide-folder-materials-checkbox-edit').check();
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click();
        });

        // Check if recursive updates were applied
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(3-i).click();
            cy.get('#hide-materials-checkbox-edit').should('be.checked');
            cy.get('#edit-picker').should('have.value', '2021-06-29 21:37:53');
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Check that a student cannot access the files through Course Materials page
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer').should('not.exist');

        // Check that a student can view the files through a URL
        const fileTgt = buildUrl(['sample', 'course_material', 'a', 'file1.txt']);
        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        cy.logout();
        cy.login();

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.file-viewer').should('not.exist');
    });

    it('Should sort course materials folder and preserve uploaded links', () => {
        const test_url = buildUrl(['sample', 'users'], true);
        const link_titles = ['Test URL', 'Nested Test URL'];

        // Upload link 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#url_selection_radio').click();
        cy.get('#url_title').type(link_titles[0]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload link 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a/b');
        cy.get('#url_selection_radio').click();
        cy.get('#url_title').type(link_titles[1]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();

        // Edit folders' sorting order
        for (let i = 1; i >= 0; i--) {
            cy.get('.fa-pencil-alt').eq(i).click();
            cy.get('#edit-folder-sort').clear().type(i + 1);
            cy.waitPageChange(() => {
                cy.get('#submit-folder-edit').click();
            });
        }

        // Confirm that link data was preserved
        for (let i = 1; i < 4; i += 2) {
            cy.get('.fa-pencil-alt').eq(i).click();
            cy.get('#edit-url-title').should('have.value', link_titles[(i - 1) / 2]);
            cy.get('#edit-url-url').should('have.value', test_url);
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.file-viewer').should('not.exist');
    });

    it('Should apply recursive updates to course materials folder and preserve uploaded links', () => {
        const test_url = buildUrl(['sample', 'users'], true);
        const link_titles = ['Test URL', 'Nested Test URL'];

        // Upload link 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a');
        cy.get('#url_selection_radio').click();
        cy.get('#url_title').type(link_titles[0]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload link 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#input-provide-full-path').type('a/b');
        cy.get('#url_selection_radio').click();
        cy.get('#url_title').type(link_titles[1]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick=\'setCookie("foldersOpen",openAllDivForCourseMaterials());\']').click();

        // Perform a recursive updates
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#all-sections-showing-yes-folder').click();
        cy.get('#section-folder-edit-1').check();
        cy.get('#edit-folder-picker').clear().type('2021-06-29 21:37:53');
        cy.get('#hide-folder-materials-checkbox-edit').check();
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click({force: true}); //div covering button
        });

        // Confirm that link data was preserved
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(i + 2).click();
            cy.get('#edit-url-title').should('have.value', link_titles[1 - i]);
            cy.get('#edit-url-url').should('have.value', test_url);
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger').click();

        cy.get('.file-viewer').should('not.exist');
    });
});
