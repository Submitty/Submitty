/**
 * Create a node that is only visible to screen readers
 *
 * @param {String} content The text content that should be read by screen reader
 * @param {String} [annotationId] The ID of the annotation assocaited
 * @return {Element} An Element that is only visible to screen readers
 */
export default function createScreenReaderOnly(content, annotationId) {
  let node = document.createElement('div');
  let text = document.createTextNode(content);
  node.appendChild(text);
  node.setAttribute('id', `pdf-annotate-screenreader-${annotationId}`);
  node.style.position = 'absolute';
  node.style.left = '-10000px';
  node.style.top = 'auto';
  node.style.width = '1px';
  node.style.height = '1px';
  node.style.overflow = 'hidden';
  return node;
}
