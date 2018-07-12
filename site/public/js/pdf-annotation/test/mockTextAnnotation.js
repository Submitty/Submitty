import assign from 'object-assign';
import uuid from '../src/utils/uuid';
import renderText from '../src/render/renderText';

const DEFAULT_TEXT_ANNOTATION = {x: 10, y: 10, size: 12, color: '000', content: 'foo'};

export default function mockTextAnnotation(annotation) {
  let text = renderText(assign(DEFAULT_TEXT_ANNOTATION, annotation));
  text.setAttribute('data-pdf-annotate-id', uuid());
  text.setAttribute('data-pdf-annotate-type', 'textbox');
  return text;
}

export { DEFAULT_TEXT_ANNOTATION };
