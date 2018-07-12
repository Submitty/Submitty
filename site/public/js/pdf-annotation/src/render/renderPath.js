import setAttributes from '../utils/setAttributes';
import normalizeColor from '../utils/normalizeColor';

/**
 * Create SVGPathElement from an annotation definition.
 * This is used for anntations of type `drawing`.
 *
 * @param {Object} a The annotation definition
 * @return {SVGPathElement} The path to be rendered
 */
export default function renderPath(a) {
  let d = [];
  let path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  
  
  for (let i=0, l=a.lines.length; i<l; i++) {
    var p1 = a.lines[i];
    var p2 = a.lines[i+1];
    if (p2) {
      d.push(`M${p1[0]} ${p1[1]} ${p2[0]} ${p2[1]}`);
    }
  }

/*
  
   if(a.lines.length>2) {
    var p1 = a.lines[0];
    var p2 = a.lines[a.lines.length-1];

    var p3 = []; //arrow 
    var p4 = [];
    var p0 = []; //arrow intersection


 
    if (p2) {
      var k = -(p2[0]-p1[0])/(p2[1]-p1[1]);

      var deltaX = 3;
      p0[0] = p1[0]+0.8*(p2[0]-p1[0]);
      p0[1] = p1[1]+0.8*(p2[1]-p1[1]);

      p3[0] = p0[0] + deltaX;
      p3[1] = p0[1] + k*deltaX;

      p4[0] = p0[0] - deltaX;
      p4[1] = p0[1] - k*deltaX;

      if(Math.abs(p2[1]-p1[1]) < 20) {

        p3[0] = p0[0] ;
        p3[1] = p0[1] + deltaX*1;

        p4[0] = p0[0] ;
        p4[1] = p0[1] - deltaX*1;

      }

      d.push(`M${p1[0]} ${p1[1]} ${p2[0]} ${p2[1]}`);
       //d.push(`M${p1[0]} ${p1[1]} ${p2[0]} ${p2[1]}`);
      d.push(`M${p2[0]} ${p2[1]} ${p3[0]} ${p3[1]}`);
      d.push(`M${p3[0]} ${p3[1]} ${p4[0]} ${p4[1]}`);
      d.push(`M${p4[0]} ${p4[1]} ${p2[0]} ${p2[1]}`);
     }
    }*/
  
  setAttributes(path, {
    d: `${d.join(' ')}Z`,
    stroke: normalizeColor(a.color || '#000'),
    strokeWidth: a.width || 1,
    fill: 'none'
  });

  return path;
}
