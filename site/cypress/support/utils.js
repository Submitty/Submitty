// These functions are defined in normal JS and can be imported into a spec file

/**
* Generate a 3 letter semester code e.g s21, f20 based on today's data
* This functions the same as the submitty python util's get_current_semester
*
* @returns {String}
*/
export function getCurrentSemester() {
    const today = new Date();
    const year = today.getFullYear().toString().slice(2, 4); // get last two digits
    const semester = ((today.getMonth() + 1) < 7) ? 's' : 'f'; // first half of year 'spring' rest is fall

    return semester + year;
}
/**
* Generates the semester name e.g Spring 2021, Fall 2021 based on today's data
*
* @returns {String}
*/
export function getFullCurrentSemester() {
    const today = new Date();
    const year = today.getFullYear().toString(); // get last two digits
    const semester = ((today.getMonth() + 1) < 7) ? 'Spring' : 'Fall'; // first half of year 'spring' rest is fall

    return `${semester} ${year}`;
}

/**
* Get the API key for the given user_id and password
* @param {String} [password]
* @param {String} [user_id]
* @returns {String}
*/
export function getApiKey(user_id, password) {
    return cy.request({
        method: 'POST',
        url: `${Cypress.config('baseUrl')}/api/token`,
        body: {
            user_id: user_id,
            password: password,
        },
    }).then((response) => {
        return response.body.data.token;
    });
}

/**
* Build a courseURL based on an array of 'parts', e.g [foo, bar] -> courses/s21/foo/bar
*
* @param {String[]} [parts=[]] array of parts to string together
* @param {Boolean} [include_base=false] whether to include the url base (e.g. http://localhost:1501/) or not
* @returns {String}
*/
export function buildUrl(parts = [], include_base = false) {
    let url = '';
    if (include_base) {
        url = `${Cypress.config('baseUrl')}/`;
    }

    return `${url}courses/${getCurrentSemester()}/${parts.join('/')}`;
}

/**
 * Formats the request body based on the content type and presence of a CSRF token
 *
 * @param {Object} body - The body of the request, which can be a FormData or JSON object
 * @param {String} contentType - The content type of the request, which can be 'multipart/form-data' or 'application/json'
 * @param {String} csrfToken - The CSRF token to be added to the request, if present
 * @returns {Object} - The formatted request body
 */
function formatRequestBody(body, contentType, csrfToken) {
    if (contentType === 'multipart/form-data') {
        const formData = new FormData();

        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        for (const [key, value] of Object.entries(body)) {
            formData.append(key, value);
        }

        return formData;
    }
    else {
        return { ...body, csrf_token: csrfToken || undefined };
    }
}

/**
 * Verifies the WebSocket functionality of a given request
 *
 * @param {String[]} urlArray - The URL parts to string together using the buildUrl function including the base URL
 * @param {String} method - The HTTP method to use for the request
 * @param {String} contentType - The content type of the request, which can be 'multipart/form-data' or 'application/json'
 * @param {Object} body - The body of the request, which should be a JSON object
 * @param {Function} successCallback - The method to call for response and UI verification
 * @param {String} apiKey - The API key to use for the request Authorization header, if present

 */
export function verifyWebSocketFunctionality(
    urlArray = [],
    method = 'GET',
    contentType = 'application/json',
    body = {},
    successCallback = () => {},
    apiKey = '',
) {
    const url = buildUrl(urlArray, true);
    return cy.window().then(async (window) => {
        cy.request({
            headers: {
                'Content-Type': contentType,
                'Authorization': apiKey || undefined,
            },
            method: method,
            url: apiKey ? url.replace('courses/', 'api/courses/') : url,
            body: formatRequestBody(body, contentType, apiKey ? undefined : window.csrfToken),
        }).then((res) => {
            let response;

            if (res.redirects && Array.isArray(res.redirects)) {
                // Redirects are in typically in the form "302: http://localhost:1511/courses/s21/foo/bar"
                const index = res.redirects[0].indexOf('http');
                const redirect = res.redirects[0].slice(index).trim();
                response = {
                    status: 'success',
                    data: { redirect },
                };
            }
            else {
                // Cypress response body is formatted as an array buffer, so we need to convert it to a valid JSON representation
                response = JSON.parse(Cypress.Blob.arrayBufferToBinaryString(res.body) || '{}');
            }

            if (Object.keys(response).length > 0) {
                // Validate server-side formatted JSON responses or redirects
                expect(response.status).to.equal('success');
                successCallback(response.data);
            }
            else {
                // Validate responses with no required data
                expect(res.status).to.equal(200);
                successCallback(response);
            }
        });
    });
}

/**
 * Verifies that the WebSocket server is connected and the system message is hidden,
 * where the message is displayed for authentication, connection, or internal server errors.
 */
export function verifyWebSocketStatus(timeout = 10000, interval = 100) {
    const start = Date.now();

    const pollSocket = () => {
        return cy.window().then((win) => {
            const client = win.socketClient?.client;

            if (Date.now() - start > timeout) {
                throw new Error(`WebSocket did not open within ${timeout}ms`);
            }

            if (!client) {
                // Retry for the initialization of the socket client
                return Cypress.Promise.delay(interval).then(pollSocket);
            }

            if (client.readyState === WebSocket.OPEN) {
                // Double check that the system message is hidden
                return cy.get('#socket-server-system-message').should('be.hidden');
            }

            // Retry after a short wait in case we are attempting to establish a stable connection
            return Cypress.Promise.delay(interval).then(pollSocket);
        });
    };

    return pollSocket();
}
