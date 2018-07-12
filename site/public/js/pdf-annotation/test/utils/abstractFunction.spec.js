import abstractFunction from '../../src/utils/abstractFunction';
import { equal } from 'assert';

describe('utils::abstractFunction', function () {
  it('should throw when not implemented', function () {
    let err;

    try {
      abstractFunction('fn');
    } catch (e) {
      err = e;
    }

    equal(typeof err, 'object');
    equal(err.message, 'fn is not implemented');
  });
});
