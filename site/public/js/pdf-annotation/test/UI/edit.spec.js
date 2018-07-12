import { equal } from 'assert';
import simulant from 'simulant';
import PDFJSAnnotate from '../../src/PDFJSAnnotate';
import { enableEdit, disableEdit, destroyEditOverlay } from '../../src/UI/edit';
import mockEditAnnotation from '../mockEditAnnotation';
import mockDeleteAnnotation from '../mockDeleteAnnotation';
import mockSVGContainer from '../mockSVGContainer';
import mockLineAnnotation, { DEFAULT_LINE_ANNOTATION } from '../mockLineAnnotation';
import mockPathAnnotation, { DEFAULT_PATH_ANNOTATION } from '../mockPathAnnotation';
import mockTextAnnotation, { DEFAULT_TEXT_ANNOTATION } from '../mockTextAnnotation';
import mockRectAnnotation, { DEFAULT_RECT_ANNOTATION } from '../mockRectAnnotation';

let svg;
let line;
let path;
let text;
let rect;
let annotations = {};
let editAnnotationSpy;
let deleteAnnotationSpy;
let __getAnnotation = PDFJSAnnotate.__storeAdapter.getAnnotation;
let __editAnnotation = PDFJSAnnotate.__storeAdapter.editAnnotation;
let __deleteAnnotation = PDFJSAnnotate.__storeAdapter.deleteAnnotation;

function findOverlay() {
  return document.getElementById('pdf-annotate-edit-overlay');
}

function simulateMoveOverlay(callback) {
  simulant.fire(document, 'click', { clientX: 25, clientY: 25 });
  setTimeout(function () {
    let overlay = findOverlay();
    simulant.fire(overlay, 'mousedown', { clientX: 25, clientY: 25 });
    setTimeout(function () {
      simulant.fire(overlay, 'mousemove', { clientX: 50, clientY: 50 });
      setTimeout(function () {
        simulant.fire(overlay, 'mouseup', { clientX: 50, clientY: 50 });
        setTimeout(function () {
          let call = editAnnotationSpy.getCall(0);
          callback(call ? call.args : []);
        });
      });
    });
  });
}

function simulateClickAnnotation(callback) {
  simulant.fire(document, 'click', { clientX: 25, clientY: 25 });
  setTimeout(function () {
    let overlay = findOverlay();
    callback(overlay);
  });
}

describe('UI::edit', function () {
  beforeEach(function () {
    svg = mockSVGContainer();
    svg.style.width = '100px';
    svg.style.height = '100px';
    document.body.appendChild(svg);

    line = mockLineAnnotation();
    path = mockPathAnnotation();
    text = mockTextAnnotation();
    rect = mockRectAnnotation();

    annotations[line.getAttribute('data-pdf-annotate-id')] = DEFAULT_LINE_ANNOTATION;
    annotations[path.getAttribute('data-pdf-annotate-id')] = DEFAULT_PATH_ANNOTATION;
    annotations[text.getAttribute('data-pdf-annotate-id')] = DEFAULT_TEXT_ANNOTATION;
    annotations[rect.getAttribute('data-pdf-annotate-id')] = DEFAULT_RECT_ANNOTATION;

    editAnnotationSpy = sinon.spy();
    deleteAnnotationSpy = sinon.spy();
    PDFJSAnnotate.__storeAdapter.editAnnotation = mockEditAnnotation(editAnnotationSpy);
    PDFJSAnnotate.__storeAdapter.deleteAnnotation = mockDeleteAnnotation(deleteAnnotationSpy);
    PDFJSAnnotate.__storeAdapter.getAnnotation = function (documentId, annotationId) {
      return Promise.resolve(annotations[annotationId]);
    };
  });

  afterEach(function () {
    if (svg.parentNode) {
      svg.parentNode.removeChild(svg);
    }

    disableEdit();
  });

  after(function () {
    PDFJSAnnotate.__storeAdapter.getAnnotation = __getAnnotation;
    PDFJSAnnotate.__storeAdapter.editAnnotation = __editAnnotation;
    PDFJSAnnotate.__storeAdapter.deleteAnnotation = __deleteAnnotation;
  });

  it('should do nothing when disabled', function (done) {
    enableEdit();
    disableEdit();
    svg.appendChild(text);
    simulant.fire(document, 'click', { clientX: 25, clientY: 25 });

    setTimeout(function () {
      equal(findOverlay(), null);
      done();
    });
  });

  it('should create an overlay when annotation is clicked', function (done) {
    enableEdit();
    svg.appendChild(text);
    simulant.fire(document, 'click', { clientX: 25, clientY: 25 });

    setTimeout(function () {
      let overlay = findOverlay();
      equal(overlay.nodeName, 'DIV');
      equal(overlay.getAttribute('data-target-id'), text.getAttribute('data-pdf-annotate-id'));
      done();
    });
  });

  it('should destroy overlay when document is clicked', function (done) {
    enableEdit();
    svg.appendChild(text);
    simulant.fire(document, 'click', { clientX: 25, clientY: 25 });

    setTimeout(function () {
      equal(findOverlay().nodeName, 'DIV');
      simulant.fire(document, 'click', { clientX: 10, clientY: 10 });

      setTimeout(function () {
        equal(findOverlay(), null);
        done();
      });
    });
  });

  it('should delete annotation when DELETE is pressed', function (done) {
    enableEdit();
    svg.appendChild(text);
    simulant.fire(document, 'click', { clientX: 25, clientY: 25 });

    setTimeout(function () {
      simulant.fire(document, 'keyup', { keyCode: 46 });

      setTimeout(function () {
        let args = deleteAnnotationSpy.getCall(0).args;
        equal(deleteAnnotationSpy.called, true);
        equal(args[0], 'test-document-id');
        equal(args[1], text.getAttribute('data-pdf-annotate-id'));
        done();
      });
    });
  });

  it('should edit text annotation when overlay moved', function (done) {
    enableEdit();
    svg.appendChild(text);
    simulateMoveOverlay(function (args) {
      equal(editAnnotationSpy.called, true);
      equal(args[0], 'test-document-id');
      equal(args[1], text.getAttribute('data-pdf-annotate-id'));
      equal(args[2], DEFAULT_TEXT_ANNOTATION);
      done();
    });
  });
  
  it('should edit rect annotation when overlay moved', function (done) {
    enableEdit();
    svg.appendChild(rect);
    simulateMoveOverlay(function (args) {
      equal(editAnnotationSpy.called, true);
      equal(args[0], 'test-document-id');
      equal(args[1], rect.getAttribute('data-pdf-annotate-id'));
      equal(args[2], DEFAULT_RECT_ANNOTATION);
      done();
    });
  });

  it('should not edit line annotation when overlay moved', function (done) {
    enableEdit();
    svg.appendChild(line);
    simulateMoveOverlay(function (args) {
      equal(editAnnotationSpy.called, false);
      done();
    });
  });

  it('should edit path annotation when overlay moved', function (done) {
    enableEdit();
    svg.appendChild(path);
    simulateMoveOverlay(function (args) {
      equal(editAnnotationSpy.called, true);
      equal(args[0], 'test-document-id');
      equal(args[1], path.getAttribute('data-pdf-annotate-id'));
      equal(args[2], DEFAULT_PATH_ANNOTATION);
      done();
    });
  });

  it('should show delete icon when overlay moused over', function (done) {
    enableEdit();
    svg.appendChild(rect);
    simulateClickAnnotation(function (overlay) {
      simulant.fire(overlay, 'mouseover', { clientX: 30, clientY: 30 });
      setTimeout(function () {
        let a = overlay.querySelector('a');
        equal(a.style.display, '');
        done();
      });
    });
  });

  it('should hide delete icon when overlay moused out', function (done) {
    enableEdit();
    svg.appendChild(rect);
    simulateClickAnnotation(function (overlay) {
      simulant.fire(overlay, 'mouseover', { clientX: 30, clientY: 30 });
      setTimeout(function () {
        simulant.fire(overlay, 'mouseout', { clientX: 10, clientY: 10 });
        setTimeout(function () {
          let a = overlay.querySelector('a');
          equal(a.style.display, 'none');
          done();
        });
      });
    });
  });
});
