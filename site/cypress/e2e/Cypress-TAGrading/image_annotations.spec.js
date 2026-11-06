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
    cy.window().its('setPanelsVisibilities').should('be.a', 'function');
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

function editorShadow() {
    return cy.get('#global-annotation-editor').shadow();
}

// The marker area's drawing canvas sits inside the editor's shadow root
function editorCanvas() {
    return editorShadow().find('mjs-marker-area').shadow().find('svg')
        .filter('[style*="pointer-events: auto"]').first();
}

// Drags out a rectangle in the editor, with corners given as fractions of the image size
function drawRectangleInEditor(from, to) {
    // The editor has a wide and a narrow toolbar, and only one of them is laid out.
    // Each shape group also lists its shapes again in a dropdown, so only take the group's own button.
    editorShadow().find('[aria-label="Basic shapes"] > [title="Rectangle"]')
        .filter((_index, button) => button.getClientRects().length > 0)
        .should('have.length', 1)
        .click();
    editorCanvas().then(($canvas) => {
        const rect = $canvas[0].getBoundingClientRect();
        const pointer = ([x, y]) => ({
            eventConstructor: 'PointerEvent',
            pointerId: 1,
            pointerType: 'mouse',
            isPrimary: true,
            button: 0,
            clientX: rect.left + (rect.width * x),
            clientY: rect.top + (rect.height * y),
        });
        cy.wrap($canvas).trigger('pointerdown', pointer(from));
        // The editor listens for the rest of the drag on the window
        cy.document().trigger('pointermove', pointer([(from[0] + to[0]) / 2, (from[1] + to[1]) / 2]));
        cy.document().trigger('pointermove', pointer(to));
        cy.document().trigger('pointerup', pointer(to));
    });
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

// Unchecks every mark on the first component so the next run starts without a grade
function resetComponentGrade() {
    // gradeUrl is only set once the before hook's commands run
    cy.then(() => {
        if (gradeUrl === undefined) {
            return;
        }
        resetComponentGradeAt(gradeUrl);
    });
}

function resetComponentGradeAt(url) {
    // The page reopens the last open component from this cookie, so start with every component closed
    cy.clearCookie('open_component_id');
    cy.visit(url);
    showPanel('grading_rubric');
    cy.get('[data-testid="component-container"]').first().click(20, 25);
    cy.get('[data-testid="save-tools-save"]', { timeout: 20000 }).should('contain', 'Save');
    cy.get('[data-testid="component-container"]').first().find('[data-testid="mark-selector"]').should('exist');
    cy.get('[data-testid="component-container"]').first().then(($component) => {
        $component.find('[data-testid="mark-selector"]:has(.mark-selected)').each((_index, mark) => {
            cy.wrap(mark).click();
        });
    });
    // Saving a component with no marks deletes its grade. Saving also closes the component,
    // which matters because the page reopens it on the next visit and jumps to its PDF page.
    cy.get('[data-testid="save-tools-save"]').click();
    cy.get('[data-testid="save-tools-save"]').should('not.exist');
}

// The rubric editor is rendered after the page loads, so wait for it before looking for components
function visitRubricEditor() {
    cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=2']);
    cy.get('[data-testid="add-new-peer-component"]', { timeout: 10000 }).should('be.visible');
}

function addPeerGrader(grader) {
    visitRubricEditor();
    cy.get('[data-testid="add-new-peer-component"]').click();
    cy.get('[data-testid="peer-component-container"]').should('exist');
    cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=4']);
    cy.get('[data-testid="peer-control"]').should('be.visible');
    cy.get('body').then(($body) => {
        // The matrix may still have the grader if an earlier run stopped before cleanup
        if ($body.find(`.peer-table-grader:contains("${grader}")`).length > 0) {
            return;
        }
        cy.get('[data-testid="add-peer-grader"]').click();
        cy.get('[data-testid="new-peer-grader"]').type(grader);
        cy.get('[data-testid="add-user-id-0"]').type(student);
        // Close the autocomplete list before submitting
        cy.get('[data-testid="new-peer-grader"]').click();
        cy.get('[data-testid="admin-gradeable-add-peers-submit"]').click();
    });
    cy.get('.peer-table-grader').should('contain', grader);
}

function deletePeerComponents() {
    cy.get('body').then(($body) => {
        const count = $body.find('[data-testid="peer-component-container"]').length;
        if (count === 0) {
            return;
        }
        cy.get('[data-testid="peer-component-container"]').first().find('[data-testid="delete-gradable-component"]').click();
        cy.get('[data-testid="peer-component-container"]').should('have.length', count - 1);
        deletePeerComponents();
    });
}

// Turns the gradeable back into a non-peer gradeable, if a peer test left it as one
function removePeerGrading() {
    visitRubricEditor();
    cy.get('body').then(($body) => {
        if ($body.find('[data-testid="peer-component-container"]').length === 0) {
            return;
        }
        cy.visit(['sample', 'gradeable', gradeable, 'update?nav_tab=4']);
        cy.intercept('POST', '**/RandomizePeers').as('clearPeers');
        cy.get('[data-testid="clear-peer-matrix"]').click();
        cy.wait('@clearPeers');
        visitRubricEditor();
        deletePeerComponents();
    });
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
        // Asks whether to keep old files when the student already has a submission, e.g. from an earlier run
        cy.wait('@verifySubmitter').its('response.body').then((body) => {
            const response = typeof body === 'string' ? JSON.parse(body) : body;
            if (response.data.previous_submission) {
                cy.get('#previous-submission-form').should('be.visible').find('input[type="submit"]').click();
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
        // Undo anything an earlier run left behind if it stopped before its after hook finished
        removePeerGrading();
        resetComponentGrade();
        cy.logout();
    });

    after(() => {
        cy.visit(['sample', 'gradeable', gradeable, 'update']);
        cy.login('instructor');
        removePeerGrading();
        resetComponentGrade();
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

        // Removing an annotation also counts as a new grade
        loginStudent();
        assertViewGradeHighlighted(true);
        visitStudentGradeable();
        stubPopup();
        cy.get('[data-testid="annotation-popup-button"]').click();
        popupBody().find('button').should('not.exist');
        cy.logout();

        // The feedback section only lists images that still have an annotation file
        clearAndSave('instructor');
        cy.logout();

        loginStudent();
        assertViewGradeHighlighted(true);
        visitStudentGradeable();
        cy.get('[data-testid="annotation-popup-button"]').should('not.exist');
    });

    it('should save and clear multiple annotations drawn in the editor', () => {
        const boxes = [
            { from: [0.1, 0.1], to: [0.3, 0.2] },
            { from: [0.5, 0.4], to: [0.8, 0.6] },
        ];

        // The editor's canvas is as large as the image is in the panel, so give the panel room to draw in
        cy.viewport(1600, 1200);
        visitGradingPage('instructor');
        openProcessedImage();
        // Start from a blank image even if an earlier test left an annotation behind
        clearAnnotations();
        assertImageAnnotated(false);
        cy.get('#annotatable-image').invoke('outerWidth').should('be.greaterThan', 200);

        cy.get('#add-annotations-btn').click();
        // Once the editor's copy of the image loads, it restores the state it was opened with, wiping
        // anything drawn before then. markerjs has no public event for this, so wait on its load flag.
        cy.get('#global-annotation-editor').should(($editor) => {
            expect($editor[0].markerArea._targetImageLoaded).to.equal(true);
        });
        editorCanvas().should(($canvas) => {
            expect(parseFloat($canvas[0].style.width)).to.be.greaterThan(200);
        });
        boxes.forEach(({ from, to }) => drawRectangleInEditor(from, to));
        cy.get('#global-annotation-editor').should(($editor) => {
            expect($editor[0].markerArea.getState().markers).to.have.length(boxes.length);
        });
        editorShadow().find('[aria-label="OK"]').click();
        cy.get('#annotation-status').should('have.text', 'Annotations modified (not saved)');
        saveAnnotations();

        // Each box is shown where it was drawn on the image
        cy.reload();
        openProcessedImage();
        cy.get('#annotation-marker-view').should(($view) => {
            const view = $view[0];
            expect(view.markers).to.have.length(boxes.length);
            const image = view.shadowRoot.querySelector('img').getBoundingClientRect();
            const markers = view.markers
                .map((marker) => marker.container.getBoundingClientRect())
                .sort((a, b) => a.left - b.left);
            boxes.forEach(({ from, to }, i) => {
                expect((markers[i].left - image.left) / image.width).to.be.closeTo(from[0], 0.03);
                expect((markers[i].top - image.top) / image.height).to.be.closeTo(from[1], 0.03);
                expect((markers[i].right - image.left) / image.width).to.be.closeTo(to[0], 0.03);
                expect((markers[i].bottom - image.top) / image.height).to.be.closeTo(to[1], 0.03);
            });
        });

        cy.intercept('POST', '**/img/annotations').as('saveCleared');
        clearAnnotations();
        cy.wait('@saveCleared').then(({ request }) => {
            const annotations = new URLSearchParams(request.body).get('annotations');
            expect(JSON.parse(annotations)).to.equal(null);
        });
        cy.reload();
        openProcessedImage();
        assertImageAnnotated(false);
    });

    it('should hide the annotation toolbar from peer graders', () => {
        visitGradingPage('instructor');
        addPeerGrader('student');

        // Staff can still annotate once the gradeable has a peer component
        cy.visit(gradeUrl);
        openProcessedImage();
        cy.get('.annotation-toolbar').should('be.visible');
        cy.logout();

        cy.visit(['sample', 'gradeable', gradeable, 'grading', 'details']);
        cy.login('student');
        // Peer graders are shown the grading instructions the first time they visit
        cy.get('body').then(($body) => {
            if ($body.find('[data-testid="popup-save-button"]').length > 0) {
                cy.get('[data-testid="popup-save-button"]').click();
            }
        });
        cy.get('[data-testid="grade-button"]').should('have.length', 1).click();
        openProcessedImage();
        cy.get('.annotation-toolbar').should('not.exist');
        cy.get('#add-annotations-btn').should('not.exist');
    });
});
