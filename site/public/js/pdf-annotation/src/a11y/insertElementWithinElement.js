import config from '../config'
import {
  pointIntersectsRect,
  scaleUp,
  scaleDown
} from '../UI/utils'; 

/**
 * Insert an element at a point within the document.
 * This algorithm will only insert within an element amidst it's text content.
 *
 * @param {Element} el The element to be inserted
 * @param {Number} x The x coordinate of the point
 * @param {Number} y The y coordinate of the point
 * @param {Number} pageNumber The page number to limit elements to
 * @param {Boolean} insertBefore Whether the element is to be inserted before or after x
 * @return {Boolean} True if element was able to be inserted, otherwise false
 */
export default function insertElementWithinElement(el, x, y, pageNumber, insertBefore) {
  const OFFSET_ADJUST = 2;

  // If inserting before adjust `x` by looking for element a few px to the right
  // Otherwise adjust a few px to the left
  // This is to allow a little tolerance by searching within the box, instead
  // of getting a false negative by testing right on the border.
  x = Math.max(x + (OFFSET_ADJUST * (insertBefore ? 1 : -1)), 0);

  let node = textLayerElementFromPoint(x, y + OFFSET_ADJUST, pageNumber);
  if (!node) {
    return false;
  }
  
  // Now that node has been found inverse the adjustment for `x`.
  // This is done to accomodate tolerance by cutting off on the outside of the
  // text boundary, instead of missing a character by cutting off within.
  x = x + (OFFSET_ADJUST * (insertBefore ? -1 : 1));

  let svg = document.querySelector(`svg[data-pdf-annotate-page="${pageNumber}"]`);
  let left = scaleDown(svg, {left: node.getBoundingClientRect().left}).left - svg.getBoundingClientRect().left;
  let temp = node.cloneNode(true);
  let head = temp.innerHTML.split('');
  let tail = [];

  // Insert temp off screen
  temp.style.position = 'absolute';
  temp.style.top = '-10000px';
  temp.style.left = '-10000px';
  document.body.appendChild(temp);

  while (head.length) {
    // Don't insert within HTML tags
    if (head[head.length - 1] === '>') {
      while(head.length) {
        tail.unshift(head.pop());
        if (tail[0] === '<') {
          break;
        }
      }
    }
    
    // Check if width of temp based on current head value satisfies x
    temp.innerHTML = head.join('');
    let width = scaleDown(svg, {width: temp.getBoundingClientRect().width}).width;
    if (left + width <= x) {
      break;
    }
    tail.unshift(head.pop());
  }
  
  // Update original node with new markup, including element to be inserted
  node.innerHTML = head.join('') + el.outerHTML + tail.join('');
  temp.parentNode.removeChild(temp);

  return true;
}

/**
 * Get a text layer element at a given point on a page
 *
 * @param {Number} x The x coordinate of the point
 * @param {Number} y The y coordinate of the point
 * @param {Number} pageNumber The page to limit elements to
 * @return {Element} First text layer element found at the point
 */
function textLayerElementFromPoint(x, y, pageNumber) {
  let svg = document.querySelector(`svg[data-pdf-annotate-page="${pageNumber}"]`);
  let rect = svg.getBoundingClientRect();
  y = scaleUp(svg, {y}).y + rect.top;
  x = scaleUp(svg, {x}).x + rect.left;
  return [...svg.parentNode.querySelectorAll(config.textClassQuery() + ' [data-canvas-width]')].filter((el) => {
    return pointIntersectsRect(x, y, el.getBoundingClientRect());
  })[0];
}
