import { appendChild } from '../../src/render/appendChild';
import mockViewport from '../mockViewport';
import { equal } from 'assert';

const isFirefox = /Firefox/i.test(navigator.userAgent);

function testScale(scale = 0.5, passViewportArg = true) {
  viewport = mockViewport(undefined, undefined, scale);
  svg.setAttribute('data-pdf-annotate-viewport', JSON.stringify(viewport));

  let annotation = {
    type: 'point',
    x: 200,
    y: 100
  };

  let nested = appendChild(svg, annotation, passViewportArg ? viewport : undefined);

  if (isFirefox) {
    equal(nested.getAttribute('x'), annotation.x);
    equal(nested.getAttribute('y'), annotation.y);
    equal(nested.querySelector('path').getAttribute('transform'), null);
  } else {
    equal(nested.getAttribute('x'), annotation.x * scale);
    equal(nested.getAttribute('y'), annotation.y * scale);
    equal(nested.querySelector('path').getAttribute('transform'), `scale(1) rotate(0) translate(0, 0)`);
  }
}

function testRotation(rotation, transX, transY) {
  viewport = mockViewport(undefined, undefined, undefined, rotation);
  let annotation = {
    type: 'point',
    x: 200,
    y: 100
  };
  let node = appendChild(svg, annotation, viewport);
  let width = parseInt(node.getAttribute('width'), 10);
  let height = parseInt(node.getAttribute('height'), 10);
  let expectX = annotation.x;
  let expectY = annotation.y;

  switch(viewport.rotation % 360) {
    case 90:
      expectX = viewport.width - annotation.y - width;
      expectY = annotation.x;
      break;
    case 180:
      expectX = viewport.width - annotation.x - width;
      expectY = viewport.height - annotation.y - height;
      break;
    case 270:
      expectX = annotation.y;
      expectY = viewport.height - annotation.x - height;
      break;
  }

  if (isFirefox) {
    equal(node.getAttribute('transform'), `scale(1) rotate(${rotation}) translate(${transX}, ${transY})`);
    equal(parseInt(node.getAttribute('x'), 10), annotation.x);
    equal(parseInt(node.getAttribute('y'), 10), annotation.y);
  } else {
    equal(node.getAttribute('transform'), `scale(1) rotate(${rotation}) translate(${transX}, ${transY})`);
    equal(parseInt(node.getAttribute('x'), 10), expectX);
    equal(parseInt(node.getAttribute('y'), 10), expectY);
  }
}

let svg;
let viewport;

describe('render::appendChild', function () {
  beforeEach(function () {
    svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    viewport = mockViewport();
  });

  it('should add data-attributes', function () {
    let point = appendChild(svg, {
      uuid: 1234,
      type: 'point',
      x: 0,
      y: 0
    }, viewport);
    let area = appendChild(svg, {
      uuid: 5678,
      type: 'area',
      x: 0,
      y: 0,
      width: 25,
      height: 25
    }, viewport);

    equal(point.getAttribute('data-pdf-annotate-id'), '1234');
    equal(point.getAttribute('data-pdf-annotate-type'), 'point');
    equal(area.getAttribute('data-pdf-annotate-id'), '5678');
    equal(area.getAttribute('data-pdf-annotate-type'), 'area');
  });

  it('should render area', function () {
    let area = appendChild(svg, {
      type: 'area',
      x: 125,
      y: 225,
      width: 100,
      height: 50
    }, viewport);

    equal(area.nodeName.toLowerCase(), 'rect');
  });

  it('should render highlight', function () {
    let highlight = appendChild(svg, {
      type: 'highlight',
      color: 'FF0000',
      rectangles: [
        {
          x: 1,
          y: 1,
          width: 50,
          height: 50
        }
      ]
    }, viewport);

    equal(highlight.nodeName.toLowerCase(), 'g');
    equal(highlight.children.length, 1);
    equal(highlight.children[0].nodeName.toLowerCase(), 'rect');
  });

  it('should render strikeout', function () {
    let strikeout = appendChild(svg, {
      type: 'strikeout',
      color: 'FF0000',
      rectangles: [{
        x: 125,
        y: 320,
        width: 270,
        height: 1
      }],
    }, viewport);

    equal(strikeout.nodeName.toLowerCase(), 'g');
    equal(strikeout.children.length, 1);
    equal(strikeout.children[0].nodeName.toLowerCase(), 'line');
  });

  it('should render textbox', function () {

    let textboxGroup = appendChild(svg, {
      type: 'textbox',
      x: 125,
      y: 400,
      width: 50,
      height: 100,
      size: 20,
      color: '000000',
      content: 'Lorem Ipsum'
    }, viewport);
    let textbox = textboxGroup.firstChild;

    equal(textbox.nodeName.toLowerCase(), 'text');
  });

  it('should render point', function () {
    let point = appendChild(svg, {
      type: 'point',
      x: 5,
      y: 5
    }, viewport);

    equal(point.nodeName.toLowerCase(), 'svg');
  });

  it('should render drawing', function () {
    let drawing = appendChild(svg, {
      type: 'drawing',
      x: 10,
      y: 10,
      lines: [[0, 0], [1, 1]]
    }, viewport);

    equal(drawing.nodeName.toLowerCase(), 'path');
  });

  it('should fail gracefully if no type is provided', function () {
    let error = false;
    try {
      appendChild(svg, { x: 1, y: 1 }, viewport);
    } catch (e) {
      error = true;
    }

    equal(error, false);
  });

  it('should transform scale', function () { testScale(0.5); });
  it('should use viewport from svg data-attribute', function () { testScale(0.5, false); });

  it('should transform rotation 0', function () { testRotation(0, 0, 0); });
  it('should transform rotation 90', function () { testRotation(90, 0, -100); });
  it('should transform rotation 180', function () { testRotation(180, -100, -100); });
  it('should transform rotation 270', function () { testRotation(270, -100, 0); });
  it('should transform rotation 360', function () { testRotation(360, 0, 0); });
  it('should transform rotation 540', function () { testRotation(540, -100, -100); });
});
