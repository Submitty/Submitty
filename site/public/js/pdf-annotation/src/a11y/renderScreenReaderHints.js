import insertScreenReaderHint from './insertScreenReaderHint';
import initEventHandlers from './initEventHandlers';

// TODO This is not the right place for this to live
initEventHandlers();

/**
 * Insert hints into the DOM for screen readers.
 *
 * @param {Array} annotations The annotations that hints are inserted for
 */
export default function renderScreenReaderHints(annotations) {
  annotations = Array.isArray(annotations) ? annotations : [];

  // Insert hints for each type
  Object.keys(SORT_TYPES).forEach((type) => {
    let sortBy = SORT_TYPES[type];
    annotations
      .filter((a) => a.type === type)
      .sort(sortBy)
      .forEach((a, i) => insertScreenReaderHint(a, i + 1));
  });
}

// Sort annotations first by y, then by x.
// This allows hints to be injected in the order they appear,
// which makes numbering them easier.
function sortByPoint(a, b) {
  if (a.y < b.y) {
    return a.x - b.x;
  } else {
    return 1;
  }
}

// Sort annotation by it's first rectangle
function sortByRectPoint(a, b) {
  return sortByPoint(a.rectangles[0], b.rectangles[0]);
}

// Sort annotation by it's first line
function sortByLinePoint(a, b) {
  let lineA = a.lines[0];
  let lineB = b.lines[0];
  return sortByPoint(
    {x: lineA[0], y: lineA[1]},
    {x: lineB[0], y: lineB[1]}
  );
}

// Arrange supported types and associated sort methods
const SORT_TYPES = {
  'highlight': sortByRectPoint,
  'strikeout': sortByRectPoint,
  'drawing': sortByLinePoint,
  'textbox': sortByPoint,
  'point': sortByPoint,
  'area': sortByPoint
};

