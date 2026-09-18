import { getStudentNavigationFilter } from '../../ts/ta-grading-toolbar';

describe('getStudentNavigationFilter', () => {
    test('uses cluster navigation while cluster grading is enabled', () => {
        expect(getStudentNavigationFilter(true, 'on', 'ungraded')).toBe('cluster');
    });

    test('uses active inquiries when inquiry mode is enabled', () => {
        expect(getStudentNavigationFilter(false, 'on', 'ungraded')).toBe('active-inquiry');
    });

    test('uses the configured navigation filter otherwise', () => {
        expect(getStudentNavigationFilter(false, 'off', 'ungraded')).toBe('ungraded');
    });

    test('falls back to default navigation for an inactive inquiry filter', () => {
        expect(getStudentNavigationFilter(false, 'off', 'active-inquiry')).toBe('default');
        expect(getStudentNavigationFilter(false, 'off', 'cluster')).toBe('default');
        expect(getStudentNavigationFilter(false, undefined, null)).toBe('default');
    });
});
