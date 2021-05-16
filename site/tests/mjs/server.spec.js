import { buildCourseUrl, getCsrfToken } from '../../public/mjs/server';
import { describe, test } from '@jest/globals';

describe('buildCourseUrl', () => {
    document.body.dataset.courseUrl = 'http://localhost/s20/sample';

    test('build url with no parameter', () => {
        expect(buildCourseUrl()).toEqual('http://localhost/s20/sample');
    });

    test('build url with empty array parameter', () => {
        expect(buildCourseUrl()).toEqual('http://localhost/s20/sample');
    });

    test('build url with one part', () => {
        expect(buildCourseUrl(['test'])).toEqual('http://localhost/s20/sample/test');
    });

    test('build url with multiple parts', () => {
        expect(buildCourseUrl(['forum', 'post', 'get'])).toEqual('http://localhost/s20/sample/forum/post/get');
    });
});

test('getCsrfToken', () => {
    document.body.dataset.csrfToken = 'test-csrf-token';

    expect(getCsrfToken()).toEqual('test-csrf-token');
});
