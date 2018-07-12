export default (spy) => {
  return function (documentId, annotationId) {
    spy(documentId, annotationId);
    return Promise.resolve(true);
  };
}
