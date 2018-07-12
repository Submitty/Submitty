import normalizeColor from '../../src/utils/normalizeColor';
import { equal } from 'assert';

describe('utils::normalizeColor', function () {
  it('should add # to invalid hex', function () {
    equal(normalizeColor('000'), '#000');
    equal(normalizeColor('ccff00'), '#ccff00');
  });

  it('should not add # to valid hex', function () {
    equal(normalizeColor('#000'), '#000');
    equal(normalizeColor('#ccff00'), '#ccff00');
  });

  it('should not alter rgb', function () {
    equal(normalizeColor('rgb(0, 0, 0)'), 'rgb(0, 0, 0)');
  });
});
