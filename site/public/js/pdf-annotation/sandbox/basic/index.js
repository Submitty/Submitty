import PDFJSAnnotate from '../../';
import annotations from './annotations';

const DOCUMENT_ID = 'PDFJSAnnotate.pdf';

PDFJS.workerSrc = '../shared/pdf.worker.js';

PDFJSAnnotate.StoreAdapter.getAnnotations = (documentId, pageNumber) => {
  return new Promise((resolve, reject) => {
    resolve(annotations);
  });
};

PDFJS.getDocument(DOCUMENT_ID).then((pdf) => {
  Promise.all([
    pdf.getPage(1),
    PDFJSAnnotate.getAnnotations(1)
  ])
  .then(([page, annotations]) => {
    data.page = page;
    data.annotations = annotations;
    render();
  });
});

let data = {
  page: null,
  annotations: null
};
const scale = document.getElementById('scale');
const rotation = document.getElementById('rotation');

scale.onchange = render;
rotation.onchange = render;

function render() {
  let viewport = data.page.getViewport(scale.value, rotation.value);
  let canvas = document.getElementById('canvas');
  let svg = document.getElementById('svg');
  let canvasContext = canvas.getContext('2d');
  
  canvas.height = viewport.height;
  canvas.width = viewport.width;
  canvas.style.marginTop = ((viewport.height / 2) * -1) + 'px';
  canvas.style.marginLeft = ((viewport.width / 2) * -1) + 'px';

  svg.setAttribute('height', viewport.height);
  svg.setAttribute('width', viewport.width);
  svg.style.marginTop = ((viewport.height / 2) * -1) + 'px';
  svg.style.marginLeft = ((viewport.width / 2) * -1) + 'px';

  data.page.render({canvasContext, viewport});
  PDFJSAnnotate.render(svg, viewport, {
    documentId: DOCUMENT_ID,
    pageNumber: 1,
    annotations: data.annotations
  });
}
