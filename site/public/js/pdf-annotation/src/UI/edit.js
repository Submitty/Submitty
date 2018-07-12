import PDFJSAnnotate from '../PDFJSAnnotate';
import config from '../config';
import { appendChild } from '../render/appendChild';
import {
  addEventListener,
  removeEventListener
} from './event';
import {
  BORDER_COLOR,
  disableUserSelect,
  enableUserSelect,
  findSVGContainer,
  findSVGAtPoint,
  getOffsetAnnotationRect,
  getMetadata,
  scaleUp,
  convertToSvgPoint
} from './utils';

let _enabled = false;
let isDragging = false, overlay;
let dragOffsetX, dragOffsetY, dragStartX, dragStartY;
const OVERLAY_BORDER_SIZE = 3;

/**
 * Create an overlay for editing an annotation.
 *
 * @param {Element} target The annotation element to apply overlay for
 */
function createEditOverlay(target) {
  destroyEditOverlay();

  overlay = document.createElement('div');
  let anchor = document.createElement('a');
  let parentNode = findSVGContainer(target).parentNode;
  let id = target.getAttribute('data-pdf-annotate-id');
  let rect = getOffsetAnnotationRect(target);
  let styleLeft = rect.left - OVERLAY_BORDER_SIZE;
  let styleTop = rect.top - OVERLAY_BORDER_SIZE;
  
  overlay.setAttribute('id', 'pdf-annotate-edit-overlay');
  overlay.setAttribute('data-target-id', id);
  overlay.style.boxSizing = 'content-box';
  overlay.style.position = 'absolute';
  overlay.style.top = `${styleTop}px`;
  overlay.style.left = `${styleLeft}px`;
  overlay.style.width = `${rect.width}px`;
  overlay.style.height = `${rect.height}px`;
  overlay.style.border = `${OVERLAY_BORDER_SIZE}px solid ${BORDER_COLOR}`;
  overlay.style.borderRadius = `${OVERLAY_BORDER_SIZE}px`;
  overlay.style.zIndex = 20100;

  anchor.innerHTML = '×';
  anchor.setAttribute('href', 'javascript://');
  anchor.style.background = '#fff';
  anchor.style.borderRadius = '20px';
  anchor.style.border = '1px solid #bbb';
  anchor.style.color = '#bbb';
  anchor.style.fontSize = '16px';
  anchor.style.padding = '2px';
  anchor.style.textAlign = 'center';
  anchor.style.textDecoration = 'none';
  anchor.style.position = 'absolute';
  anchor.style.top = '-13px';
  anchor.style.right = '-13px';
  anchor.style.width = '25px';
  anchor.style.height = '25px';
  
  overlay.appendChild(anchor);
  parentNode.appendChild(overlay);
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keyup', handleDocumentKeyup);
  document.addEventListener('mousedown', handleDocumentMousedown);
  anchor.addEventListener('click', deleteAnnotation);
  anchor.addEventListener('mouseover', () => {
    anchor.style.color = '#35A4DC';
    anchor.style.borderColor = '#999';
    anchor.style.boxShadow = '0 1px 1px #ccc';
  });
  anchor.addEventListener('mouseout', () => {
    anchor.style.color = '#bbb';
    anchor.style.borderColor = '#bbb';
    anchor.style.boxShadow = '';
  });
  overlay.addEventListener('mouseover', () => {
    if (!isDragging) { anchor.style.display = ''; }
  });
  overlay.addEventListener('mouseout', () => {
    anchor.style.display = 'none';
  });
}

/**
 * Destroy the edit overlay if it exists.
 */
function destroyEditOverlay() {
  if (overlay) {
    overlay.parentNode.removeChild(overlay);
    overlay = null;
  }

  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keyup', handleDocumentKeyup);
  document.removeEventListener('mousedown', handleDocumentMousedown);
  document.removeEventListener('mousemove', handleDocumentMousemove);
  document.removeEventListener('mouseup', handleDocumentMouseup);
  enableUserSelect();
}

/**
 * Delete currently selected annotation
 */
function deleteAnnotation() {
  if (!overlay) { return; }

  let annotationId = overlay.getAttribute('data-target-id');
  let nodes = document.querySelectorAll(`[data-pdf-annotate-id="${annotationId}"]`);
  let svg = overlay.parentNode.querySelector(config.annotationSvgQuery());
  let { documentId } = getMetadata(svg);

  [...nodes].forEach((n) => {
    n.parentNode.removeChild(n);
  });
  
  PDFJSAnnotate.getStoreAdapter().deleteAnnotation(documentId, annotationId);

  destroyEditOverlay();
}

/**
 * Handle document.click event
 *
 * @param {Event} e The DOM event that needs to be handled
 */
function handleDocumentClick(e) {
  if (!findSVGAtPoint(e.clientX, e.clientY)) { return; }

  // Remove current overlay
  let overlay = document.getElementById('pdf-annotate-edit-overlay');
  if (overlay) {
    if (isDragging || e.target === overlay) {
      return;
    }

    destroyEditOverlay();
  }
}

/**
 * Handle document.keyup event
 *
 * @param {Event} e The DOM event that needs to be handled
 */
function handleDocumentKeyup(e) {
  if (overlay && e.keyCode === 46 &&
      e.target.nodeName.toLowerCase() !== 'textarea' &&
      e.target.nodeName.toLowerCase() !== 'input') {
    deleteAnnotation();
  }
}

/**
 * Handle document.mousedown event
 *
 * @param {Event} e The DOM event that needs to be handled
 */
function handleDocumentMousedown(e) {
  if (e.target !== overlay) { return; }

  // Highlight and strikeout annotations are bound to text within the document.
  // It doesn't make sense to allow repositioning these types of annotations.
  let annotationId = overlay.getAttribute('data-target-id');
  let target = document.querySelector(`[data-pdf-annotate-id="${annotationId}"]`);
  let type = target.getAttribute('data-pdf-annotate-type');

  if (type === 'highlight' || type === 'strikeout') { return; }

  isDragging = true;
  dragOffsetX = e.clientX;
  dragOffsetY = e.clientY;
  dragStartX = overlay.offsetLeft;
  dragStartY = overlay.offsetTop;

  overlay.style.background = 'rgba(255, 255, 255, 0.7)';
  overlay.style.cursor = 'move';
  overlay.querySelector('a').style.display = 'none';

  document.addEventListener('mousemove', handleDocumentMousemove);
  document.addEventListener('mouseup', handleDocumentMouseup);
  disableUserSelect();
}

/**
 * Handle document.mousemove event
 *
 * @param {Event} e The DOM event that needs to be handled
 */
function handleDocumentMousemove(e) {
  let annotationId = overlay.getAttribute('data-target-id');
  let parentNode = overlay.parentNode;
  let rect = parentNode.getBoundingClientRect();
  let y = (dragStartY + (e.clientY - dragOffsetY));
  let x = (dragStartX + (e.clientX - dragOffsetX));
  let minY = 0;
  let maxY = rect.height;
  let minX = 0;
  let maxX = rect.width;

  if (y > minY && y + overlay.offsetHeight < maxY) {
    overlay.style.top = `${y}px`;
  }

  if (x > minX && x + overlay.offsetWidth < maxX) {
    overlay.style.left = `${x}px`;
  }
}

/**
 * Handle document.mouseup event
 *
 * @param {Event} e The DOM event that needs to be handled
 */
function handleDocumentMouseup(e) {
  let annotationId = overlay.getAttribute('data-target-id');
  let target = document.querySelectorAll(`[data-pdf-annotate-id="${annotationId}"]`);
  let type = target[0].getAttribute('data-pdf-annotate-type');
  let svg = overlay.parentNode.querySelector(config.annotationSvgQuery());
  let { documentId } = getMetadata(svg);
  
  overlay.querySelector('a').style.display = '';

  PDFJSAnnotate.getStoreAdapter().getAnnotation(documentId, annotationId).then((annotation) => {
    let attribX = 'x';
    let attribY = 'y';
    if (['circle', 'fillcircle', 'emptycircle'].indexOf(type) > -1) {
      attribX = 'cx';
      attribY = 'cy';
    }
    if (['area', 'highlight', 'point', 'textbox', 'circle', 'fillcircle', 'emptycircle'].indexOf(type) > -1) {
      let modelStart = convertToSvgPoint([dragStartX, dragStartY], svg);
      let modelEnd = convertToSvgPoint([overlay.offsetLeft, overlay.offsetTop], svg);
      let modelDelta = {
        x: modelEnd[0] - modelStart[0],
        y: modelEnd[1] - modelStart[1]
      };

      if (type === 'textbox') {
        target = [target[0].firstChild];
      }

      [...target].forEach((t, i) => {
        let modelX = parseInt(t.getAttribute(attribX), 10);
        let modelY = parseInt(t.getAttribute(attribY), 10);
        if (modelDelta.y !== 0) {
          modelY = modelY + modelDelta.y;
          let viewY = modelY;

          if (type === 'point') {
            viewY = scaleUp(svg, { viewY }).viewY;
          }

          t.setAttribute(attribY, viewY);
          if (annotation.rectangles && i < annotation.rectangles.length) {
            annotation.rectangles[i].y = modelY;
          } else if (annotation[attribY]) {
            annotation[attribY] = modelY;
          }
        }
        if (modelDelta.x !== 0) {
          modelX = modelX + modelDelta.x;
          let viewX = modelX;

          if (type === 'point') {
            viewX = scaleUp(svg, { viewX }).viewX;
          }

          t.setAttribute(attribX, viewX);
          if (annotation.rectangles  && i < annotation.rectangles.length) {
            annotation.rectangles[i].x = modelX;
          } else if (annotation[attribX]) {
            annotation[attribX] = modelX;
          }
        }
      });
    // } else if (type === 'strikeout') {
    //   let { deltaX, deltaY } = getDelta('x1', 'y1');
    //   [...target].forEach(target, (t, i) => {
    //     if (deltaY !== 0) {
    //       t.setAttribute('y1', parseInt(t.getAttribute('y1'), 10) + deltaY);
    //       t.setAttribute('y2', parseInt(t.getAttribute('y2'), 10) + deltaY);
    //       annotation.rectangles[i].y = parseInt(t.getAttribute('y1'), 10);
    //     }
    //     if (deltaX !== 0) {
    //       t.setAttribute('x1', parseInt(t.getAttribute('x1'), 10) + deltaX);
    //       t.setAttribute('x2', parseInt(t.getAttribute('x2'), 10) + deltaX);
    //       annotation.rectangles[i].x = parseInt(t.getAttribute('x1'), 10);
    //     }
    //   });
    } else if (type === 'drawing' || type === 'arrow') {
      let modelStart = convertToSvgPoint([dragStartX, dragStartY], svg);
      let modelEnd = convertToSvgPoint([overlay.offsetLeft, overlay.offsetTop], svg);
      let modelDelta = {
        x: modelEnd[0] - modelStart[0],
        y: modelEnd[1] - modelStart[1]
      };

      annotation.lines.forEach((line, i) => {
        let [x, y] = annotation.lines[i];
        annotation.lines[i][0] = x + modelDelta.x;
        annotation.lines[i][1] = y + modelDelta.y;
      });

      target[0].parentNode.removeChild(target[0]);
      appendChild(svg, annotation);
    } 

    PDFJSAnnotate.getStoreAdapter().editAnnotation(documentId, annotationId, annotation);
  });

  setTimeout(() => {
    isDragging = false;
  }, 0);

  overlay.style.background = '';
  overlay.style.cursor = '';

  document.removeEventListener('mousemove', handleDocumentMousemove);
  document.removeEventListener('mouseup', handleDocumentMouseup);
  enableUserSelect();
}

/**
 * Handle annotation.click event
 *
 * @param {Element} e The annotation element that was clicked
 */
function handleAnnotationClick(target) {
  createEditOverlay(target);
}

/**
 * Enable edit mode behavior.
 */
export function enableEdit () {
  if (_enabled) { return; }

  _enabled = true;
  addEventListener('annotation:click', handleAnnotationClick);
};

/**
 * Disable edit mode behavior.
 */
export function disableEdit () {
  destroyEditOverlay();

  if (!_enabled) { return; }

  _enabled = false;
  removeEventListener('annotation:click', handleAnnotationClick);
};

