import insertElementWithinElement from '../../src/a11y/insertElementWithinElement';
import mockPageWithTextLayer, { CHAR_WIDTH } from '../mockPageWithTextLayer';
import { equal } from 'assert';

function createElement(content) {
  let el = document.createElement('div');
  el.innerHTML = content;
  return el;
}

let page;
let rect;

describe('a11y::insertElementWithinElement', function () {
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

  it('should insert an element within another element', function () {
    let el = createElement();
    let result = insertElementWithinElement(el, rect.left + 10 + (CHAR_WIDTH * 5), rect.top + 15, 1);
    equal(result, true);
  });
  
  it('should not insert if no element can be found', function () {
    let el = createElement();
    let result = insertElementWithinElement(el, rect.left, rect.top + 25, 1);
    equal(result, false);
  });

  it('should insert an element at the proper point', function () {
    let el = createElement('hello');
    let textLayer = page.querySelector('.textLayer');
    insertElementWithinElement(el, rect.left + 10 + (CHAR_WIDTH * (process.env.CI === 'true' ? 6 : 5)), rect.top + 15, 1);
    let node = textLayer.children[0];
    equal(node.innerHTML, 'abcde<div>hello</div>fghijklmnopqrstuvwxyz');
  });

  it('should not insert within a nested element', function () {
    let el = createElement('hello');
    let textLayer = page.querySelector('.textLayer');
    let node = textLayer.children[0];
    node.innerHTML = node.innerHTML.replace('ef', 'e<img>f');
    insertElementWithinElement(el, rect.left + 10 + (CHAR_WIDTH * (process.env.CI === 'true' ? 6 : 5)), rect.top + 15, 1);
    equal(node.innerHTML, 'abcde<div>hello</div><img>fghijklmnopqrstuvwxyz');
  });
});
