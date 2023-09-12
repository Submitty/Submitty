/* exported rejoinCourse */
/* global csrfToken */

/**
 * rejoin-class.js
 * Functions for students self-readding themselves to courses when conditions are met.
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
