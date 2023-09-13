/* exported rejoinCourse */
/* global csrfToken */

/**
 * rejoin-course.js
 * Functions for students self-readding themselves to courses when conditions are met.
 */

/**
 * Sends request for student to be readded to the course.
 * param string readd_url Url to the readd PHP function.
 */
async function rejoinCourse(readd_url) {
    $.ajax({
        type: 'POST',
        url: readd_url,
        data: {
            'csrf_token': csrfToken,
        },
    });
}
