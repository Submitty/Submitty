import setAttributes from '../utils/setAttributes';
import normalizeColor from '../utils/normalizeColor';

/**
 * Create an SVGCircleElement from an annotation definition.
 * This is used for annotations of type `circle`.
 *
 * @param {Object} a The annotation definition
 * @return {SVGGElement|SVGCircleElement} A circle to be rendered
 */
export default function renderCircle(a) {
  let circle = createCircle(a);
  let color = normalizeColor(a.color || '#f00')

  if (a.type === 'circle')
    setAttributes(circle, {
      stroke: color,
      fill: 'none',
      'stroke-width': 5
    });
  if (a.type === 'emptycircle')
    setAttributes(circle, {
      stroke: color,
      fill: 'none',
      'stroke-width': 2
    });

  if (a.type === 'fillcircle')
    setAttributes(circle, {
      stroke: color,
      fill: color,
      'stroke-width': 5
    });

  return circle;
}

function createCircle(a) {
  let circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
  setAttributes(circle, {
    cx: a.cx,
    cy: a.cy,
    r: a.r
  });

  return circle;
}
