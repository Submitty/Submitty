import EventEmitter from 'events';
import {
  findAnnotationAtPoint,
  findSVGAtPoint
} from './utils';

const emitter = new EventEmitter;

let clickNode;

/**
 * Handle document.click event
 *
 * @param {Event} e The DOM event to be handled
 */
document.addEventListener('click', function handleDocumentClick(e) {
  if (!findSVGAtPoint(e.clientX, e.clientY)) { return; }

  let target = findAnnotationAtPoint(e.clientX, e.clientY);

  // Emit annotation:blur if clickNode is no longer clicked
  if (clickNode && clickNode !== target) {
    emitter.emit('annotation:blur', clickNode);
  }

  // Emit annotation:click if target was clicked
  if (target) {
    emitter.emit('annotation:click', target);
  }

  clickNode = target;
});

// let mouseOverNode;
// document.addEventListener('mousemove', function handleDocumentMousemove(e) {
//   let target = findAnnotationAtPoint(e.clientX, e.clientY);
//
//   // Emit annotation:mouseout if target was mouseout'd
//   if (mouseOverNode && !target) {
//     emitter.emit('annotation:mouseout', mouseOverNode);
//   }
//
//   // Emit annotation:mouseover if target was mouseover'd
//   if (target && mouseOverNode !== target) {
//     emitter.emit('annotation:mouseover', target);
//   }
//
//   mouseOverNode = target;
// });

export function fireEvent() { emitter.emit(...arguments); };
export function addEventListener() { emitter.on(...arguments); };
export function removeEventListener() { emitter.removeListener(...arguments); };
