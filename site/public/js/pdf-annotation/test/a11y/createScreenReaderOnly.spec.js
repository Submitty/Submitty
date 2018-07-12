import createScreenReaderOnly from '../../src/a11y/createScreenReaderOnly';
import { equal } from 'assert';

describe('a11y::createScreenReaderOnly', function () {
  it('should create an element that cannot be seen', function () {
    let sr = createScreenReaderOnly();
    equal(sr.offsetWidth, 0);
    equal(sr.offsetHeight, 0);
  });

  it('should set innerHTML', function () {
    let sr = createScreenReaderOnly('foo bar baz');
    equal(sr.innerHTML, 'foo bar baz');
  });

  it('should set id', function () {
    let sr = createScreenReaderOnly(null, '12345');
    equal(sr.getAttribute('id'), 'pdf-annotate-screenreader-12345');
  });
});
