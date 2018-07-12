import PDFJSAnnotate from '../../';
import annotations from './annotations';
import mockViewport from '../mockViewport';

const { UI } = PDFJSAnnotate;
const svg = document.querySelector('svg');
const DOCUMENT_ID = window.location.pathname.replace(/\/$/, '');
const PAGE_NUMBER = 1;

function findAnnotation(annotationId) {
  let index = -1;
  for (let i=0, l=annotations.length; i<l; i++) {
    if (annotations[i].uuid === annotationId) {
      index = i;
      break;
    }
  }
  return index;
}

PDFJSAnnotate.StoreAdapter.getAnnotations = (documentId, pageNumber) => {
  return new Promise((resolve, reject) => {
    resolve(annotations);
  });
};

PDFJSAnnotate.StoreAdapter.getAnnotation = (documentId, annotationId) => {
  return new Promise((resolve, reject) => {
    resolve(annotations[findAnnotation(annotationId)]);
  });
};

PDFJSAnnotate.StoreAdapter.editAnnotation = (documentId, annotationId, annotation) => {
  let index = findAnnotation(annotationId);

  if (index > -1) {
    annotations[index] = annotation;
    resolve(annotation);
  } else {
    reject(new Error(`Can't find annotation ${annotationId}`));
  }
};

PDFJSAnnotate.StoreAdapter.deleteAnnotation = (documentId, annotationId) => {
  let index = findAnnotation(annotationId);
  
  if (index > -1) {
    annotations.splice(index, 1);
  }
};

// Get the annotations
PDFJSAnnotate.getAnnotations(DOCUMENT_ID, PAGE_NUMBER).then((annotations) => {
  PDFJSAnnotate.render(svg, mockViewport(svg), {
    documentId: DOCUMENT_ID,
    pageNumber: 1,
    annotations
  });
});

UI.enableEdit();
