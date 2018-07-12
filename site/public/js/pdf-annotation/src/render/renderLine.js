import setAttributes from '../utils/setAttributes';
import normalizeColor from '../utils/normalizeColor';

/**
 * Create SVGLineElements from an annotation definition.
 * This is used for anntations of type `strikeout`.
 *
 * @param {Object} a The annotation definition
 * @return {SVGGElement} A group of all lines to be rendered
 */
export default function renderLine(a) {
  let group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
  setAttributes(group, {
    stroke: normalizeColor(a.color || '#f00'),
    strokeWidth: 1
  });

  a.rectangles.forEach((r) => {
    let line = document.createElementNS('http://www.w3.org/2000/svg', 'line');

    setAttributes(line, {
      x1: r.x,
      y1: r.y,
      x2: r.x + r.width,
      y2: r.y,
    });

    group.appendChild(line);
  });

  return group;
}
