import uuid from '../src/utils/uuid';

export default (spy) => {
  return function (documentId, annotationId, content) {
    spy(documentId, annotationId, content);
    
    let comment = {
      class: 'Comment',
      uuid: uuid(),
      annotation: annotationId,
      content: content
    };

    return Promise.resolve(comment);
  };
}
