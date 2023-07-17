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

// Variables

/**
 * The current submission being graded.
 */
let current_submission;

