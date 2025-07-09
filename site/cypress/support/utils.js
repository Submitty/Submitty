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
 * Format the body of the request based on the content type and the CSRF token
 *
 * @param {Object} body - The body of the request, which can be a FormData or JSON object
 * @param {String} contentType - The content type of the request, which can be 'multipart/form-data' or 'application/json'
 * @param {String} csrfToken - The CSRF token to be added to the request
 * @returns {Object} - The formatted body of the request
 */
function formatBody(body, contentType, csrfToken) {
    if (contentType === 'multipart/form-data') {
        const formData = new FormData();

        for (const [key, value] of Object.entries(body)) {
            formData.append(key, value);
        }
        formData.append('csrf_token', csrfToken);
        return formData;
    }
    else {
        return { ...body, csrf_token: csrfToken };
    }
}

/**
 * Verify the WebSocket functionality of a given request.
 *
 * @param {String[]} urlArray - The URL parts to string together using the buildUrl function
 * @param {String} method - The HTTP method to use for the request
 * @param {String} contentType - The content type of the request, which can be 'multipart/form-data' or 'application/json'
 * @param {Object} body - The body of the request, which can be a FormData object or a JSON object
 * @param {Function} verifyResponse - The method to call once the response resulting action and response data can be verified
 */
export function verifyWebSocketFunctionality(
    urlArray = [],
    method = 'GET',
    contentType = 'application/json',
    body = {},
    verifyResponse = () => {},
) {
    return cy.window().then(async (window) => {
        cy.request({
            headers: { 'Content-Type': contentType },
            method: method,
            url: buildUrl(urlArray, true), // Always include the base URL for websocket requests
            body: formatBody(body, contentType, window.csrfToken),
        }).then((res) => {
            // Cypress response body is returned as an array buffer, so we need to parse it into a valid JSON representation
            let response;

            if (res.redirects && Array.isArray(res.redirects)) {
                // Handle redirects
                response = {
                    status: 'success',
                    data: {
                        redirect: `http${res.redirects[0].split('http')[1].trim()}`,
                    },
                };
            }
            else {
                response = JSON.parse(Cypress.Blob.arrayBufferToBinaryString(res.body) || '{}');
            }

            if (Object.keys(response).length > 0) {
                // Handle responses returning data, such as create requests
                expect(response.status).to.equal('success');
                verifyResponse(response.data);
            }
            else {
                // Handle responses returning no data, such as delete requests
                expect(res.status).to.equal(200);
                verifyResponse(res);
            }
        });
    });
}
