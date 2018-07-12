import insertScreenReaderHint from './insertScreenReaderHint';
import renderScreenReaderHints from './renderScreenReaderHints';
import insertScreenReaderComment from './insertScreenReaderComment';
import renderScreenReaderComments from './renderScreenReaderComments';
import { addEventListener } from '../UI/event';
import PDFJSAnnotate from '../PDFJSAnnotate';

/**
 * Initialize the event handlers for keeping screen reader hints synced with data
 */
export default function initEventHandlers() {
  addEventListener('annotation:add', (documentId, pageNumber, annotation) => {
    reorderAnnotationsByType(documentId, pageNumber, annotation.type);
  });
  addEventListener('annotation:edit', (documentId, annotationId, annotation) => {
    reorderAnnotationsByType(documentId, annotation.page, annotation.type);
  });
  addEventListener('annotation:delete', removeAnnotation);
  addEventListener('comment:add', insertComment);
  addEventListener('comment:delete', removeComment);
}

/**
 * Reorder the annotation numbers by annotation type
 *
 * @param {String} documentId The ID of the document
 * @param {Number} pageNumber The page number of the annotations
 * @param {Strig} type The annotation type
 */
function reorderAnnotationsByType(documentId, pageNumber, type) {
  PDFJSAnnotate.getStoreAdapter().getAnnotations(documentId, pageNumber)
    .then((annotations) => {
      return annotations.annotations.filter((a) => {
        return a.type === type;
      });
    })
    .then((annotations) => {
      annotations.forEach((a) => {
        removeAnnotation(documentId, a.uuid);
      });

      return annotations;
    })
    .then(renderScreenReaderHints);
}

/**
 * Remove the screen reader hint for an annotation
 *
 * @param {String} documentId The ID of the document
 * @param {String} annotationId The Id of the annotation
 */
function removeAnnotation(documentId, annotationId) {
  removeElementById(`pdf-annotate-screenreader-${annotationId}`);
  removeElementById(`pdf-annotate-screenreader-${annotationId}-end`);
}

/**
 * Insert a screen reader hint for a comment
 *
 * @param {String} documentId The ID of the document
 * @param {String} annotationId The ID of tha assocated annotation
 * @param {Object} comment The comment to insert a hint for
 */
function insertComment(documentId, annotationId, comment) {
  let list = document.querySelector(`pdf-annotate-screenreader-comment-list-${annotationId}`);
  let promise;

  if (!list) {
    promise = renderScreenReaderComments(documentId, annotationId, []).then(() => {
      list = document.querySelector(`pdf-annotate-screenreader-comment-list-${annotationId}`);
      return true;
    });
  } else {
    promise = Promise.resolve(true);
  }

  promise.then(() => {
    insertScreenReaderComment(comment);
  });
}

/**
 * Remove a screen reader hint for a comment
 *
 * @param {String} documentId The ID of the document
 * @param {String} commentId The ID of the comment
 */
function removeComment(documentId, commentId) {
  removeElementById(`pdf-annotate-screenreader-comment-${commentId}`);
}

/**
 * Remove an element from the DOM by it's ID if it exists
 *
 * @param {String} elementID The ID of the element to be removed
 */
function removeElementById(elementId) {
  let el = document.getElementById(elementId);
  if (el) {
    el.parentNode.removeChild(el);
  }
}
