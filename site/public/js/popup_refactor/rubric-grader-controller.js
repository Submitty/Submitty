 /**
 * ---------------------
 *
 * rubric-grader-controller.js
 *
 * The user-side controller for the new RubricGrader page.
 * Loads in advance the previous and next students, and allows
 * asyncronous writeback to the server.
 *
 * ---------------------
 */

// ---------------------------------

// Publisher-Subcriber Object

import PubSub from './PubSub';
const PUBSUB = new PubSub();

export default PUBSUB;

// ---------------------------------



// ---



// ---------------------------------

// Variables

// Gradeable-Specific

/**
 * The current submission being graded.
 */
let current_submission;


/**
 * The access mode for the current user for this gradeable.
 * Possible Values:
 *  - "unblind" - Nothing about students is hidden.
 *  - "single"  - For peer grading or for full access grading's Anonymous Mode. Graders cannot see
 *                who they are currently grading.
 *  - "double"  - For peer grading. In addition to blinded peer graders, students cannot
 *                see which peer they are currently grading.
 */
let blind_access_mode;


// ---------------------------------



// ---



// ---------------------------------

// Functions

function progress_to_next_student() {
    
}