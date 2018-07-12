import renderPath from '../../src/render/renderPath';
import { equal } from 'assert';

describe('render::renderPath', function () {
  it('should render a path', function () {
    let path = renderPath({
      lines: [[0, 5], [10, 15], [20, 35]]
    });

    equal(path.nodeName, 'path');
    equal(path.getAttribute('d'), 'M0 5 10 15 M10 15 20 35Z');
    equal(path.getAttribute('stroke'), '#000');
    equal(path.getAttribute('stroke-width'), 1);
    equal(path.getAttribute('fill'), 'none');
  });

  it('shohuld render with custom options', function () {
    let path = renderPath({
      color: 'f00',
      width: 5,
      lines: [[0, 1], [1, 2], [2, 3]]
    });

    equal(path.getAttribute('stroke'), '#f00');
    equal(path.getAttribute('stroke-width'), 5);
  });
});
