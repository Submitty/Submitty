import renderScreenReaderHints from '../../src/a11y/renderScreenReaderHints';
import mockPageWithTextLayer, { CHAR_WIDTH } from '../mockPageWithTextLayer';
import PDFJSAnnotate from '../../src/PDFJSAnnotate';
import { equal } from 'assert';

const SR_STYLE = 'position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;';
function mockHint(id, content) {
  return `<div style="${SR_STYLE}" id="pdf-annotate-screenreader-${id}">${content}</div>`;
}

let page;
let rect;
let textLayer;
let getComments = PDFJSAnnotate.__storeAdapter.getComments;

describe('a11y::renderScreenReaderHints', function () {
  beforeEach(function () {
    page = mockPageWithTextLayer();
    document.body.appendChild(page);
    textLayer = page.querySelector('.textLayer');
    rect = textLayer.getBoundingClientRect();
    PDFJSAnnotate.__storeAdapter.getComments = () => {
      return Promise.resolve([]);
    }
  });

  afterEach(function () {
    PDFJSAnnotate.__storeAdapter.getComments = getComments;
    if (page && page.parentNode) {
      page.parentNode.removeChild(page);
    }
  });

  describe('render', function () {
    it('should render without annotations', function () {
      let error;

      try {
        renderScreenReaderHints();
      } catch (e) {
        error = e;
      }

      equal(typeof error, 'undefined');
    });

    it('should render with non-array annotations', function () {
      let error;

      try {
        renderScreenReaderHints(null);
      } catch (e) {
        error = e;
      }

      equal(typeof error, 'undefined');
    });

    it('should render highlight', function () {
      renderScreenReaderHints([{
        type: 'highlight',
        page: 1,
        uuid: 12345,
        rectangles: [{
          height: 10,
          width: 10,
          x: 20,
          y: 10
        }]
      }]);

      let target = textLayer.children[0];
      let begin = mockHint(12345, 'Begin highlight annotation 1');
      let end = mockHint('12345-end', 'End highlight annotation 1');
      let result = `a${begin}b${end}cdefghijklmnopqrstuvwxyz`;

      equal(target.innerHTML, result);
    });
    
    it('should render strikeout', function () {
      renderScreenReaderHints([{
        type: 'strikeout',
        page: 1,
        uuid: 12345,
        rectangles: [{
          height: 10,
          width: 50,
          x: (process.env.CI === 'true' ? 60 : 50),
          y: 10
        }]
      }]);
      
      let target = textLayer.children[0];
      let begin = mockHint(12345, 'Begin strikeout annotation 1');
      let end = mockHint('12345-end', 'End strikeout annotation 1');
      let result = `abcde${begin}fghijkl${end}mnopqrstuvwxyz`;

      equal(target.innerHTML, result);
    });
    
    it('should render drawing', function () {
      renderScreenReaderHints([{
        type: 'drawing',
        page: 1,
        uuid: 12345,
        lines: [[0, 11]]
      }]);

      let target = textLayer.children[1];

      equal(target.innerHTML, 'Unlabeled drawing');
    });
    
    it('should render area', function () {
      renderScreenReaderHints([{
        type: 'area',
        page: 1,
        uuid: 12345,
        width: 50,
        x: 0,
        y: 11
      }]);

      let target = textLayer.children[1];

      equal(target.innerHTML, 'Unlabeled drawing');
    });
    
    it('should render textbox', function () {
      renderScreenReaderHints([{
        type: 'textbox',
        page: 1,
        uuid: 12345,
        width: 100,
        height: 10,
        x: 0,
        y: 11,
        content: 'hello'
      }]);

      let target = textLayer.children[1];

      equal(target.innerHTML, 'textbox annotation 1 (content: hello)');
    });
    
    it('should render point', function () {
      renderScreenReaderHints([{
        type: 'point',
        page: 1,
        uuid: 12345,
        x: 0,
        y: 11,
        content: 'hello'
      }]);

      let target = textLayer.children[1];

      equal(target.innerHTML, 'point annotation 1');
    });
  });

  describe('sort', function () {
    it('should sort by point', function () {
      renderScreenReaderHints([{
        type: 'point',
        page: 1,
        uuid: 12345,
        x: 5,
        y: 5,
        content: 'foo'
      }, {
        type: 'point',
        page: 1,
        uuid: 67890,
        x: 0,
        y: 0,
        content: 'foo'
      }]);

      equal(textLayer.children[0].getAttribute('id'), 'pdf-annotate-screenreader-67890');
      equal(textLayer.children[1].getAttribute('id'), 'pdf-annotate-screenreader-12345');
    });
    
    it('should sort by rect point', function () {
      renderScreenReaderHints([{
        type: 'highlight',
        page: 1,
        uuid: 12345,
        rectangles: [{
          width: 10,
          height: 10,
          x: 20,
          y: 30
        }]
      }, {
        type: 'highlight',
        page: 1,
        uuid: 67890,
        rectangles: [{
          width: 10,
          height: 10,
          x: 20,
          y: 10
        }]
      }]);

      let children = textLayer.querySelectorAll('[id^="pdf-annotate-screenreader"]');

      equal(children[0].getAttribute('id'), 'pdf-annotate-screenreader-67890');
      equal(children[2].getAttribute('id'), 'pdf-annotate-screenreader-12345');
    });
    
    it('should sort by line point', function () {
      renderScreenReaderHints([{
        type: 'drawing',
        page: 1,
        uuid: 12345,
        lines: [[5, 5]]
      }, {
        type: 'drawing',
        page: 1,
        uuid: 67890,
        lines: [[0, 0]]
      }]);

      equal(textLayer.children[0].getAttribute('id'), 'pdf-annotate-screenreader-67890');
      equal(textLayer.children[1].getAttribute('id'), 'pdf-annotate-screenreader-12345');
    });
  });  
});
