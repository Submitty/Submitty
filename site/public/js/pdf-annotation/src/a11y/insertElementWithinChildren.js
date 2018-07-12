import config from '../config';
import insertElementWithinElement from './insertElementWithinElement';
import { pointIntersectsRect } from '../UI/utils';
import { scaleUp } from '../UI/utils'; 

/**
 * Insert an element at a point within the document.
 * This algorithm will try to insert between elements if possible.
 * It will however use `insertElementWithinElement` if it is more accurate.
 *
 * @param {Element} el The element to be inserted
 * @param {Number} x The x coordinate of the point
 * @param {Number} y The y coordinate of the point
 * @param {Number} pageNumber The page number to limit elements to
 * @return {Boolean} True if element was able to be inserted, otherwise false
 */
export default function insertElementWithinChildren(el, x, y, pageNumber) {
  // Try and use most accurate method of inserting within an element
  if (insertElementWithinElement(el, x, y, pageNumber, true)) {
    return true;
  }

  // Fall back to inserting between elements
  let svg = document.querySelector(`svg[data-pdf-annotate-page="${pageNumber}"]`);
  let rect = svg.getBoundingClientRect();
  let nodes = [...svg.parentNode.querySelectorAll(config.textClassQuery() + ' > div')];

  y = scaleUp(svg, {y}).y + rect.top;
  x = scaleUp(svg, {x}).x + rect.left;

  // Find the best node to insert before
  for (let i=0, l=nodes.length; i<l; i++) {
    let n = nodes[i];
    let r = n.getBoundingClientRect();
    if (y <= r.top) {
      n.parentNode.insertBefore(el, n);
      return true;
    }
  }

  // If all else fails try to append to the bottom
  let textLayer = svg.parentNode.querySelector(config.textClassQuery());
  if (textLayer) {
    let textRect = textLayer.getBoundingClientRect();
    if (pointIntersectsRect(x, y, textRect)) {
      textLayer.appendChild(el);
      return true;
    }
  }

  return false;
}
