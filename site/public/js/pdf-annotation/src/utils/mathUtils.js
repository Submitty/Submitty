// Transform point by matrix
//
export function applyTransform(p, m) {
  var xt = p[0] * m[0] + p[1] * m[2] + m[4];
  var yt = p[0] * m[1] + p[1] * m[3] + m[5];
  return [xt, yt];
};

// Transform point by matrix inverse
//
export function applyInverseTransform(p, m) {
  var d = m[0] * m[3] - m[1] * m[2];
  var xt = (p[0] * m[3] - p[1] * m[2] + m[2] * m[5] - m[4] * m[3]) / d;
  var yt = (-p[0] * m[1] + p[1] * m[0] + m[4] * m[1] - m[5] * m[0]) / d;
  return [xt, yt];
};


// Concatenates two transformation matrices together and returns the result.
export function transform(m1, m2) {
  return [
    m1[0] * m2[0] + m1[2] * m2[1],
    m1[1] * m2[0] + m1[3] * m2[1],
    m1[0] * m2[2] + m1[2] * m2[3],
    m1[1] * m2[2] + m1[3] * m2[3],
    m1[0] * m2[4] + m1[2] * m2[5] + m1[4],
    m1[1] * m2[4] + m1[3] * m2[5] + m1[5]
  ];
};

export function translate(m, x, y) {
  return [
    m[0],
    m[1],
    m[2],
    m[3],
    m[0] * x + m[2] * y + m[4],
    m[1] * x + m[3] * y + m[5]
  ];
};


export function rotate(m, angle) {
  angle = angle * Math.PI / 180;

  var cosValue = Math.cos(angle);
  var sinValue = Math.sin(angle);

  return [
    m[0] * cosValue + m[2] * sinValue,
    m[1] * cosValue + m[3] * sinValue,
    m[0] * (-sinValue) + m[2] * cosValue,
    m[1] * (-sinValue) + m[3] * cosValue,
    m[4],
    m[5]
  ];
};

export function scale(m, x, y) {
  return [
    m[0] * x,
    m[1] * x,
    m[2] * y,
    m[3] * y,
    m[4],
    m[5]
  ];
};
  
function getInverseTransform(m) {
  var d = m[0] * m[3] - m[1] * m[2];
  return [m[3] / d, -m[1] / d, -m[2] / d, m[0] / d,
    (m[2] * m[5] - m[4] * m[3]) / d, (m[4] * m[1] - m[5] * m[0]) / d];
};


export function makePoint(x, y, z) {
  return { x: x, y: y, z: z }
}

export function makeVector(xcoord, ycoord, zcoord) {
  return { xcoord: xcoord, ycoord: ycoord, zcoord: zcoord }
}

export function makeVectorFromPoints(pt1, pt2)
{
  let xcoord = pt2.x - pt1.x;
  let ycoord = pt2.y - pt1.y;
  let zcoord = pt2.z - pt1.z;
  return makeVector(xcoord, ycoord, zcoord);
}

export function addVector(pt, v) {
  return makePoint(pt.x + v.xcoord, pt.y + v.ycoord, pt.z + v.zcoord);
}

export function multiplyVector(v, scalar) {
  return makeVector(v.xcoord * scalar, v.ycoord * scalar, v.zcoord * scalar);
}

export function magnitude(v)
{
  return Math.sqrt(
    Math.pow(v.xcoord, 2) + Math.pow(v.ycoord, 2) + Math.pow(v.zcoord, 2)
  );
}

export function negateVector(v) {
  return multiplyVector(v, -1);
}

export function unitVector(v) {
  let mag = magnitude(v);
  let xcoord = v.xcoord / mag;
  let ycoord = v.ycoord / mag;
  let zcoord = v.zcoord / mag;
  return makeVector(xcoord, ycoord, zcoord);
} 

export function crossProduct(u, v) {
  //
  // u X v = < u2*v3 - u3*v2,
  //           u3*v1 - u1*v3,
  //           u1*v2 - u2*v1 >
  let xcoord = u.ycoord * v.zcoord - u.zcoord * v.ycoord;
  let ycoord = u.zcoord * v.xcoord - u.xcoord * v.zcoord;
  let zcoord = u.xcoord * v.ycoord - u.ycoord * v.xcoord;
  return makeVector(xcoord, ycoord, zcoord);
}

