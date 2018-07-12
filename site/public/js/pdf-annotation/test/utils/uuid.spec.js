import uuid from '../../src/utils/uuid';
import { equal, notEqual } from 'assert';

describe('utils::uuid', function () {
  it('should create a 36 char sequence', function () {
    equal(uuid().length, 36);
  });

  it('should create a random sequence', function () {
    notEqual(uuid(), uuid());
  });
});
