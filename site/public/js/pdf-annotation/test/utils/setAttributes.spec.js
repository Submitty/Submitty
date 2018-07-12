import setAttributes from '../../src/utils/setAttributes';
import { equal } from 'assert';

describe('utils::setAttributes', function () {
  it('should set attributes', function () {
    var node = document.createElement('div');
    setAttributes(node, {
      id: 'foo',
      tabindex: 0
    });

    equal(node.getAttribute('id'), 'foo');
    equal(node.getAttribute('tabindex'), 0);
  });

  it('should hyphenate camelCase attributes', function () {
    var node = document.createElement('div');
    setAttributes(node, {
      dataAttr: 'abc'
    });

    equal(node.getAttribute('data-attr'), 'abc');
  });

  it('should not hyphenate special camelCase attributes', function () {
    var node = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    setAttributes(node, {
      viewBox: '0 0 800 400'
    });

    equal(node.getAttribute('viewBox'), '0 0 800 400');
  });
});
