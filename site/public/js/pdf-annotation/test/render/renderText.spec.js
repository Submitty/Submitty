import renderText from '../../src/render/renderText';
import { equal } from 'assert';

describe('render::renderText', function () {
  it('should render text', function () {
    const x = 50;
    const y = 100;
    const size = 20;
    const color = '000';
    let gNode = renderText({
      x,
      y,
      size,
      color
    });
    let text = gNode.firstChild

    equal(text.nodeName, 'text');
    equal(text.getAttribute('x'), x);
    equal(text.getAttribute('y'), y );
    equal(text.getAttribute('fill'), `#${color}`);
    equal(text.getAttribute('font-size'), size);
  });
});
