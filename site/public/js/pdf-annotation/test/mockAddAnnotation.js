import uuid from '../src/utils/uuid';

export default (spy) => {
  return function (documentId, pageNumber, annotation) {
    spy(documentId, pageNumber, annotation);
    
    annotation.class = 'Annotation';
    annotation.uuid = uuid();
    annotation.page = pageNumber;

    return Promise.resolve(annotation);
  };
}
