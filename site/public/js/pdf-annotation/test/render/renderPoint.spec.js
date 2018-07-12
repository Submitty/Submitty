import renderPoint from '../../src/render/renderPoint';
import { equal } from 'assert';

describe('render::renderPoint', function () {
  it('should render a point', function () {
    let point = renderPoint({
      x: 100,
      y: 200
    });

    equal(point.nodeName, 'svg');
    equal(point.getAttribute('x'), 100);
    equal(point.getAttribute('y'), 200);
    equal(point.getAttribute('width'), 25);
    equal(point.getAttribute('height'), 25);
  });
});
