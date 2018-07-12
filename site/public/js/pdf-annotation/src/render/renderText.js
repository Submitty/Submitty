import setAttributes from '../utils/setAttributes';
import normalizeColor from '../utils/normalizeColor';

/**
 * Create SVGTextElement from an annotation definition.
 * This is used for anntations of type `textbox`.
 *
 * @param {Object} a The annotation definition
 * @return {SVGTextElement} A text to be rendered
 */
export default function renderText(a) {

  // Text should be rendered at 0 degrees relative to
  // document rotation
  let text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
  let x = a.x;
  let y = a.y;

  setAttributes(text, {
    x: x,
    y: y,
    fill: normalizeColor(a.color || '#000'),
    fontSize: a.size,
    transform: `rotate(${a.rotation}, ${x}, ${y})`
  });
  text.innerHTML = a.content;

  var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
  g.appendChild(text);

  return g;
}
