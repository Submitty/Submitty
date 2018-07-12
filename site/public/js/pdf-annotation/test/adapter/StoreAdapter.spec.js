import StoreAdapter from '../../src/adapter/StoreAdapter';
import LocalStoreAdapter from '../../src/adapter/LocalStoreAdapter';
import { addEventListener, removeEventListener } from '../../src/UI/event';
import { equal } from 'assert';

function testExpectedError(callback) {
  return function () {
    let error = null;
    try {
      callback();
    } catch (e) {
      error = e;
    }

    equal(error instanceof Error, true);
  }
}

describe('StoreAdapter', function () {
  describe('abstract', function () {
    let adapter;

    beforeEach(function () {
      adapter = new StoreAdapter();
    });

    it('should error by default when calling getAnnotations', testExpectedError(function () {
      adapter.getAnnotations();
    }));
    
    it('should error by default when calling getAnnotation', testExpectedError(function () {
      adapter.getAnnotation();
    }));
  
    it('should error by default when calling addAnnotation', testExpectedError(function () {
      adapter.addAnnotation();
    }));
      
    it('should error by default when calling editAnnotation', testExpectedError(function () {
      adapter.editAnnotation();
    }));

    it('should error by default when calling deleteAnnotation', testExpectedError(function () {
      adapter.deleteAnnotation();
    }));

    it('should error by default when calling getComments', testExpectedError(function () {
      adapter.getComments();
    }));

    it('should error by default when calling addComment', testExpectedError(function () {
      adapter.addComment();
    }));

    it('should error by default when calling deleteComment', testExpectedError(function () {
      adapter.deleteComment();
    }));
  });

  describe('events', function () {
    let adapter;
    let handleAnnotationAdd = sinon.spy();
    let handleAnnotationEdit = sinon.spy();
    let handleAnnotationDelete = sinon.spy();
    let handleCommentAdd = sinon.spy();
    let handleCommentDelete = sinon.spy();

    beforeEach(function () {
      adapter = new LocalStoreAdapter();
    });

    afterEach(function () {
      removeEventListener('annotation:add', handleAnnotationAdd);
      removeEventListener('annotation:edit', handleAnnotationEdit);
      removeEventListener('annotation:delete', handleAnnotationDelete);
      removeEventListener('comment:add', handleCommentAdd);
      removeEventListener('comment:delete', handleCommentDelete);
    });

    it('should emit annotation:add', function (done) {
      addEventListener('annotation:add', handleAnnotationAdd);
      adapter.addAnnotation(12345, 1, {type: 'foo'});

      setTimeout(() => {
        equal(handleAnnotationAdd.called, true);
        done();
      });
    });
    
    it('should emit annotation:edit', function (done) {
      addEventListener('annotation:edit', handleAnnotationEdit);
      adapter.editAnnotation(12345, 67890, {type: 'bar'});

      setTimeout(() => {
        equal(handleAnnotationEdit.called, true);
        done();
      });
    });
    
    it('should emit annotation:delete', function (done) {
      addEventListener('annotation:delete', handleAnnotationDelete);
      adapter.deleteAnnotation(12345, 67890);

      setTimeout(() => {
        equal(handleAnnotationDelete.called, true);
        done();
      });
    });

    it('should emit comment:add', function (done) {
      addEventListener('comment:add', handleCommentAdd);
      adapter.addComment(12345, 67890, 'hello');

      setTimeout(() => {
        equal(handleCommentAdd.called, true);
        done();
      });
    });

    it('should emit comment:delete', function (done) {
      addEventListener('comment:delete', handleCommentDelete);
      adapter.deleteComment(12345, 67890);

      setTimeout(() => {
        equal(handleCommentDelete.called, true);
        done();
      });
    });
  });
});
