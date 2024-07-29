import { buildUrl } from '../../support/utils.js';

describe('Test cases revolving around course material uploading and access control', () => {
    beforeEach(() => {
        cy.visit(['sample', 'course_materials']);
        cy.login();
    });

    it('Should upload a file and be able to view and download it', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('.file-viewer').contains('file1.txt');

        const fileTgt = buildUrl(['sample', 'course_material', 'file1.txt']);

        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        // a href tags should be for navigation only, workaround to prevent cypress from expecting a page change
        // https://github.com/cypress-io/cypress/issues/14857
        // TODO: handle download

        cy.get('.file-viewer > a').contains('file1.txt').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
        cy.get('.file-viewer').contains('file1.txt').should('not.exist');
    });

    it('Should support optional file locations', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('option1{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('.file-viewer').contains('file1.txt');
        const fileTgt = buildUrl(['sample', 'course_material', 'option1', 'file1.txt']);

        cy.visit(fileTgt);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });

        const fpath = 'option1/1234/!@#$%^&*()';
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type(`${fpath}{enter}`);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });
        cy.get('.file-viewer').should('have.length', 8);

        const fileTgt2 = buildUrl(['sample', 'course_material', 'option1', '1234', encodeURIComponent('!@#$%^&*()'), 'file1.txt']);
        cy.visit(fileTgt2);
        cy.get('pre').should('have.text', 'a\n');
        cy.visit(['sample', 'course_materials']);

        cy.get('.div-viewer > [id^=option1]').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
    });

    it('Should allow uploading links', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#url_selection_radio').click();
        cy.get('#title').type('Test URL');
        cy.get('#url_url').type(buildUrl(['sample', 'users'], true));
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get(`.file-viewer > [href="${buildUrl(['sample', 'users'], true)}"]`).click();
        cy.location().should((loc) => {
            expect(loc.href).to.eq(buildUrl(['sample', 'users'], true));
        });

        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer > a').contains('Test URL').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
        cy.get('.file-viewer > a').contains('Test URL').should('not.exist');
    });

    it('Should release course materials by date', () => {
        const date = '2021-06-29 21:37:53';
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload_picker').clear({ force: true });
        cy.get('#upload_picker').type(date);
        cy.get('#upload1').selectFile(['cypress/fixtures/file1.txt', 'cypress/fixtures/file2.txt'], { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#date_to_release_sf3').should('have.value', date);
        cy.get('#date_to_release_sf4').should('have.value', date);

        cy.get('.file-viewer > a').contains('file1.txt').parent().find('.fa-pencil-alt').click();
        cy.get('#edit-picker').clear({ force: true });
        cy.get('#edit-picker').type('9998-01-01 00:00:00', { force: true });
        cy.waitPageChange(() => {
            cy.get('#submit-edit').click({ force: true }); // div covering button
        });
        cy.get('.file-viewer > a').contains('file1.txt').parent().find('[id^=date_to_release_sf]').should('have.value', '9998-01-01 00:00:00');

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer').should('have.length', 7);

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

        cy.get('.file-viewer > a').contains('file1.txt').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer > a').contains('file2.txt').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
    });

    it('Should hide course materials visually', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();

        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.get('#upload_picker').clear();
        cy.get('#upload_picker').type('2021-06-29 21:37:53');

        cy.get('#hide-materials-checkbox').check();
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('option1{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', { action: 'drag-drop' });
        cy.get('#hide-materials-checkbox').check();
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 6);
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

        cy.get('.file-viewer > a').contains('file1.txt').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();

        cy.get('.div-viewer > a').contains('option1').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should upload and unzip zip files', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#expand-zip-checkbox').check();
        cy.get('#upload1').selectFile('cypress/fixtures/zip.zip', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();
        cy.get('.file-viewer').should('have.length', 29);

        cy.get('#file-container .btn').eq(9).click();
        cy.get('#date_to_release').clear({ force: true });
        cy.get('#date_to_release').type('2021-06-29 21:37:53', { force: true });
        cy.waitPageChange(() => {
            cy.get('#submit_time').click();
        });

        for (let i = 0; i < 3; i++) {
            cy.get('a[id="zip"]').parent().parent().find('[name="release_date"]').eq(i).should('have.value', '9998-01-01 00:00:00');
        }

        for (let i = 3; i < 6; i++) {
            cy.get('a[id="zip"]').parent().parent().find('[name="release_date"]').eq(i).should('have.value', '2021-06-29 21:37:53');
        }

        for (let i = 6; i < 22; i++) {
            cy.get('a[id="zip"]').parent().parent().find('[name="release_date"]').eq(i).should('have.value', '9998-01-01 00:00:00');
        }

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 9);

        const fileTgt = buildUrl(['sample', 'course_material', 'zip', '2', '3', '7', '9', '10', '10_1.txt']);
        cy.visit(fileTgt);
        cy.get('body').should('have.text', '');

        const fileTgt2 = buildUrl(['sample', 'course_material', 'zip', '1_1.txt']);
        cy.visit(fileTgt2);
        cy.get('.content').contains('Reason: You may not access this file until it is released');

        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);
        cy.get('a[id=zip]').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
        cy.get('a[id=zip]').should('not.exist');
    });

    it('Should restrict course materials by section', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#all_Sections_Showing_yes').click();
        cy.get('#upload1').selectFile(['cypress/fixtures/file1.txt', 'cypress/fixtures/file2.txt'], { action: 'drag-drop' });
        cy.get('#section-upload-1').check();
        cy.get('#upload_picker').clear({ force: true });
        cy.get('#upload_picker').type('2021-06-29 21:37:53', { force: true });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('.file-viewer > a').contains('file2.txt').parent().find('.fa-pencil-alt').click();
        cy.get('#section-edit-2').check();
        cy.waitPageChange(() => {
            cy.get('#submit-edit').click();
        });

        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 8);

        cy.logout();
        cy.login('browna');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 7);

        const fileTgt2 = buildUrl(['sample', 'course_material', 'file1.txt']);

        cy.visit(fileTgt2);
        cy.get('.content').contains('Reason: Your section may not access this file');

        cy.visit('/');
        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer > a').contains('file1.txt').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer > a').contains('file2.txt').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should not upload file when no section selected for restrict course materials', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#all_Sections_Showing_yes').click();

        cy.get('#upload1').selectFile(['cypress/fixtures/file1.txt', 'cypress/fixtures/file2.txt'], { action: 'drag-drop' });
        cy.get('#upload_picker').clear({ force: true });
        cy.get('#upload_picker').type('2021-06-29 21:37:53', { force: true });

        cy.get('#submit-materials').click();
        cy.on('window:alert', (alert) => {
            expect(alert).eq('Select at least one section');
        });

        cy.reload();
        cy.get('.file-viewer > a').contains('file1.txt').should('not.exist');
        cy.get('.file-viewer > a').contains('file2.txt').should('not.exist');
        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should restrict course materials within folders', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#all_Sections_Showing_yes').click();
        cy.get('#upload1').selectFile('cypress/fixtures/zip.zip', { action: 'drag-drop' });
        cy.get('#section-upload-1').check();
        cy.get('#upload_picker').clear({ force: true });
        cy.get('#upload_picker').type('2021-06-29 21:37:53', { force: true });
        cy.get('#expand-zip-checkbox').check();
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();
        cy.get('a[id=zip]').parent().parent().find('.fa-pencil-alt').eq(24).click();
        cy.get('#all-sections-showing-yes').click();
        cy.get('#section-edit-2').check();
        cy.waitPageChange(() => {
            cy.get('#submit-edit').click();
        });

        cy.logout();
        cy.login('browna');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 7);
        const fileTgt2 = buildUrl(['sample', 'course_material', 'zip', '1_1.txt']);
        cy.visit(fileTgt2);

        cy.get('.content').contains('Reason: Your section may not access this file');
        cy.visit('/');
        cy.logout();

        cy.login();
        cy.visit(['sample', 'course_materials']);
        cy.get('a[id=zip]').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
    });

    it('Should sort course materials', () => {
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.get('#upload_sort').clear();
        cy.get('#upload_sort').type('50000');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', { action: 'drag-drop' });
        cy.get('#upload_sort').clear();
        cy.get('#upload_sort').type('10');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file3.txt', { action: 'drag-drop' });
        cy.get('#upload_sort').clear();
        cy.get('#upload_sort').type('5.5');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file4.txt', { action: 'drag-drop' });
        cy.get('#upload_sort').clear();
        cy.get('#upload_sort').type('5.4');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file5.txt', { action: 'drag-drop' });
        cy.get('#upload_sort').clear();
        cy.get('#upload_sort').type('0');
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });
        cy.get('#cm-toggle-folders-btn').click();

        for (let i = 5; i > 0; i--) {
            cy.get(`.folder-container:nth-child(4) :nth-child(${6 - i}) > .file-viewer`).contains(`file${i}.txt`);
        }
        cy.get('a[id=a]').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
    });

    it('Should sort course materials folders', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a/b1{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a/b2{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();

        // Edit folder b1 sorting order
        cy.get('.fa-pencil-alt').eq(1).click();
        cy.get('#edit-folder-sort').clear();
        cy.get('#edit-folder-sort').type('1');
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
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should release course materials in folder by date', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a/b{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();

        // Check that student cannot view unreleased files
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 6);

        cy.visit('/');
        cy.logout();
        cy.login();

        cy.visit(['sample', 'course_materials']);

        // Set release date for files
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-folder-picker').clear({ force: true });
        cy.get('#edit-folder-picker').type('2021-06-29 21:37:53', { force: true });
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click({ force: true }); // div covering button
        });

        // Check if recursive updates were applied
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(3 - i).click();
            cy.get('#edit-picker').should('have.value', '2021-06-29 21:37:53');
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Check that student cannot view the now released files
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 8);

        cy.logout();
        cy.login();

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should restrict course materials in folder', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a/b{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();

        // Restrict course materials in folder to section 1
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-folder-picker').clear({ force: true });
        cy.get('#edit-folder-picker').type('2021-06-29 21:37:53', { force: true });
        cy.get('#all-sections-showing-yes-folder').click();
        cy.get('#section-folder-edit-1').check();
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click();
        });

        // Check if recursive updates were applied
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(3 - i).click();
            cy.get('#all-sections-showing-yes').should('be.checked');
            cy.get('#section-edit-1').should('be.visible').should('be.checked');
            cy.get('#edit-picker').should('have.value', '2021-06-29 21:37:53');
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Check that a student in section 1 can view the files
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);

        cy.get('.file-viewer').should('have.length', 8);

        // Check that a student not in section 1 cannot view the files
        cy.logout();
        cy.login('browna');
        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer').should('have.length', 6);

        const fileTgt = buildUrl(['sample', 'course_material', 'a', 'file1.txt']);

        cy.visit(fileTgt);
        cy.get('.content').contains('Reason: Your section may not access this file');

        cy.logout();
        cy.login();

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('.fa-trash').first().click();
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should hide course materials in folder visually', () => {
        // Upload file 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file1.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload file 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a/b{enter}');
        cy.get('#upload1').selectFile('cypress/fixtures/file2.txt', { action: 'drag-drop' });
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();

        // Visually hide course materials in folder from students
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#edit-folder-picker').clear({ force: true });
        cy.get('#edit-folder-picker').type('2021-06-29 21:37:53', { force: true });
        cy.get('#hide-folder-materials-checkbox-edit').check();
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click();
        });

        // Check if recursive updates were applied
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(3 - i).click();
            cy.get('#hide-materials-checkbox-edit').should('be.checked');
            cy.get('#edit-picker').should('have.value', '2021-06-29 21:37:53');
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Check that a student cannot access the files through Course Materials page
        cy.logout();
        cy.login('aphacker');
        cy.visit(['sample', 'course_materials']);
        cy.get('.file-viewer').should('have.length', 6);

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
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should show overwrite popup when a material with the same name already exists', () => {
        // overwriting a file
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload_picker').clear();
        cy.get('#upload_picker').type('2022-01-01 00:00:00');
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('words{enter}');
        cy.get('#upload1').attachFile('words_249.pdf', { subjectType: 'drag-n-drop' });
        cy.get('#submit-materials').click();
        cy.get('#overwrite-confirmation', { timeout: 10000 }).should('be.visible');
        cy.get('#existing-names').should('have.length', 1);
        cy.waitPageChange(() => {
            cy.get('#overwrite-submit').click();
        });

        // url title change
        const sample_url = 'https://www.submitty.org';
        const link_titles = ['Submitty-1', 'Submitty-2'];
        for (let i = 0; i <= 1; i++) {
            cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
            cy.get('#url_selection_radio').click();
            cy.get('#title').type(link_titles[i]);
            cy.get('#url_url').type(sample_url);
            cy.waitPageChange(() => {
                cy.get('#submit-materials').click();
            });
        }
        cy.get('.file-viewer > a').contains(link_titles[1]).parent().find('.fa-pencil-alt').click();
        cy.get('#edit-title').clear();
        cy.get('#edit-title').type(link_titles[0]);
        cy.get('#submit-edit').click();
        cy.get('#overwrite-confirmation', { timeout: 10000 }).should('be.visible');
        cy.get('#existing-names').should('have.length', 1);
        cy.waitPageChange(() => {
            cy.get('#overwrite-submit').click();
        });
        cy.get('.file-viewer > a').contains(link_titles[1]).should('not.exist');
        cy.get('.file-viewer > a').contains(link_titles[0]).parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();
    });

    it('Should sort course materials folder and preserve uploaded links', () => {
        const test_url = buildUrl(['sample', 'users'], true);
        const link_titles = ['Test URL', 'Nested Test URL'];

        // Upload link 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#url_selection_radio').click();
        cy.get('#title').type(link_titles[0]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload link 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a/b{enter}');
        cy.get('#url_selection_radio').click();
        cy.get('#title').type(link_titles[1]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();

        // Edit folders' sorting order
        for (let i = 1; i >= 0; i--) {
            cy.get('.fa-pencil-alt').eq(i).click();
            cy.get('#edit-folder-sort').clear();
            cy.get('#edit-folder-sort').type(i + 1);
            cy.waitPageChange(() => {
                cy.get('#submit-folder-edit').click();
            });
        }

        // Confirm that link data was preserved
        for (let i = -1; i >= -3; i -= 2) {
            cy.get('.fa-pencil-alt').eq(i).click();
            cy.get('#edit-title').should('have.value', link_titles[(i + 3) / 2]);
            cy.get('#edit-url-url').should('have.value', test_url);
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('a[id="a"]').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should apply recursive updates to course materials folder and preserve uploaded links', () => {
        const test_url = buildUrl(['sample', 'users'], true);
        const link_titles = ['Test URL', 'Nested Test URL'];

        // Upload link 1
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a{enter}');
        cy.get('#url_selection_radio').click();
        cy.get('#title').type(link_titles[0]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        // Upload link 2
        cy.get('[onclick="newUploadCourseMaterialsForm()"]').click();
        cy.get('#upload-location-drop-down').click();
        cy.get('.select2-search__field').type('a/b{enter}');
        cy.get('#url_selection_radio').click();
        cy.get('#title').type(link_titles[1]);
        cy.get('#url_url').type(test_url);
        cy.waitPageChange(() => {
            cy.get('#submit-materials').click();
        });

        cy.get('#cm-toggle-folders-btn').click();

        // Perform recursive updates
        cy.get('.fa-pencil-alt').first().click();
        cy.get('#all-sections-showing-yes-folder').click();
        cy.get('#section-folder-edit-1').check();
        cy.get('#edit-folder-picker').clear({ force: true });
        cy.get('#edit-folder-picker').type('2021-06-29 21:37:53', { force: true });
        cy.get('#hide-folder-materials-checkbox-edit').check();
        cy.waitPageChange(() => {
            cy.get('#submit-folder-edit-full').click({ force: true }); // div covering button
        });

        // Confirm that link data was preserved
        for (let i = 0; i < 2; i++) {
            cy.get('.fa-pencil-alt').eq(i + 2).click();
            cy.get('#edit-title').should('have.value', link_titles[1 - i]);
            cy.get('#edit-url-url').should('have.value', test_url);
            cy.get('#edit-course-materials-form > .popup-box > .popup-window > .form-title > .btn').click();
        }

        // Clean up files
        cy.visit(['sample', 'course_materials']);
        cy.get('a[id="a"]').parent().find('.fa-trash').click();
        cy.get('.btn-danger:visible').click();

        cy.get('.file-viewer').should('have.length', 6);
    });

    it('Should show partially and fully restricted sections correctly', () => {
        cy.get('#cm-toggle-folders-btn').click();
        cy.get('#div_viewer_sd1d1 > .file-container > .file-viewer > a[onclick^=newEditCourseMaterialsForm]').eq(0).click();
        cy.get('input[id=all-sections-showing-yes]').click();
        cy.get('input[id=section-edit-1]').click();
        cy.waitPageChange(() => {
            cy.get('input[id=submit-edit]').click();
        });
        cy.get('#div_viewer_sd1 > .folder-container > .div-viewer > a[onclick^=newEditCourseMaterialsFolderForm]').click();
        cy.get('input[id=all-sections-showing-yes-folder]').should('be.checked');
        cy.get('input[id=section-folder-edit-1]').should('be.checked').and('have.class', 'partial-checkbox');
        cy.get('input[id=section-folder-edit-1]').click();
        cy.waitPageChange(() => {
            cy.get('input[id=submit-folder-edit-full]').click();
        });
        cy.get('#div_viewer_sd1 > .folder-container > .div-viewer > a[onclick^=newEditCourseMaterialsFolderForm]').click();
        cy.get('input[id=all-sections-showing-yes-folder]').should('be.checked');
        cy.get('input[id=section-folder-edit-1]').should('be.checked').and('not.have.class', 'partial-checkbox');
        cy.get('.popup-box:visible .form-title .close-button').click();
        for (let i = 0; i <= 1; i++) {
            cy.get('#div_viewer_sd1d1 > .file-container > .file-viewer > a[onclick^=newEditCourseMaterialsForm]').eq(i).click();
            cy.get('input[id=all-sections-showing-yes]').should('be.checked');
            cy.get('input[id=section-edit-1]').should('be.checked');
            cy.get('input[id=all-sections-showing-no]').click();
            cy.waitPageChange(() => {
                cy.get('input[id=submit-edit]').click();
            });
        }
        cy.get('#div_viewer_sd1 > .folder-container > .div-viewer > a[onclick^=newEditCourseMaterialsFolderForm]').click();
        cy.get('input[id=all-sections-showing-no-folder]').should('be.checked');
        cy.get('.popup-box:visible .form-title .close-button').click();
    });

    it('Should also let a folder to have only partial sections when applying recursive updates', () => {
        cy.get('#cm-toggle-folders-btn').click();
        for (let i = 0; i <= 1; i++) {
            cy.get('#div_viewer_sd1d1 > .file-container > .file-viewer > a[onclick^=newEditCourseMaterialsForm]').eq(i).click();
            cy.get('input[id=all-sections-showing-yes]').click();
            cy.get(`input[id=section-edit-${i + 1}]`).click();
            cy.waitPageChange(() => {
                cy.get('input[id=submit-edit]').click();
            });
        }
        cy.get('#div_viewer_sd1 > .folder-container > .div-viewer > a[onclick^=newEditCourseMaterialsFolderForm]').click();
        cy.get('input[id=all-sections-showing-yes-folder]').should('be.checked');
        cy.get('input[id=section-folder-edit-1]').should('be.checked').and('have.class', 'partial-checkbox');
        cy.get('input[id=section-folder-edit-2]').should('be.checked').and('have.class', 'partial-checkbox');
        cy.get('#edit-folder-picker').should('have.value', '2022-01-01 00:00:00');
        cy.get('#edit-folder-picker').clear({ force: true });
        cy.get('#edit-folder-picker').type('2022-01-01 12:00:00', { force: true });
        cy.get('input[id=all-sections-showing-yes-folder]').click();
        cy.get('input[id=hide-folder-materials-checkbox-edit]').check();
        cy.waitPageChange(() => {
            cy.get('input[id=submit-folder-edit-full]').click();
        });
        for (let i = 0; i <= 1; i++) {
            cy.get('#div_viewer_sd1d1 > .file-container > .file-viewer > a[onclick^=newEditCourseMaterialsForm]').eq(i).click();
            cy.get('input[id=all-sections-showing-yes]').should('be.checked');
            cy.get(`input[id=section-edit-${i + 1}]`).should('be.checked');
            cy.get('#edit-picker').should('have.value', '2022-01-01 12:00:00');
            cy.get('#edit-picker').clear({ force: true });
            cy.get('#edit-picker').type('2022-01-01 00:00:00', { force: true });
            cy.get('input[id=all-sections-showing-no]').click();
            cy.get('input[id=hide-materials-checkbox-edit]').should('be.checked');
            cy.get('input[id=hide-materials-checkbox-edit]').uncheck();
            cy.waitPageChange(() => {
                cy.get('input[id=submit-edit]').click();
            });
        }
    });
});
