import setAttributes from '../utils/setAttributes';
import normalizeColor from '../utils/normalizeColor';

/**
 * Create SVGRectElements from an annotation definition.
 * This is used for anntations of type `area` and `highlight`.
 *
 * @param {Object} a The annotation definition
 * @return {SVGGElement|SVGRectElement} A group of all rects to be rendered
 */
export default function renderRect(a) {
  if (a.type === 'highlight') {
    let group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    setAttributes(group, {
      fill: normalizeColor(a.color || '#ff0'),
      fillOpacity: 0.2
    });
    
    a.rectangles.forEach((r) => {
      group.appendChild(createRect(r));
    });

    return group;
  } else {
    let rect = createRect(a);
    setAttributes(rect, {
      stroke: normalizeColor(a.color || '#f00'),
      fill: 'none'
    });

    return rect;
  }
}

function createRect(r) {
  let rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');

  setAttributes(rect, {
    x: r.x,
    y: r.y,
    width: r.width,
    height: r.height
  });

  return rect;
}
