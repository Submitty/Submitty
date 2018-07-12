import PDFJSAnnotate from '../../src/PDFJSAnnotate';
import { createPage, renderPage } from '../../src/UI/page';
import mockPDFDocument from '../mockPDFDocument';
import mockPDFJS from '../mockPDFJS';
import { equal } from 'assert';

let page;
let _PDFJS = window.PDFJS;
let getAnnotations = PDFJSAnnotate.getAnnotations;

describe('UI::page', function () {
  before(function () {
    _PDFJS = window.PDFJS;
    window.PDFJS = mockPDFJS();
    PDFJSAnnotate.getAnnotations = function (documentId, pageNumber) {
      return new Promise((resolve, reject) => {
        resolve({
          documentId,
          pageNumber,
          annotations: []
        });
      });
    };
  });

  after(function () {
    window.PDFJS = _PDFJS;
    PDFJSAnnotate.getAnnotations = getAnnotations;
  });

  beforeEach(function () {
    page = createPage(1);
  });

  afterEach(function () {
    if (page.parentNode) {
      page.parentNode.removeChild(page);
    }
  });

  it('should create a page', function () {
    let canvas = page.querySelector('canvas');
    let svg = page.querySelector('svg');
    let wrapper = page.querySelector('.canvasWrapper');
    let container = page.querySelector('.textLayer');

    equal(page.nodeName, 'DIV');
    equal(page.className, 'page');
    equal(page.getAttribute('id'), 'pageContainer1');
    equal(page.getAttribute('data-page-number'), '1');
    equal(page.getAttribute('data-loaded'), 'false');
    equal(page.style.visibility, 'hidden');

    equal(canvas.getAttribute('id'), 'page1');
    equal(canvas.parentNode, wrapper);

    equal(wrapper.className, 'canvasWrapper');
    equal(svg.getAttribute('class'), 'annotationLayer');
    equal(container.className, 'textLayer');
  });

  it('should render a page', function (done) {
    document.body.appendChild(page);

    renderPage(1, {
      documentId: 'test-document-id',
      pdfDocument: mockPDFDocument(),
      scale: 1,
      rotate: 0
    }).then(function ([pdfPage, annotations]) {
      equal(page.getAttribute('data-loaded'), 'true');
      equal(page.style.visibility, '');

      equal(typeof pdfPage.render, 'function');
      equal(Array.isArray(annotations.annotations), true);

      done();
    });
  });
});

