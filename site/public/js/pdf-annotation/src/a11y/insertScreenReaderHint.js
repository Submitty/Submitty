import createScreenReaderOnly from './createScreenReaderOnly';
import insertElementWithinChildren from './insertElementWithinChildren';
import insertElementWithinElement from './insertElementWithinElement';
import renderScreenReaderComments from './renderScreenReaderComments';

// Annotation types that support comments
const COMMENT_TYPES = ['highlight', 'point', 'area','circle','emptycircle','fillcircle'];

/**
 * Insert a hint into the DOM for screen readers for a specific annotation.
 *
 * @param {Object} annotation The annotation to insert a hint for
 * @param {Number} num The number of the annotation out of all annotations of the same type
 */
export default function insertScreenReaderHint(annotation, num = 0) {
  switch (annotation.type) {
    case 'highlight':
    case 'strikeout':
      let rects = annotation.rectangles;
      let first = rects[0];
      let last = rects[rects.length - 1];

      insertElementWithinElement(
        createScreenReaderOnly(`Begin ${annotation.type} annotation ${num}`, annotation.uuid),
        first.x, first.y, annotation.page, true
      );

      insertElementWithinElement(
        createScreenReaderOnly(`End ${annotation.type} annotation ${num}`, `${annotation.uuid}-end`),
        last.x + last.width, last.y, annotation.page, false
      );
      break;

    case 'textbox':
    case 'point':
      let text = annotation.type === 'textbox' ? ` (content: ${annotation.content})` : '';

      insertElementWithinChildren(
        createScreenReaderOnly(`${annotation.type} annotation ${num}${text}`, annotation.uuid),
        annotation.x, annotation.y, annotation.page
      );
      break;

    case 'drawing':
    case 'area':
      let x = typeof annotation.x !== 'undefined' ? annotation.x : annotation.lines[0][0];
      let y = typeof annotation.y !== 'undefined' ? annotation.y : annotation.lines[0][1];

      insertElementWithinChildren(
        createScreenReaderOnly(`Unlabeled drawing`, annotation.uuid),
        x, y, annotation.page
      );
      break;

    case 'circle':
    case 'fillcircle':
    case 'emptycircle':
      let x2 = typeof annotation.cx !== 'undefined' ? annotation.cx : annotation.lines[0][0];
      let y2 = typeof annotation.cy !== 'undefined' ? annotation.cy : annotation.lines[0][1];

      insertElementWithinChildren(
        createScreenReaderOnly(`Unlabeled drawing`, annotation.uuid),
        x2, y2, annotation.page
      );
      break;
  }

  // Include comments in screen reader hint
  if (COMMENT_TYPES.includes(annotation.type)) {
    renderScreenReaderComments(annotation.documentId, annotation.uuid);
  }
}
