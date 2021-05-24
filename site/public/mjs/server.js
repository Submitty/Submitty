/**
 * Builds the course url with assembled parts
 *
 * @param {string[]} parts
 * @returns string
 */
export function buildCourseUrl(parts = []) {
    return `${document.body.dataset.courseUrl}${parts.length > 0 ? `/${parts.join('/')}` : ''}`;
}

/**
 * Gets the CSRF token for the current page
 *
 * @returns string
 */
export function getCsrfToken() {
    return document.body.dataset.csrfToken;
}
