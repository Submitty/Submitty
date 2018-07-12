import PDFJSAnnotate from '../PDFJSAnnotate';
import insertScreenReaderComment from './insertScreenReaderComment';

/**
 * Insert the comments into the DOM to be available by screen reader
 *
 * Example output:
 *   <div class="screenReaderOnly">
 *    <div>Begin highlight 1</div>
 *    <ol aria-label="Comments">
 *      <li>Foo</li>
 *      <li>Bar</li>
 *      <li>Baz</li>
 *      <li>Qux</li>
 *    </ol>
 *  </div>
 *  <div>Some highlighted text goes here...</div>
 *  <div class="screenReaderOnly">End highlight 1</div>
 *
 * NOTE: `screenReaderOnly` is not a real class, just used for brevity
 *
 * @param {String} documentId The ID of the document
 * @param {String} annotationId The ID of the annotation
 * @param {Array} [comments] Optionally preloaded comments to be rendered
 * @return {Promise}
 */
export default function renderScreenReaderComments(documentId, annotationId, comments) {
  let promise;

  if (Array.isArray(comments)) {
    promise = Promise.resolve(comments);
  } else {
    promise = PDFJSAnnotate.getStoreAdapter().getComments(documentId, annotationId);
  }

  return promise.then((comments) => {
    // Node needs to be found by querying DOM as it may have been inserted as innerHTML
    // leaving `screenReaderNode` as an invalid reference (see `insertElementWithinElement`).
    let node = document.getElementById(`pdf-annotate-screenreader-${annotationId}`);
    if (node) { 
      let list = document.createElement('ol');
      list.setAttribute('id', `pdf-annotate-screenreader-comment-list-${annotationId}`);
      list.setAttribute('aria-label', 'Comments');
      node.appendChild(list);
      comments.forEach(insertScreenReaderComment);
    }
  });
}
