import assign from 'object-assign';
import uuid from '../src/utils/uuid';
import renderLine from '../src/render/renderLine';

const DEFAULT_LINE_ANNOTATION = {rectangles: [{x: 10, y: 10, width: 12}]};

export default function mockLineAnnotation(annotation) {
  let line = renderLine(assign(DEFAULT_LINE_ANNOTATION, annotation));
  line.setAttribute('data-pdf-annotate-id', uuid());
  line.setAttribute('data-pdf-annotate-type', 'strikeout');
  return line;
}

export { DEFAULT_LINE_ANNOTATION };
