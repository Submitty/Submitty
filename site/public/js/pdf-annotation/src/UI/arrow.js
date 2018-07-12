import PDFJSAnnotate from '../PDFJSAnnotate';
import { appendChild } from '../render/appendChild';
import {
  disableUserSelect,
  enableUserSelect,
  findSVGAtPoint,
  findSVGContainer,
  getMetadata,
  convertToSvgPoint,
  convertToScreenPoint,
  findAnnotationAtPoint
} from './utils';

let _enabled = false;
let _penSize;
let _penColor;
let path;
let lines;
let originY;
let originX;

/**
 * Handle document.mousedown event
 */
function handleDocumentMousedown(e) {
  let target = findAnnotationAtPoint(e.clientX, e.clientY);
  if (target === null)
    return;

  let type = target.getAttribute('data-pdf-annotate-type');
  if (type !== 'circle' && type !== 'fillcircle' && type !== 'emptycircle') {
    return;
  }

  let svg = findSVGContainer(target);
  let { documentId } = getMetadata(svg);
  let annotationId = target.getAttribute('data-pdf-annotate-id');

  let event = e;
  PDFJSAnnotate.getStoreAdapter().getAnnotation(documentId, annotationId).then((annotation) => {
    if (annotation) {
      path = null;
      lines = [];

      let point = convertToScreenPoint([
        annotation.cx,
        annotation.cy
      ], svg);

      let rect = svg.getBoundingClientRect();

      originX = point[0] + rect.left;
      originY = point[1] + rect.top;    

      document.addEventListener('mousemove', handleDocumentMousemove);
      document.addEventListener('mouseup', handleDocumentMouseup);
    }
  });
}

/**
 * Handle document.mouseup event
 *
 * @param {Event} e The DOM event to be handled
 */
function handleDocumentMouseup(e) {
  let svg;
  if (lines.length > 1 && (svg = findSVGAtPoint(e.clientX, e.clientY))) {
    let { documentId, pageNumber } = getMetadata(svg);

    PDFJSAnnotate.getStoreAdapter().addAnnotation(documentId, pageNumber, {
        type: 'arrow',
        width: _penSize,
        color: _penColor,
        lines
      }
    ).then((annotation) => {
      if (path) {
        svg.removeChild(path);
      }

      appendChild(svg, annotation);
    });
  }

  document.removeEventListener('mousemove', handleDocumentMousemove);
  document.removeEventListener('mouseup', handleDocumentMouseup);
}

/**
 * Handle document.mousemove event
 *
 * @param {Event} e The DOM event to be handled
 */
function handleDocumentMousemove(e) {
  let x = lines.length === 0 ? originX : e.clientX;
  let y = lines.length === 0 ? originY : e.clientY;

  savePoint(x, y);
}

/**
 * Handle document.keyup event
 *
 * @param {Event} e The DOM event to be handled
 */
function handleDocumentKeyup(e) {
  // Cancel rect if Esc is pressed
  if (e.keyCode === 27) {
    lines = null;
    path.parentNode.removeChild(path);
    document.removeEventListener('mousemove', handleDocumentMousemove);
    document.removeEventListener('mouseup', handleDocumentMouseup);
  }
}

/**
 * Save a point to the line being drawn.
 *
 * @param {Number} x The x coordinate of the point
 * @param {Number} y The y coordinate of the point
 */
function savePoint(x, y) {
  let svg = findSVGAtPoint(x, y);
  if (!svg) {
    return;
  }

  let rect = svg.getBoundingClientRect();
  let point = convertToSvgPoint([
    x - rect.left,
    y - rect.top
  ], svg);

  if (lines.length < 2) {
    lines.push(point);
    return;
  } else {
    lines[1] = point; // update end point
  }

  if (path) {
    svg.removeChild(path);
  }

  path = appendChild(svg, {
    type: 'arrow',
    color: _penColor,
    width: _penSize,
    lines
  });
}

/**
 * Set the attributes of the pen.
 *
 * @param {Number} penSize The size of the lines drawn by the pen
 * @param {String} penColor The color of the lines drawn by the pen
 */
export function setArrow(penSize = 10, penColor = '0000FF') {
  _penSize = parseInt(penSize, 10);
  _penColor = penColor;
}

/**
 * Enable the pen behavior
 */
export function enableArrow() {
  if (_enabled) { return; }

  _enabled = true;
  document.addEventListener('mousedown', handleDocumentMousedown);
  document.addEventListener('keyup', handleDocumentKeyup);
  disableUserSelect();
}

/**
 * Disable the pen behavior
 */
export function disableArrow() {
  if (!_enabled) { return; }

  _enabled = false;
  document.removeEventListener('mousedown', handleDocumentMousedown);
  document.removeEventListener('keyup', handleDocumentKeyup);
  enableUserSelect();
}

