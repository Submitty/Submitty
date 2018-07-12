import renderScreenReaderComments from '../../src/a11y/renderScreenReaderComments';
import PDFJSAnnotate from '../../src/PDFJSAnnotate';
import { equal } from 'assert';

let hint;
let getComments = PDFJSAnnotate.__storeAdapter.getComments;

describe('a11y::renderScreenReaderComments', function () {
  beforeEach(function () {
    hint = document.createElement('div');
    hint.setAttribute('id', 'pdf-annotate-screenreader-12345');
    document.body.appendChild(hint);

    PDFJSAnnotate.__storeAdapter.getComments = () => {
      return Promise.resolve([{
        annotation: 12345,
        content: 'foo'
      }, {
        annotation: 12345,
        content: 'bar'
      }]);
    }
  });

  afterEach(function () {
    if (hint && hint.parentNode) {
      hint.parentNode.removeChild(hint);
    }

    PDFJSAnnotate.__storeAdapter.getComments = getComments;
  });

  it('should render comments', function (done) {
    renderScreenReaderComments(null, 12345);

    setTimeout(function () {
      let list = hint.querySelector('ol');
      equal(list.getAttribute('aria-label'), 'Comments');
      equal(list.children.length, 2);
      equal(list.children[0].innerHTML, 'foo');
      equal(list.children[1].innerHTML, 'bar');
      done();
    });
  });
});
