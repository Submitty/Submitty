import createStyleSheet from 'create-stylesheet';
import { getTranslation } from '../render/appendChild';
import { 
  applyTransform, 
  applyInverseTransform, 
  translate,
  rotate,
  scale 
} from '../utils/mathUtils';


export const BORDER_COLOR = '#00BFFF';

const userSelectStyleSheet = createStyleSheet({
  body: {
    '-webkit-user-select': 'none',
       '-moz-user-select': 'none',
        '-ms-user-select': 'none',
            'user-select': 'none'
  }
});
userSelectStyleSheet.setAttribute('data-pdf-annotate-user-select', 'true');

/**
 * Find the SVGElement that contains all the annotations for a page
 *
 * @param {Element} node An annotation within that container
 * @return {SVGElement} The container SVG or null if it can't be found
 */
export function findSVGContainer(node) {
  let parentNode = node;

  while ((parentNode = parentNode.parentNode) &&
          parentNode !== document) {
    if (parentNode.nodeName.toUpperCase() === 'SVG' &&
        parentNode.getAttribute('data-pdf-annotate-container') === 'true') {
      return parentNode;
    }
  }

  return null;
}

/**
 * Find an SVGElement container at a given point
 *
 * @param {Number} x The x coordinate of the point
 * @param {Number} y The y coordinate of the point
 * @return {SVGElement} The container SVG or null if one can't be found
 */
export function findSVGAtPoint(x, y) {
  let elements = document.querySelectorAll('svg[data-pdf-annotate-container="true"]');

  for (let i=0, l=elements.length; i<l; i++) {
    let el = elements[i];
    let rect = el.getBoundingClientRect();

    if (pointIntersectsRect(x, y, rect)) {
      return el;
    }
  }

  return null;
}

/**
 * Find an Element that represents an annotation at a given point.
 * 
 * IMPORTANT: Requires the annotation layer to be the top most element so
 *            either use z-ordering or make it the leaf container.
 *
 * @param {Number} x The x coordinate of the point
 * @param {Number} y The y coordinate of the point
 * @return {Element} The annotation element or null if one can't be found
 */
export function findAnnotationAtPoint(x, y) {
  let el = null;
  var candidate = document.elementFromPoint(x, y)
  while (!el && candidate && candidate !== document) {  
    let type = candidate.getAttribute('data-pdf-annotate-type');
    if (type) {
      el = candidate;
    }
    candidate = candidate.parentNode;
  }
  return el;
}

/**
 * Determine if a point intersects a rect
 *
 * @param {Number} x The x coordinate of the point
 * @param {Number} y The y coordinate of the point
 * @param {Object} rect The points of a rect (likely from getBoundingClientRect)
 * @return {Boolean} True if a collision occurs, otherwise false
 */
export function pointIntersectsRect(x, y, rect) {
  return y >= rect.top && y <= rect.bottom && x >= rect.left && x <= rect.right;
}

/**
 * Get the rect of an annotation element accounting for offset.
 *
 * @param {Element} el The element to get the rect of
 * @return {Object} The dimensions of the element
 */
export function getOffsetAnnotationRect(el) {
  let rect = el.getBoundingClientRect();
  let { offsetLeft, offsetTop } = getOffset(el);
  return {
    top: rect.top - offsetTop,
    left: rect.left - offsetLeft,
    right: rect.right - offsetLeft,
    bottom: rect.bottom - offsetTop,
    width: rect.width,
    height: rect.height
  };
}

/**
 * Adjust scale from normalized scale (100%) to rendered scale.
 *
 * @param {SVGElement} svg The SVG to gather metadata from
 * @param {Object} rect A map of numeric values to scale
 * @return {Object} A copy of `rect` with values scaled up
 */
export function scaleUp(svg, rect) {
  let result = {};
  let { viewport } = getMetadata(svg);

  Object.keys(rect).forEach((key) => {
    result[key] = rect[key] * viewport.scale;
  });

  return result;
}

export function convertToSvgRect(rect, svg, viewport) {
  var pt1 = [rect.x, rect.y];
  var pt2 = [rect.x + rect.width, rect.y + rect.height];

  pt1 = convertToSvgPoint(pt1, svg, viewport);
  pt2 = convertToSvgPoint(pt2, svg, viewport);

  return {
    x: Math.min(pt1[0], pt2[0]),
    y: Math.min(pt1[1], pt2[1]),
    width: Math.abs(pt2[0] - pt1[0]),
    height: Math.abs(pt2[1] - pt1[1])
  };
}

export function convertToSvgPoint(pt, svg, viewport) {
  let result = {};
  viewport = viewport || getMetadata(svg).viewport;

  let xform = [ 1, 0, 0, 1, 0, 0 ];
  xform = scale(xform, viewport.scale, viewport.scale);
  xform = rotate(xform, viewport.rotation);

  let offset = getTranslation(viewport);
  xform = translate(xform, offset.x, offset.y);

  return applyInverseTransform(pt, xform);
}

export function convertToScreenPoint(pt, svg, viewport) {
  let result = {};
  viewport = viewport || getMetadata(svg).viewport;

  let xform = [ 1, 0, 0, 1, 0, 0 ];
  xform = scale(xform, viewport.scale, viewport.scale);
  xform = rotate(xform, viewport.rotation);

  let offset = getTranslation(viewport);
  xform = translate(xform, offset.x, offset.y);

  return applyTransform(pt, xform);
}

/**
 * Adjust scale from rendered scale to a normalized scale (100%).
 *
 * @param {SVGElement} svg The SVG to gather metadata from
 * @param {Object} rect A map of numeric values to scale
 * @return {Object} A copy of `rect` with values scaled down
 */
export function scaleDown(svg, rect) {
  let result = {};
  let { viewport } = getMetadata(svg);

  Object.keys(rect).forEach((key) => {
    result[key] = rect[key] / viewport.scale;
  });

  return result;
}

/**
 * Get the scroll position of an element, accounting for parent elements
 *
 * @param {Element} el The element to get the scroll position for
 * @return {Object} The scrollTop and scrollLeft position
 */
export function getScroll(el) {
  let scrollTop = 0;
  let scrollLeft = 0;
  let parentNode = el;

  while ((parentNode = parentNode.parentNode) &&
          parentNode !== document) {
    scrollTop += parentNode.scrollTop;
    scrollLeft += parentNode.scrollLeft;
  }

  return { scrollTop, scrollLeft };
}

/**
 * Get the offset position of an element, accounting for parent elements
 *
 * @param {Element} el The element to get the offset position for
 * @return {Object} The offsetTop and offsetLeft position
 */
export function getOffset(el) {
  let parentNode = el;

  while ((parentNode = parentNode.parentNode) &&
          parentNode !== document) {
    if (parentNode.nodeName.toUpperCase() === 'SVG') {
      break;
    }
  }

  let rect = parentNode.getBoundingClientRect();

  return { offsetLeft: rect.left, offsetTop: rect.top };
}

/**
 * Disable user ability to select text on page
 */
export function disableUserSelect() {
  if (!userSelectStyleSheet.parentNode) {
    document.head.appendChild(userSelectStyleSheet);
  }
}


/**
 * Enable user ability to select text on page
 */
export function enableUserSelect() {
  if (userSelectStyleSheet.parentNode) {
    userSelectStyleSheet.parentNode.removeChild(userSelectStyleSheet);
  }
}

/**
 * Get the metadata for a SVG container
 *
 * @param {SVGElement} svg The SVG container to get metadata for
 */
export function getMetadata(svg) {
  return {
    documentId: svg.getAttribute('data-pdf-annotate-document'),
    pageNumber: parseInt(svg.getAttribute('data-pdf-annotate-page'), 10),
    viewport: JSON.parse(svg.getAttribute('data-pdf-annotate-viewport'))
  };
}
