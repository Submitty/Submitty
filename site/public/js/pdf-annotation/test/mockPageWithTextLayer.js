import mockSVGContainer from './mockSVGContainer';
import { createPage } from '../src/UI/page';

const ALPHA = Array(26).fill(0).map((c, i) => String.fromCharCode(i + 97));
const TEXT_LAYER_TEMPLATE = Array(5).fill(0).map((c, i) => {
  let left = i % 2 == 0 ? 10 : 20;
  let top = (i + 1) * 10;
  return `<div data-canvas-width="100" style="position:absolute; left:${left}px; top:${top}px;">${ALPHA.join('')}</div>`;
}).join('\n');

// Width of a single character using Courier New font family at 10px font size
export const CHAR_WIDTH = 8;

export default function mockPageWithTextLayer(pageNumber = 1) {
  let page = createPage(pageNumber);
  let textLayer = page.querySelector('.textLayer');
  let annotationLayer = mockSVGContainer()

  page.replaceChild(annotationLayer, page.querySelector('.annotationLayer'));

  // Setup text layer to make text size predictable
  page.style.padding = 0;
  page.style.margin = 0;
  textLayer.innerHTML = TEXT_LAYER_TEMPLATE;
  textLayer.style.padding = 0;
  textLayer.style.margin = 0;
  textLayer.style.fontFamily = 'Courier';
  textLayer.style.fontSize = '10px';
  textLayer.style.lineHeight = '10px';
  
  // CSS isn't loaded so manually position layers
  textLayer.style.position = 'absolute';
  annotationLayer.style.position = 'absolute';
  ['top', 'left', 'right', 'bottom'].forEach((prop) => {
    textLayer.style[prop] = 0;
    annotationLayer.style[prop] = 0;
  });

  return page;
}
