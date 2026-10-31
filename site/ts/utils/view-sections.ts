/**
 * Cookie storing whether TA grading shows only the grader's assigned sections or all sections.
 * It is set at the site root so the choice carries across gradeables and courses.
 */
export const VIEW_SECTIONS_COOKIE = 'grading_view_sections';

/**
 * Saves the "Only Assigned Sections" choice and keeps the Prev/Next navigation setting in sync
 *
 * @param {boolean} viewAll - true to show all sections, false for only assigned sections
 */
export function setViewAllSections(viewAll: boolean): void {
    window.Cookies.set(VIEW_SECTIONS_COOKIE, viewAll ? 'all' : 'assigned', { path: '/', expires: 365 });
    localStorage.setItem('general-setting-navigate-assigned-students-only', viewAll ? 'false' : 'true');
    // this setting used to share the `view` cookie with the calendar, so clear any old copies
    window.Cookies.remove('view', { path: '/' });
    window.Cookies.remove('view', { path: document.body.dataset.coursePath ?? '' });
}
