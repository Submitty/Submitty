import PDFJSAnnotate from '../PDFJSAnnotate';
import config from '../config';
import { appendChild } from '../render/appendChild';
import {
  BORDER_COLOR,
  disableUserSelect,
  enableUserSelect,
  findSVGAtPoint,
  getMetadata,
  convertToSvgRect
} from './utils';

let _enabled = false;
let _type;
let overlay;
let originY;
let originX;

/**
 * Get the current window selection as rects
 *
 * @return {Array} An Array of rects
 */
function getSelectionRects() {
  try {
    let selection = window.getSelection();
    let range = selection.getRangeAt(0);
    let rects = range.getClientRects();

    if (rects.length > 0 &&
        rects[0].width > 0 &&
        rects[0].height > 0) {
      return rects;
    }
  } catch (e) {}
  
  return null;
}

/**
 * Handle document.mousedown event
 *
 * @param {Event} e The DOM event to handle
 */
function handleDocumentMousedown(e) {
  let svg;
  if (_type !== 'area' || !(svg = findSVGAtPoint(e.clientX, e.clientY))) {
    return;
  }

  let rect = svg.getBoundingClientRect();
  originY = e.clientY;
  originX = e.clientX;

  overlay = document.createElement('div');
  overlay.style.position = 'absolute';
  overlay.style.top = `${originY - rect.top}px`;
  overlay.style.left = `${originX - rect.left}px`;
  overlay.style.border = `3px solid ${BORDER_COLOR}`;
  overlay.style.borderRadius = '3px';
  svg.parentNode.appendChild(overlay);
  
  document.addEventListener('mousemove', handleDocumentMousemove);
  disableUserSelect();
}

/**
 * Handle document.mousemove event
 *
 * @param {Event} e The DOM event to handle
 */
function handleDocumentMousemove(e) {
  let svg = overlay.parentNode.querySelector(config.annotationSvgQuery());
  let rect = svg.getBoundingClientRect();

  if (originX + (e.clientX - originX) < rect.right) {
    overlay.style.width = `${e.clientX - originX}px`;
  }

  if (originY + (e.clientY - originY) < rect.bottom) {
    overlay.style.height = `${e.clientY - originY}px`;
  }
}

/**
 * Handle document.mouseup event
 *
 * @param {Event} e The DOM event to handle
 */
function handleDocumentMouseup(e) {
  let rects;
  if (_type !== 'area' && (rects = getSelectionRects())) {
    let svg = findSVGAtPoint(rects[0].left, rects[0].top);
    saveRect(_type, [...rects].map((r) => {
      return {
        top: r.top,
        left: r.left,
        width: r.width,
        height: r.height
      };
    }));
  } else if (_type === 'area' && overlay) {
    let svg = overlay.parentNode.querySelector(config.annotationSvgQuery());
    let rect = svg.getBoundingClientRect();
    saveRect(_type, [{
      top: parseInt(overlay.style.top, 10) + rect.top,
      left: parseInt(overlay.style.left, 10) + rect.left,
      width: parseInt(overlay.style.width, 10),
      height: parseInt(overlay.style.height, 10)
    }]);

    overlay.parentNode.removeChild(overlay);
    overlay = null;

    document.removeEventListener('mousemove', handleDocumentMousemove);
    enableUserSelect();
  }
}

/**
 * Handle document.keyup event
 *
 * @param {Event} e The DOM event to handle
 */
function handleDocumentKeyup(e) {
  // Cancel rect if Esc is pressed
  if (e.keyCode === 27) {
    let selection = window.getSelection();
    selection.removeAllRanges();
    if (overlay && overlay.parentNode) {
      overlay.parentNode.removeChild(overlay);
      overlay = null;
      document.removeEventListener('mousemove', handleDocumentMousemove);
    }
  }
}

/**
 * Save a rect annotation
 *
 * @param {String} type The type of rect (area, highlight, strikeout)
 * @param {Array} rects The rects to use for annotation
 * @param {String} color The color of the rects
 */
function saveRect(type, rects, color) {
  let svg = findSVGAtPoint(rects[0].left, rects[0].top);
  let annotation;

  if (!svg) {
    return;
  }

  let boundingRect = svg.getBoundingClientRect();

  if (!color) {
    if (type === 'highlight') {
      color = 'FFFF00';
    } else if (type === 'strikeout') {
      color = 'FF0000';
    }
  }

  // Initialize the annotation
  annotation = {
    type,
    color,
    rectangles: [...rects].map((r) => {
      let offset = 0;

      if (type === 'strikeout') {
        offset = r.height / 2;
      }

      return convertToSvgRect({
        y: (r.top + offset) - boundingRect.top,
        x: r.left - boundingRect.left,
        width: r.width,
        height: r.height
      }, svg);
    }).filter((r) => r.width > 0 && r.height > 0 && r.x > -1 && r.y > -1)
  };
  
  // Short circuit if no rectangles exist
  if (annotation.rectangles.length === 0) {
    return;
  }

  // Special treatment for area as it only supports a single rect
  if (type === 'area') {
    let rect = annotation.rectangles[0];
    delete annotation.rectangles;
    annotation.x = rect.x;
    annotation.y = rect.y;
    annotation.width = rect.width;
    annotation.height = rect.height;
  }

  let { documentId, pageNumber } = getMetadata(svg);

  // Add the annotation
  PDFJSAnnotate.getStoreAdapter().addAnnotation(documentId, pageNumber, annotation)
    .then((annotation) => {
      appendChild(svg, annotation);
    });
}

/**
 * Enable rect behavior
 */
export function enableRect(type) {
  _type = type;
  
  if (_enabled) { return; }

  _enabled = true;
  document.addEventListener('mouseup', handleDocumentMouseup);
  document.addEventListener('mousedown', handleDocumentMousedown);
  document.addEventListener('keyup', handleDocumentKeyup);
}

/**
 * Disable rect behavior
 */
export function disableRect() {
  if (!_enabled) { return; }

  _enabled = false;
  document.removeEventListener('mouseup', handleDocumentMouseup);
  document.removeEventListener('mousedown', handleDocumentMousedown);
  document.removeEventListener('keyup', handleDocumentKeyup);
}

