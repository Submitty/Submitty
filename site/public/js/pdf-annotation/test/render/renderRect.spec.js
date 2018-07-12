import renderRect from '../../src/render/renderRect';
import { equal } from 'assert';

function assertG(g, l, c) {
  equal(g.nodeName, 'g');
  equal(g.children.length, l);

  if (c) {
    equal(g.getAttribute('fill'), `#${c}`);
  } else {
    equal(g.getAttribute('fill'), 'none');
    equal(g.getAttribute('stroke'), '#f00');
  }
}

function assertRect(rect, x, y, w, h) {
  equal(rect.nodeName, 'rect');
  equal(rect.getAttribute('x'), x);
  equal(rect.getAttribute('y'), y);
  equal(rect.getAttribute('width'), w);
  equal(rect.getAttribute('height'), h);
}

describe('render::renderRect', function () {
  it('should render a rect', function () {
    let rect = renderRect({
      type: 'highlight',
      color: '0ff',
      rectangles: [
        {
          x: 50,
          y: 75,
          width: 100,
          height: 125
        }
      ]
    });

    assertG(rect, 1, '0ff');
    assertRect(rect.children[0], 50, 75, 100, 125);
  });

  it('should render multiple rects', function () {
    let rect = renderRect({
      type: 'highlight',
      rectangles: [
        {
          x: 50,
          y: 75,
          width: 100,
          height: 125
        },
        {
          x: 100,
          y: 200,
          width: 300,
          height: 400
        }
      ]
    });

    assertG(rect, 2, 'ff0');
    assertRect(rect.children[0], 50, 75, 100, 125);
    assertRect(rect.children[1], 100, 200, 300, 400);
  });

  it('should render area rect without group', function () {
    let rect = renderRect({
      type: 'area',
      x: 100,
      y: 200,
      width: 300,
      height: 400
    });

    assertRect(rect, 100, 200, 300, 400);
  });
});
