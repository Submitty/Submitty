import { buildUrl } from '../../support/utils';

const gradeable = 'bulk_upload_test';
const student = 'aphacker';

const openDates = {
    ta_view_start_date: '1970-01-01 23:59:59',
    submission_open_date: '1971-01-01 23:59:59',
    submission_due_date: '1972-01-01 23:59:59',
    grade_start_date: '1973-01-01 23:59:59',
    grade_due_date: '1974-01-01 23:59:59',
    grade_released_date: '1974-01-01 23:59:59',
};
const originalDates = {
    ta_view_start_date: '9994-12-31 23:59:59',
    submission_open_date: '9995-12-31 23:59:59',
    submission_due_date: '9996-12-31 23:59:59',
    grade_start_date: '9997-12-31 23:59:59',
    grade_due_date: '9998-12-31 23:59:59',
    grade_released_date: '9998-12-31 23:59:59',
};

// Each grader draws a box at a different x position so rendered images can be told apart.
// Graders are numbered by sorted grader id, so instructor is Grader 1 and ta is Grader 2.
const RED = [255, 0, 0];
const BLUE = [0, 0, 255];
const BOX_TOP = 50;
const BOX_SIZE = 100;
const INSTRUCTOR_BOX = 50;
const EDITED_INSTRUCTOR_BOX = 400;
const TA_BOX = 250;

let gradeUrl;

function setDates(dates) {
    cy.visit(['sample', 'gradeable', gradeable, 'update']);
    cy.window().then((win) => {
        cy.request({
            method: 'POST',
            url: buildUrl(['sample', 'gradeable', gradeable, 'update']),
            form: true,
            body: { ...dates, csrf_token: win.csrfToken },
        }).then((response) => {
            // The endpoint sends JSON as text/html, so Cypress doesn't parse it
            expect(JSON.parse(response.body).status).to.equal('success');
        });
    });
}

function visitGradingPage(user) {
    cy.visit(gradeUrl);
    cy.login(user);
}

// Panel buttons toggle based on the remembered layout, so force the panel open instead
function showPanel(panelId) {
    cy.window().then((win) => win.setPanelsVisibilities(panelId, true));
    cy.get(`#${panelId}`).should('be.visible');
}

function openProcessedImage() {
    showPanel('submission_browser');
    // Folders also remember whether they were open
    cy.get('[data-folder-name="submissions_processed"] [id^="div_viewer_"]').first().then(($folder) => {
        if (!$folder.hasClass('open')) {
            cy.get('#submissions_processed').click();
        }
    });
    cy.intercept('POST', '**/grading/img').as('loadImage');
    cy.get('a.image-file.openable-element-submissions_processed').first().click();
    cy.wait('@loadImage');
    // Saved annotations replace the image with a marker view once it loads
    cy.get('#annotatable-image, #annotation-marker-view').should(($el) => {
        expect($el[0].id === 'annotation-marker-view' || $el[0].naturalWidth > 0).to.equal(true);
    });
}

function assertImageAnnotated(annotated) {
    if (annotated) {
        cy.get('#annotation-marker-view').should('exist');
    }
    else {
        cy.get('#annotatable-image').should(($img) => {
            expect($img[0].naturalWidth).to.be.greaterThan(0);
        });
        cy.get('#annotation-marker-view').should('not.exist');
    }
}

// Stands in for drawing in the markerjs editor, whose UI is a third party web component
function drawBox(left, color) {
    cy.get('#add-annotations-btn').click();
    cy.get('#annotatable-image').then(($img) => {
        const state = {
            width: $img[0].naturalWidth,
            height: $img[0].naturalHeight,
            markers: [{
                typeName: 'FrameMarker',
                left,
                top: BOX_TOP,
                width: BOX_SIZE,
                height: BOX_SIZE,
                rotationAngle: 0,
                strokeColor: `rgb(${color.join(',')})`,
                strokeWidth: 10,
            }],
        };
        cy.get('#global-annotation-editor').trigger('editorsave', { detail: { state, dataUrl: 'data:,' }, force: true });
    });
    cy.get('#annotation-status').should('have.text', 'Annotations modified (not saved)');
}

function saveAnnotations() {
    cy.get('#save-annotations-btn').click();
    cy.get('#annotation-status').should('have.text', 'Annotations saved successfully!');
}

function clearAnnotations() {
    cy.get('#clear-annotations-btn').click();
    cy.get('#annotation-status').should('have.text', 'Annotations cleared (not saved)');
    cy.get('#annotation-marker-view').should('not.exist');
    saveAnnotations();
}

function annotateAndSave(grader, left, color) {
    visitGradingPage(grader);
    openProcessedImage();
    drawBox(left, color);
    saveAnnotations();
}

function clearAndSave(grader) {
    visitGradingPage(grader);
    openProcessedImage();
    clearAnnotations();
}

function pixelAt(dataUrl, x, y) {
    return new Cypress.Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            const context = canvas.getContext('2d');
            context.drawImage(img, 0, 0);
            resolve(Array.from(context.getImageData(x, y, 1, 1).data.slice(0, 3)));
        };
        img.src = dataUrl;
    });
}

// Checks the color at the middle of a box's left edge
function expectBox(dataUrl, left, color, present = true) {
    cy.wrap(pixelAt(dataUrl, left, BOX_TOP + (BOX_SIZE / 2))).then((pixel) => {
        const matches = pixel.every((value, i) => Math.abs(value - color[i]) < 40);
        expect(matches, `box at x=${left} drawn in rgb(${color})`).to.equal(present);
    });
}

// Downloads click a temporary link, so capture the link instead of saving a file
function stubDownloads() {
    cy.window().then((win) => {
        cy.stub(win.HTMLAnchorElement.prototype, 'click').as('download');
    });
}

function lastDownload(count) {
    // The link is removed from the page after clicking, so pass along its values instead
    return cy.get('@download').should('have.callCount', count).then((download) => {
        const { download: filename, href } = download.lastCall.thisValue;
        return { download: filename, href };
    });
}

// Cypress can't follow new windows, so the popup is written into an iframe instead
function stubPopup() {
    cy.window().then((win) => {
        const frame = win.document.createElement('iframe');
        frame.id = 'annotation-popup-stub';
        win.document.body.appendChild(frame);
        cy.stub(win, 'open').returns(frame.contentWindow);
    });
}

function popupBody() {
    return cy.get('#annotation-popup-stub').its('0.contentDocument.body').should('have.class', 'annotated-image-popup').then(cy.wrap);
}

function popupImageSrc() {
    return popupBody().find('img').invoke('attr', 'src');
}

// Lands on the gradeables list, so the student hasn't viewed their grade yet
function loginStudent() {
    cy.visit(['sample']);
    cy.login(student);
}

function visitStudentGradeable() {
    cy.visit(['sample', 'gradeable', gradeable]);
}

function assertViewGradeHighlighted(highlighted) {
    cy.visit(['sample']);
    cy.get(`[data-testid="${gradeable}"] .btn-nav-submit`).should(highlighted ? 'have.class' : 'not.have.class', 'btn-success');
}

describe('Test cases for image annotations', () => {
    before(() => {
        cy.visit(['sample', 'gradeable', gradeable]);
        cy.login('instructor');
        setDates(openDates);

        // Images in submissions_processed are only made for PDFs assigned through bulk upload
        cy.visit(['sample', 'gradeable', gradeable]);
        // Remove PDFs left unassigned by earlier runs so the new upload is the only one
        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="bulk-delete-all"]').length > 0) {
                cy.get('[data-testid="bulk-delete-all"]').click();
                cy.get('[data-testid="assign-box"]').contains('0 files ready to assign', { timeout: 10000 });
            }
        });
        cy.get('[data-testid="radio-bulk-upload"]').click();
        cy.get('[data-testid="split-by-page-count"]').type('1');
        cy.get('[data-testid="select-files"]').selectFile('cypress/fixtures/bulk_upload.pdf', { force: true });
        cy.get('[data-testid="submit-gradeable"]').click();
        cy.get('[data-testid="assign-box"]').contains('2 files ready to assign', { timeout: 10000 });
        cy.reload();
        cy.intercept('POST', '**/verify').as('verifySubmitter');
        cy.intercept('POST', '**/split_pdf/upload?*').as('assignPdf');
        cy.get('[data-testid="bulk-user-id-1"]').type(student);
        cy.get('[data-testid="bulk-submit-1"]').click();
        cy.wait('@verifySubmitter');
        // Asks whether to keep old files when the student already has a submission, e.g. from an earlier run
        cy.get('#previous-submission-form').then(($form) => {
            if ($form.is(':visible')) {
                cy.wrap($form).find('input[type="submit"]').click();
            }
        });
        cy.wait('@assignPdf');
        cy.get('[data-testid="bulk-delete-all"]').click();
        cy.get('[data-testid="assign-box"]').contains('0 files ready to assign', { timeout: 10000 });

        cy.visit(['sample', 'gradeable', gradeable, 'grading', 'details']);
        cy.get('[data-testid="view-sections"]').uncheck();
        cy.get('#details-table').contains('tr', student).find('[data-testid="grade-button"]').invoke('attr', 'href').then((href) => {
            gradeUrl = href;
        });

        // The page images are generated by a daemon job after the PDF is assigned
        cy.then(() => cy.visit(gradeUrl));
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('body').then(($body) => $body.find('a.image-file.openable-element-submissions_processed').length > 0);
        }, 60000, 2000);
        cy.logout();
    });

    after(() => {
        cy.visit(['sample', 'gradeable', gradeable, 'update']);
        cy.login('instructor');
        setDates(originalDates);
    });

    it('should only keep annotations that the grader saves', () => {
        visitGradingPage('instructor');
        openProcessedImage();
        assertImageAnnotated(false);

        drawBox(INSTRUCTOR_BOX, RED);
        assertImageAnnotated(true);
        cy.reload();
        openProcessedImage();
        assertImageAnnotated(false);

        drawBox(INSTRUCTOR_BOX, RED);
        saveAnnotations();
        cy.reload();
        cy.window().then((win) => {
            cy.spy(win.markerjs3.Renderer.prototype, 'rasterize').as('rasterize');
        });
        openProcessedImage();
        assertImageAnnotated(true);

        // The downloadable image is rendered in the background once saved annotations load
        cy.get('@rasterize').should('have.been.calledOnce').its('firstCall.returnValue').then((rendering) => rendering);
        stubDownloads();
        cy.get('#download-image-btn').click();
        lastDownload(1).then((link) => {
            expect(link.download).to.match(/_annotated\.\w+$/);
            expect(link.href).to.match(/^data:image\/png/);
        });

        clearAnnotations();
        cy.reload();
        openProcessedImage();
        assertImageAnnotated(false);

        drawBox(INSTRUCTOR_BOX, RED);
        saveAnnotations();
    });

    it('should only show annotations to the student once grading is complete', () => {
        loginStudent();
        assertViewGradeHighlighted(false);
        visitStudentGradeable();
        cy.get('[data-testid="annotation-popup-button"]').should('not.exist');
        cy.logout();

        visitGradingPage('instructor');
        showPanel('grading_rubric');
        cy.get('[data-testid="component-container"]').first().click(20, 25);
        cy.get('[data-testid="save-tools-save"]', { timeout: 20000 }).should('contain', 'Save');
        // Full Credit
        cy.get('body').type('{0}');
        cy.intercept('POST', '**/graded_gradeable/graded_component').as('saveComponent');
        cy.get('[data-testid="save-tools-save"]').click();
        cy.wait('@saveComponent');
        cy.logout();

        loginStudent();
        assertViewGradeHighlighted(true);
        visitStudentGradeable();
        stubPopup();
        cy.get('[data-testid="annotation-popup-button"]').click();
        popupBody().find('button').should('not.exist');
        popupImageSrc().then((src) => expectBox(src, INSTRUCTOR_BOX, RED));

        stubDownloads();
        cy.get('[data-testid="annotation-download-button"]').click();
        lastDownload(1).then((link) => {
            expect(link.download).to.match(/_annotated\.\w+$/);
            expectBox(link.href, INSTRUCTOR_BOX, RED);
        });

        assertViewGradeHighlighted(false);
    });

    it('should show the student each grader\'s annotations', () => {
        annotateAndSave('ta', TA_BOX, BLUE);
        cy.logout();

        // Saving an annotation counts as a new grade
        loginStudent();
        assertViewGradeHighlighted(true);
        visitStudentGradeable();
        stubPopup();
        cy.get('[data-testid="annotation-popup-button"]').click();
        popupBody().find('button').should('have.length', 3);
        const views = [
            { label: 'Grader 1', boxes: { [INSTRUCTOR_BOX]: true, [TA_BOX]: false } },
            { label: 'Grader 2', boxes: { [INSTRUCTOR_BOX]: false, [TA_BOX]: true } },
            { label: 'All Graders', boxes: { [INSTRUCTOR_BOX]: true, [TA_BOX]: true } },
        ];
        views.forEach(({ label, boxes }) => {
            popupBody().contains('button', label).click();
            popupBody().find('.annotated-image-warning').should(label === 'All Graders' ? 'be.visible' : 'not.be.visible');
            popupImageSrc().then((src) => {
                expectBox(src, INSTRUCTOR_BOX, RED, boxes[INSTRUCTOR_BOX]);
                expectBox(src, TA_BOX, BLUE, boxes[TA_BOX]);
            });
        });

        stubDownloads();
        const downloads = [
            { option: 'Grader 1', suffix: '_grader1', boxes: views[0].boxes },
            { option: 'Grader 2', suffix: '_grader2', boxes: views[1].boxes },
            { option: 'All Graders Combined', suffix: '_all', boxes: views[2].boxes },
        ];
        downloads.forEach(({ option, suffix, boxes }, index) => {
            cy.get('[data-testid="annotation-download-button"]').click();
            cy.get('#annotation-download-popup').contains('button', option).click();
            cy.get('#annotation-download-popup').should('not.exist');
            lastDownload(index + 1).then((link) => {
                expect(link.download).to.match(new RegExp(`_annotated${suffix}\\.\\w+$`));
                expectBox(link.href, INSTRUCTOR_BOX, RED, boxes[INSTRUCTOR_BOX]);
                expectBox(link.href, TA_BOX, BLUE, boxes[TA_BOX]);
            });
        });
    });

    it('should show the student annotation changes once they are saved', () => {
        visitGradingPage('instructor');
        openProcessedImage();
        drawBox(EDITED_INSTRUCTOR_BOX, RED);
        cy.logout();

        loginStudent();
        visitStudentGradeable();
        stubPopup();
        cy.get('[data-testid="annotation-popup-button"]').click();
        popupBody().contains('button', 'Grader 1').click();
        popupImageSrc().then((src) => {
            expectBox(src, INSTRUCTOR_BOX, RED);
            expectBox(src, EDITED_INSTRUCTOR_BOX, RED, false);
        });
        cy.logout();

        annotateAndSave('instructor', EDITED_INSTRUCTOR_BOX, RED);
        cy.logout();

        loginStudent();
        assertViewGradeHighlighted(true);
        visitStudentGradeable();
        stubPopup();
        cy.get('[data-testid="annotation-popup-button"]').click();
        popupBody().contains('button', 'Grader 1').click();
        popupImageSrc().then((src) => {
            expectBox(src, INSTRUCTOR_BOX, RED, false);
            expectBox(src, EDITED_INSTRUCTOR_BOX, RED);
        });
        cy.logout();

        clearAndSave('ta');
        cy.logout();

        loginStudent();
        visitStudentGradeable();
        stubPopup();
        cy.get('[data-testid="annotation-popup-button"]').click();
        popupBody().find('button').should('not.exist');
        cy.logout();

        // The feedback section only lists images that still have an annotation file
        clearAndSave('instructor');
        cy.logout();

        loginStudent();
        visitStudentGradeable();
        cy.get('[data-testid="annotation-popup-button"]').should('not.exist');
    });
});
