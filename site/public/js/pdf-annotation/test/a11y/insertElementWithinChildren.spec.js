import insertElementWithinChildren from '../../src/a11y/insertElementWithinChildren';
import mockPageWithTextLayer, { CHAR_WIDTH } from '../mockPageWithTextLayer';
import { equal } from 'assert';

function createElement(content) {
  let el = document.createElement('div');
  el.innerHTML = content;
  return el;
}

let page;
let rect;

describe('a11y::insertElementWithinChildren', function () {
  beforeEach(function () {
    page = mockPageWithTextLayer();
    document.body.appendChild(page);
    rect = page.querySelector('.textLayer').getBoundingClientRect();
  });

  afterEach(function () {
    if (page && page.parentNode) {
      page.parentNode.removeChild(page);
    }
  });

  it('should insert element', function () {
    let el = createElement();
    let result = insertElementWithinChildren(el, rect.left, rect.top, 1);
    equal(result, true);
  });
  
  it('should insert an element at the proper point', function () {
    let el = createElement('hello');
    let textLayer = page.querySelector('.textLayer');
    insertElementWithinChildren(el, rect.left, rect.top, 1);
    let node = textLayer.children[0];
    equal(node.innerHTML, 'hello');
  });

  it('should insert within an element if needed', function () {
    let el = createElement('hello');
    let textLayer = page.querySelector('.textLayer');
    insertElementWithinChildren(el, rect.left + 10 + (CHAR_WIDTH * (process.env.CI === 'true' ? 6 : 5)), rect.top + 15, 1);
    let node = textLayer.children[0];
    equal(node.innerHTML, 'abcde<div>hello</div>fghijklmnopqrstuvwxyz');
  });

  it('should insert at the bottom if all else fails', function () {
    let el = createElement('hello');
    let textLayer = page.querySelector('.textLayer');
    let result = insertElementWithinChildren(el, rect.right, rect.bottom, 1);
    let node = textLayer.children[textLayer.children.length - 1];
    equal(result, true);
    equal(node.innerHTML, 'hello');
  });

  it('should fail if outside the box', function () {
    let el = createElement();
    let result = insertElementWithinChildren(el, rect.right + 5, rect.bottom + 5, 1);
    equal(result, false);
  });
});
