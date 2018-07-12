import insertScreenReaderComment from '../../src/a11y/insertScreenReaderComment';
import { equal } from 'assert';

let hint;

describe('a11y::insertScreenReaderComment', function () {
  beforeEach(function () {
    hint = document.createElement('div');
    hint.setAttribute('id', 'pdf-annotate-screenreader-12345');
    hint.innerHTML = '<ol></ol>';
    document.body.appendChild(hint);
  });

  afterEach(function () {
    if (hint && hint.parentNode) {
      hint.parentNode.removeChild(hint);
    }
  });

  it('should render a comment', function () {
    insertScreenReaderComment({
      annotation: 12345,
      content: 'Hello there!'
    });

    let list = hint.querySelector('ol');
    equal(list.children.length, 1);
    equal(list.children[0].innerHTML, 'Hello there!');
  });

  it('should fail gracefully if no comment provided', function () {
    let error;
    try {
      insertScreenReaderComment();
    } catch (e) {
      error = e;
    }

    equal(typeof error, 'undefined');
  });
  
  it('should fail gracefully if bad annotation provided', function () {
    let error;
    try {
      insertScreenReaderComment({
        annotation: 0
      });
    } catch (e) {
      error = e;
    }

    equal(typeof error, 'undefined');
  });

});
