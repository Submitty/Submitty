import { equal } from 'assert';
import simulant from 'simulant';
import PDFJSAnnotate from '../../src/PDFJSAnnotate';
import mockAddAnnotation from '../mockAddAnnotation';
import mockSVGContainer from '../mockSVGContainer';
import { setPen, enablePen, disablePen } from '../../src/UI/pen';

let svg;
let addAnnotationSpy;
let __addAnnotation = PDFJSAnnotate.__storeAdapter.addAnnotation;

function simulateCreateDrawingAnnotation(penSize, penColor) {
  setPen(penSize, penColor);

  let rect = svg.getBoundingClientRect();
  simulant.fire(document, 'mousedown', {
    clientX: rect.left + 10,
    clientY: rect.top + 10
  });

  simulant.fire(document, 'mousemove', {
    clientX: rect.left + 15,
    clientY: rect.top + 15
  });

  simulant.fire(document, 'mousemove', {
    clientX: rect.left + 30,
    clientY: rect.top + 30
  });

  simulant.fire(document, 'mouseup', {
    clientX: rect.left + 30,
    clientY: rect.top + 30
  });
}

describe('UI::pen', function () {
  beforeEach(function () {
    svg = mockSVGContainer();
    svg.style.width = '100px';
    svg.style.height = '100px';
    document.body.appendChild(svg);

    addAnnotationSpy = sinon.spy();
    PDFJSAnnotate.__storeAdapter.addAnnotation = mockAddAnnotation(addAnnotationSpy);
  });

  afterEach(function () {
    if (svg.parentNode) {
      svg.parentNode.removeChild(svg);
    }
    
    disablePen();
  });

  after(function () {
    PDFJSAnnotate.__storeAdapter.addAnnotation = __addAnnotation;
  });

  it('should do nothing when disabled', function (done) {
    enablePen();
    disablePen();
    simulateCreateDrawingAnnotation();
    setTimeout(function () {
      equal(addAnnotationSpy.called, false);
      done();
    }, 0);
  });

  it('should create an annotation when enabled', function (done) {
    disablePen();
    enablePen();
    simulateCreateDrawingAnnotation();
    setTimeout(function () {
      let args = addAnnotationSpy.getCall(0).args;
      equal(addAnnotationSpy.called, true);
      equal(args[0], 'test-document-id');
      equal(args[1], '1');
      equal(args[2].type, 'drawing');
      equal(args[2].width, 1);
      equal(args[2].color, '000000');
      equal(args[2].lines.length, 2);
      done();
    }, 0);
  });
});
