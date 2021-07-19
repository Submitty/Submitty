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

let messages = 0;

export function displayErrorMessage(message){
    displayMessage(message, 'error');
}

export function displaySuccessMessage(message) {
    displayMessage(message, 'success');
}

/**
 * Display a toast message after an action.
 *
 * The styling here should match what's used in GlobalHeader.twig to define the messages coming from PHP
 *
 * @param {string} message
 * @param {string} type either 'error' or 'success'
 */
export function displayMessage(message, type) {
    const id = `${type}-js-${messages}`;
    message = `<div id="${id}" class="inner-message alert alert-${type}"><span><i style="margin-right:3px;" class="fas fa-${type === 'error' ? 'times' : 'check'}-circle"></i>${message.replace(/(?:\r\n|\r|\n)/g, '<br />')}</span><a class="fas fa-times" onClick="removeMessagePopup('${type}-js-${messages}');"></a></div>`;
    $('#messages').append(message);
    $('#messages').fadeIn('slow');
    if (type === 'success') {
        setTimeout(() => {
            $(`#${id}`).fadeOut();
        }, 5000);
    }
    messages++;
}
