export default (spy) => {
  return function (documentId, annotationId, annotation) {
    spy(documentId, annotationId, annotation);
    return Promise.resolve(annotation);
  };
}
