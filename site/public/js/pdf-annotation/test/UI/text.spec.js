import { equal } from 'assert';
import simulant from 'simulant';
import PDFJSAnnotate from '../../src/PDFJSAnnotate';
import { setText, enableText, disableText } from '../../src/UI/text';
import mockAddAnnotation from '../mockAddAnnotation';
import mockSVGContainer from '../mockSVGContainer';

let svg;
let addAnnotationSpy;
let __addAnnotation = PDFJSAnnotate.__storeAdapter.addAnnotation;

function simulateCreateTextAnnotation(textContent, textSize, textColor) {
  setText(textSize, textColor);

  let rect = svg.getBoundingClientRect();
  simulant.fire(svg, 'mouseup', {
    target: svg,
    clientX: rect.left + 10,
    clientY: rect.top + 10
  });

  setTimeout(function () {
    let input = document.getElementById('pdf-annotate-text-input');
    if (input) {
      input.focus();
      input.value = textContent;
      simulant.fire(input, 'blur');
    }
  }, 0);
}

describe('UI::text', function () {
  beforeEach(function () {
    svg = mockSVGContainer();
    svg.style.width = '100px';
    svg.style.height = '100px';
    document.body.appendChild(svg);

    addAnnotationSpy = sinon.spy();
    PDFJSAnnotate.__storeAdapter.addAnnotation = mockAddAnnotation(addAnnotationSpy);
  });

  afterEach(function () {
    let input = document.getElementById('pdf-annotate-text-input');
    if (input && input.parentNode) {
      input.parentNode.removeChild(input);
    }

    if (svg.parentNode) {
      svg.parentNode.removeChild(svg);
    }

    disableText();
  });

  after(function () {
    PDFJSAnnotate.__storeAdapter.addAnnotation = __addAnnotation;
  });

  it('should do nothing when disabled', function (done) {
    enableText();
    disableText();
    simulateCreateTextAnnotation('foo bar baz');
    setTimeout(function () {
      equal(addAnnotationSpy.called, false);
      done();
    }, 0);
  });

  it('should create an annotation when enabled', function (done) {
    disableText();
    enableText();
    simulateCreateTextAnnotation('foo bar baz');
    setTimeout(function () {
      let args = addAnnotationSpy.getCall(0).args;
      equal(addAnnotationSpy.called, true);
      equal(args[0], 'test-document-id');
      equal(args[1], '1');
      equal(args[2].type, 'textbox');
      equal(args[2].size, '12');
      equal(args[2].color, '000000');
      equal(args[2].content, 'foo bar baz');
      done();
    }, 0);
  });
});
