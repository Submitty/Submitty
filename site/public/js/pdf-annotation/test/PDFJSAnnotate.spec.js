import PDFJSAnnotate from '../src/PDFJSAnnotate';
import { equal } from 'assert';

let __storeAdapter;

describe('PDFJSAnnotate', function () {
  beforeEach(function () {
    __storeAdapter = PDFJSAnnotate.__storeAdapter;
    PDFJSAnnotate.setStoreAdapter(new PDFJSAnnotate.StoreAdapter({
      getAnnotations: (documentId, pageNumber) => {
        return Promise.resolve({
          documentId,
          pageNumber,
          annotations: [
            {
              type: 'point',
              x: 0,
              y: 0
            }
          ]
        });
      }
    }));
  });

  afterEach(function () {
    PDFJSAnnotate.setStoreAdapter(__storeAdapter);
  });

  it('should get annotations', function (done) {
    PDFJSAnnotate.getAnnotations().then((annotations) => {
      equal(annotations.annotations[0].type, 'point');
      done();
    });
  });

  // it('should throw error if StoreAdapter is not valid', function () {
  //   let error;
  //   try {
  //     PDFJSAnnotate.setStoreAdapter({});
  //   } catch (e) {
  //     error = e;
  //   }
  //   equal(error instanceof Error, true);
  // });
 
  it('should inject documentId and pageNumber', function (done) {
    PDFJSAnnotate.getAnnotations('document-id', 'page-number').then((annotations) => {
      equal(annotations.documentId, 'document-id');
      equal(annotations.pageNumber, 'page-number');
      done();
    });
  });
});

