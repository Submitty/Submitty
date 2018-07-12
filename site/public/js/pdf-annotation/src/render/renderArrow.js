import setAttributes from '../utils/setAttributes';
import normalizeColor from '../utils/normalizeColor';
import { 
  makePoint, makeVector, makeVectorFromPoints,
  magnitude, unitVector, crossProduct,
  addVector, multiplyVector, negateVector
} from '../utils/mathUtils';

/**
 * Create SVGPathElement from an annotation definition.
 * This is used for anntations of type `drawing`.
 *
 * @param {Object} a The annotation definition
 * @return {SVGPathElement} The path to be rendered
 */
export default function renderArrow(a) {
  let d = [];
  let arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');

  if (a.lines.length == 2) {
    let p1 = a.lines[0];
    let p2 = a.lines[a.lines.length - 1];

    let arrowLength = 40;
    let pt0 = makePoint(p1[0], p1[1], 0);
    let pt1 = makePoint(p2[0], p2[1], 0);
    let x = makeVectorFromPoints(pt0, pt1);
    let unitX = unitVector(x);
    pt1 = addVector(pt0, multiplyVector(unitX, arrowLength));
    x = makeVectorFromPoints(pt0, pt1);
    let unitZ = makeVector(0, 0, 1);
    let unitY = unitVector(crossProduct(unitX, unitZ));
    let thickness = a.width || 10;

    let A = addVector(pt0, multiplyVector(unitY, thickness * 0.5)); 
    let B = addVector(A, multiplyVector(unitX, magnitude(x) - thickness * 2.0)); 
    let C = addVector(B, multiplyVector(unitY, thickness)); 
    let D = pt1;
    let G = addVector(pt0, multiplyVector(negateVector(unitY), thickness * 0.5)); 
    let F = addVector(G, multiplyVector(unitX, magnitude(x) - thickness * 2.0)); 
    let E = addVector(F, multiplyVector(negateVector(unitY), thickness)); 

    let points = '' + 
      A.x + ',' + A.y + ' ' +
      B.x + ',' + B.y + ' ' +
      C.x + ',' + C.y + ' ' +
      D.x + ',' + D.y + ' ' +
      E.x + ',' + E.y + ' ' +
      F.x + ',' + F.y + ' ' +
      G.x + ',' + G.y

    setAttributes(arrow, {
      points: points,
      stroke: normalizeColor(a.color || '#000'),
      fill: normalizeColor(a.color || '#000')
    });
  }

  return arrow;
}
