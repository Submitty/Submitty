import mockViewport from './mockViewport';

export default (documentId = 'test-document-id', pageNumber = '1') => {
  let svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'annotationLayer');
  svg.setAttribute('data-pdf-annotate-container', 'true');
  svg.setAttribute('data-pdf-annotate-document', documentId);
  svg.setAttribute('data-pdf-annotate-page', pageNumber);
  svg.setAttribute('data-pdf-annotate-viewport', JSON.stringify(mockViewport()));
  return svg;
}
