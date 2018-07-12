import { equal } from 'assert';
import simulant from 'simulant';
import PDFJSAnnotate from '../../src/PDFJSAnnotate';
import { enableRect, disableRect } from '../../src/UI/rect';
import mockAddAnnotation from '../mockAddAnnotation';
import mockSVGContainer from '../mockSVGContainer';

let svg;
let div;
let addAnnotationSpy;
let __addAnnotation = PDFJSAnnotate.__storeAdapter.addAnnotation;
const ALPHABET = 'abcdefghijklmnopqrstuvwxyz';

function simulateCreateRectAnnotation(type) {
  let rect = svg.getBoundingClientRect();
  simulant.fire(svg, 'mousedown', {
    clientX: rect.left + 10,
    clientY: rect.top + 10
  });

  simulant.fire(svg, 'mousemove', {
    clientX: rect.left + 50,
    clientY: rect.top + 50
  });

  simulant.fire(svg, 'mouseup', {
    clientX: rect.left + 50,
    clientY: rect.top + 50
  });
}

describe('UI::rect', function () {
  beforeEach(function () {
    svg = mockSVGContainer();
    svg.style.width = '100px';
    svg.style.height = '100px';
    document.body.appendChild(svg);

    let rect = svg.getBoundingClientRect();
    let inner = document.createElement('div');
    div = document.createElement('div');
    inner.appendChild(document.createTextNode(ALPHABET));
    div.appendChild(inner.cloneNode(true));
    div.appendChild(inner.cloneNode(true));
    div.appendChild(inner.cloneNode(true));
    div.style.top = rect.top;
    div.style.left = rect.left;
    div.style.width = '100px';
    div.style.height = '100px';
    div.style.position = 'absolute';
    document.body.appendChild(div);

    addAnnotationSpy = sinon.spy();
    PDFJSAnnotate.__storeAdapter.addAnnotation = mockAddAnnotation(addAnnotationSpy);
  });

  afterEach(function () {
    if (svg.parentNode) {
      svg.parentNode.removeChild(svg);
    }

    if (div.parentNode) {
      div.parentNode.removeChild(div);
    }

    disableRect();
  });

  after(function () {
    PDFJSAnnotate.__storeAdapter.addAnnotation = __addAnnotation;
  });

  it('should do nothing when disabled', function (done) {
    enableRect();
    disableRect();
    simulateCreateRectAnnotation();
    setTimeout(function () {
      equal(addAnnotationSpy.called, false);
      done();
    }, 0);
  });
  
  it('should create an area annotation when enabled', function (done) {
    disableRect();
    enableRect('area');
    simulateCreateRectAnnotation();
    setTimeout(function () {
      let args = addAnnotationSpy.getCall(0).args;
      equal(addAnnotationSpy.called, true);
      equal(args[0], 'test-document-id');
      equal(args[1], '1');
      equal(args[2].type, 'area');
      done();
    }, 0);
  });

  // TODO cannot trigger text selection for window.getSelection
  // it('should create a highlight annotation when enabled', function (done) {
  //   disableRect();
  //   enableRect('highlight');
  //   simulateCreateRectAnnotation();
  //   setTimeout(function () {
  //     let args = addAnnotationSpy.getCall(0).args;
  //     equal(addAnnotationSpy.called, true);
  //     equal(args[0], 'test-document-id');
  //     equal(args[1], '1');
  //     equal(args[2].type, 'highlight');
  //     equal(args[2].color, 'FFFF00');
  //     equal(args[2].rectangles.length, 3);
  //     done();
  //   }, 0);
  // });
  //
  // it('should create a strikeout annotation when enabled', function (done) {
  //   disableRect();
  //   enableRect('strikeout');
  //   simulateCreateRectAnnotation();
  //   setTimeout(function () {
  //     let args = addAnnotationSpy.getCall(0).args;
  //     equal(addAnnotationSpy.called, true);
  //     equal(args[0], 'test-document-id');
  //     equal(args[1], '1');
  //     equal(args[2].type, 'strikeout');
  //     equal(args[2].color, 'FF0000');
  //     equal(args[2].rectangles.length, 3);
  //     done();
  //   }, 0);
  // });
});
