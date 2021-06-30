import '../../public/mjs/activity-dashboard';
import { test } from '@jest/globals';
import { helper } from '../../public/mjs/activity-dashboard';

const helperTestCases = [['cat', 'apple', 0], ['0', '1', 0], ['1', '0', 0], ['0', '0', 0], ['', 'apple', 0], ['apple','', 0], ['1', 'apple', 0], ['apple', '1', 0],
    ['cat', 'apple', 1], ['0', '1', 1], ['1', '0', 1], ['0', '0', 1], ['', 'apple', 1], ['apple','', 1]];
const helperResults = [false, true, false, false, false, true, true, false, false, true, false, false, true, false];

test('helper', () => {
    for (let i = 0; i < helperTestCases.length; i++) {
        const testCase = helperTestCases[i];
        expect(helper(testCase[0], testCase[1], testCase[2])).toEqual(helperResults[i]);
    }
});
