import { equal } from 'assert';
import simulant from 'simulant';
import { addEventListener, removeEventListener } from '../../src/UI/event';
import mockSVGContainer from '../mockSVGContainer';
import mockTextAnnotation from '../mockTextAnnotation';

let svg;
let text;
let rect;
let annotationClickSpy;
let annotationBlurSpy;

describe('UI::event', function () {
  beforeEach(function () {
    svg = mockSVGContainer();
    text = mockTextAnnotation();

    document.body.appendChild(svg);
    svg.appendChild(text);
    svg.style.width = '100px';
    svg.style.width = '100px';

    rect = svg.getBoundingClientRect();

    annotationClickSpy = sinon.spy();
    annotationBlurSpy = sinon.spy();

    addEventListener('annotation:click', annotationClickSpy);
    addEventListener('annotation:blur', annotationBlurSpy);
  });

  afterEach(function () {
    // Blur to reset internal state of add/remove event
    simulant.fire(svg, 'click', {
      clientX: rect.left + 1,
      clientY: rect.top + 1
    });

    if (svg.parentNode) {
      svg.parentNode.removeChild(svg);
    }

    removeEventListener('annotation:click', annotationClickSpy);
    removeEventListener('annotation:blur', annotationBlurSpy);
  });

  it('should emit an event when an annotation is clicked', function (done) {

    simulant.fire(svg, 'click', {
      clientX: text.getBoundingClientRect().left + 1,
      clientY: text.getBoundingClientRect().top + 1
    });

    setTimeout(function () {
      equal(annotationClickSpy.calledOnce, true);
      console.log(annotationClickSpy.getCall(0).args[0], text)
      equal(annotationClickSpy.getCall(0).args[0], text);
      done();
    }, 0);
  });

  it('should emit an event when an annotation is blurred', function (done) {
    simulant.fire(svg, 'click', {
      clientX: text.getBoundingClientRect().left + 1,
      clientY: text.getBoundingClientRect().top + 1
    });

    setTimeout(function () {
      simulant.fire(svg, 'click', {
        clientX: rect.left + 1,
        clientY: rect.top + 1
      });

      setTimeout(function () {
        equal(annotationBlurSpy.calledOnce, true);
        equal(annotationBlurSpy.getCall(0).args[0], text);
        done();
      }, 0);
    }, 0);
  });

  it('should allow removing an event listener', function (done) {
    removeEventListener('annotation:click', annotationClickSpy);

    simulant.fire(svg, 'click', {
      clientX: rect.left + 15,
      clientY: rect.top + 15
    });

    setTimeout(function () {
      equal(annotationClickSpy.called, false);
      done();
    }, 0);
  });
});
