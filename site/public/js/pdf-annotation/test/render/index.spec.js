import render from '../../src/render';
import mockViewport from '../mockViewport';
import { equal } from 'assert';
import uuid from "../../src/utils/uuid"

function _render(annotations) {
  let data = Array.isArray(annotations) ? { annotations } : annotations;
  render(svg, viewport, data);
}

let svg;
let viewport;

describe('render::index', function () {
  beforeEach(function () {
    svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    viewport = mockViewport();
  });

  it('should not render the same annotation multiple times', function () {
    let viewport = mockViewport(undefined, undefined, .5);
    let id1 = uuid();
    let id2 = uuid();

    _render([
      {
        type: 'area',
        x: 0,
        y: 0,
        width: 10,
        height: 10,
        uuid: id1
      }
    ]);

    equal(svg.children.length, 1);

    _render([
      {
        type: 'area',
        x: 0,
        y: 0,
        width: 10,
        height: 10,
        uuid: id1
      },
      {
        type: 'area',
        x: 25,
        y: 25,
        width: 10,
        height: 10,
        uuid: id2
      }
    ]);

    equal(svg.children.length, 2);
  });

  it('should add data-attributes', function () {
    _render({
      documentId: '/render',
      pageNumber: 1
    });

    equal(svg.getAttribute('data-pdf-annotate-container'), 'true');
    equal(svg.getAttribute('data-pdf-annotate-viewport'), JSON.stringify(viewport));
    equal(svg.getAttribute('data-pdf-annotate-document'), '/render');
    equal(svg.getAttribute('data-pdf-annotate-page'), '1');
  });

  it('should add document and page if annotations are empty', function () {
    _render({
      documentId: '/render',
      pageNumber: 1,
      annotations: []
    });

    equal(svg.getAttribute('data-pdf-annotate-container'), 'true');
    equal(svg.getAttribute('data-pdf-annotate-viewport'), JSON.stringify(viewport));
    equal(svg.getAttribute('data-pdf-annotate-document'), '/render');
    equal(svg.getAttribute('data-pdf-annotate-page'), '1');
  });

  it('should reset document and page if no data', function () {
    _render({
      documentId: '/render',
      pageNumber: 1,
      annotations: []
    });

    _render();

    equal(svg.getAttribute('data-pdf-annotate-container'), 'true');
    equal(svg.getAttribute('data-pdf-annotate-viewport'), JSON.stringify(viewport));
    equal(svg.getAttribute('data-pdf-annotate-document'), null);
    equal(svg.getAttribute('data-pdf-annotate-page'), null);
  });

  it('should fail gracefully if no annotations are provided', function () {
    let error = false;
    try {
      _render(null);
    } catch (e) {
      error = true;
    }

    equal(error, false);
  });
});
