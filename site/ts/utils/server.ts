/**
 * Builds the course url with assembled parts
 *
 * @param {string[]} parts
 * @returns string
 */
export function buildCourseUrl(parts: string[] = []): string {
    return `${document.body.dataset.courseUrl}${parts.length > 0 ? `/${parts.join('/')}` : ''}`;
}

/**
 * Acts in a similar fashion to Core->buildUrl() function within the PHP code
 *
 * @param {string[]} parts - Object representing URL parts to append to the URL
 * @returns {string} - Built up URL to use
 */
export function buildUrl(parts: string[] = []): string {
    return document.body.dataset.baseUrl + parts.join('/');
}

/**
 * Gets the CSRF token for the current page
 *
 * @returns string
 */
export function getCsrfToken(): string {
    return document.body.dataset.csrfToken ?? '';
}
