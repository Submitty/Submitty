import objectAssign from 'object-assign';
import renderLine from './renderLine';
import renderPath from './renderPath';
import renderPoint from './renderPoint';
import renderRect from './renderRect';
import renderText from './renderText';
import renderCircle from './renderCircle';
import renderArrow from './renderArrow';

const isFirefox = /firefox/i.test(navigator.userAgent);

/**
 * Get the x/y translation to be used for transforming the annotations
 * based on the rotation of the viewport.
 *
 * @param {Object} viewport The viewport data from the page
 * @return {Object}
 */
export function getTranslation(viewport) {
  let x;
  let y;

  // Modulus 360 on the rotation so that we only
  // have to worry about four possible values.
  switch(viewport.rotation % 360) {
    case 0:
      x = y = 0;
      break;
    case 90:
      x = 0;
      y = (viewport.width / viewport.scale) * -1;
      break;
    case 180:
      x = (viewport.width / viewport.scale) * -1;
      y = (viewport.height / viewport.scale) * -1;
      break;
    case 270:
      x = (viewport.height / viewport.scale) * -1;
      y = 0;
      break;
  }

  return { x, y };
}

/**
 * Transform the rotation and scale of a node using SVG's native transform attribute.
 *
 * @param {Node} node The node to be transformed
 * @param {Object} viewport The page's viewport data
 * @return {Node}
 */
function transform(node, viewport) {
  let trans = getTranslation(viewport);

  // Let SVG natively transform the element
  node.setAttribute('transform', `scale(${viewport.scale}) rotate(${viewport.rotation}) translate(${trans.x}, ${trans.y})`);
  
  // Manually adjust x/y for nested SVG nodes
  if (!isFirefox && node.nodeName.toLowerCase() === 'svg') {
    node.setAttribute('x', parseInt(node.getAttribute('x'), 10) * viewport.scale);
    node.setAttribute('y', parseInt(node.getAttribute('y'), 10) * viewport.scale);

    let x = parseInt(node.getAttribute('x', 10));
    let y = parseInt(node.getAttribute('y', 10));
    let width = parseInt(node.getAttribute('width'), 10);
    let height = parseInt(node.getAttribute('height'), 10);
    let path = node.querySelector('path');
    let svg = path.parentNode;
   
    // Scale width/height
    [node, svg, path, node.querySelector('rect')].forEach((n) => {
      n.setAttribute('width', parseInt(n.getAttribute('width'), 10) * viewport.scale);
      n.setAttribute('height', parseInt(n.getAttribute('height'), 10) * viewport.scale);
    });

    // Transform path but keep scale at 100% since it will be handled natively
    transform(path, objectAssign({}, viewport, { scale: 1 }));
    
    switch(viewport.rotation % 360) {
      case 90:
        node.setAttribute('x', viewport.width - y - width);
        node.setAttribute('y', x);
        svg.setAttribute('x', 1);
        svg.setAttribute('y', 0);
        break;
      case 180:
        node.setAttribute('x', viewport.width - x - width);
        node.setAttribute('y', viewport.height - y - height);
        svg.setAttribute('y', 2);
        break;
      case 270:
        node.setAttribute('x', y);
        node.setAttribute('y', viewport.height - x - height);
        svg.setAttribute('x', -1);
        svg.setAttribute('y', 0);
        break;
    }
  }

  return node;
}

/**
 * Append an annotation as a child of an SVG.
 *
 * @param {SVGElement} svg The SVG element to append the annotation to
 * @param {Object} annotation The annotation definition to render and append
 * @param {Object} viewport The page's viewport data
 * @return {SVGElement} A node that was created and appended by this function
 */
export function appendChild(svg, annotation, viewport) {
  if (!viewport) {
    viewport = JSON.parse(svg.getAttribute('data-pdf-annotate-viewport'));
  }
  
  let child;
  switch (annotation.type) {
    case 'area':
    case 'highlight':
      child = renderRect(annotation);
      break;
    case 'circle':
    case 'fillcircle':
    case 'emptycircle':
      child = renderCircle(annotation);
      break;
    case 'strikeout':
      child = renderLine(annotation);
      break;
    case 'point':
      child = renderPoint(annotation);
      break;
    case 'textbox':
      child = renderText(annotation);
      break;
    case 'drawing':
      child = renderPath(annotation);
      break;
    case 'arrow':
      child = renderArrow(annotation);
      break;
  }

  // If no type was provided for an annotation it will result in node being null.
  // Skip appending/transforming if node doesn't exist.
  if (child) {
    // Set attributes
    child.setAttribute('data-pdf-annotate-id', annotation.uuid);
    child.setAttribute('data-pdf-annotate-type', annotation.type);
    child.setAttribute('aria-hidden', true);

    svg.appendChild(transform(child, viewport));
  }

  return child;
}

/**
 * Transform a child annotation of an SVG.
 *
 * @param {SVGElement} svg The SVG element with the child annotation
 * @param {Object} child The SVG child to transform
 * @param {Object} viewport The page's viewport data
 * @return {SVGElement} A node that was transformed by this function
 */
export function transformChild(svg, child, viewport) {
  if (!viewport) {
    viewport = JSON.parse(svg.getAttribute('data-pdf-annotate-viewport'));
  }

  // If no type was provided for an annotation it will result in node being null.
  // Skip transforming if node doesn't exist.
  if (child) {
    child = transform(child, viewport);
  }

  return child;
}

export default {
  /**
   * Get the x/y translation to be used for transforming the annotations
   * based on the rotation of the viewport.
   */
  getTranslation,
  
  /**
   * Append an SVG child for an annotation
   */
  appendChild,

  /**
   * Transform an existing SVG child
   */  
  transformChild
}
