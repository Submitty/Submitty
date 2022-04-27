import { GradedComponent } from './types/GradedComponent';
import { Mark } from './types/Mark';

/**
 * An associative object of <component-id> : <mark[]>
 * Each 'mark' has at least properties 'id', 'points', 'title', which is sufficient
 *  to determine conflict resolutions.  These are updated when a component is opened.
 * @type {Object}
 */
export const OLD_MARK_LIST: { [key: number]: Mark[] } = {};

/**
 * An associative object of <component-id> : <graded_component>
 * Each 'graded_component' has at least properties 'score', 'mark_ids', 'comment'
 * @type {{Object}}
 */
export const OLD_GRADED_COMPONENT_LIST: { [key: number]: GradedComponent } = {};

/**
 * A number ot represent the id of no component
 * @type {number}
 */
export const NO_COMPONENT_ID = -1;

/**
 * The id of the custom mark for a component
 * @type {number}
 */
export const CUSTOM_MARK_ID = 0;

/**
 * A counter to given unique, negative ids to new marks that haven't been
 *  added to the server yet
 * @type {number}
 */
let MARK_ID_COUNTER = 0;

/**
 * True if components should open in edit mode
 * @type {boolean}
 */
let EDIT_MODE_ENABLED = false;

/**
 * True if a TA is grading a Peer assignment
 * this allows differentiation between what peers and TA's are allowed to
 * do with the rubric for a peer assignment
 */
let TA_GRADING_PEER = false;

/**
 * Count directions for components
 * @type {number}
 */
export const COUNT_DIRECTION_UP = 1;
export const COUNT_DIRECTION_DOWN = -1;

/**
 * Pdf Page settings for components
 * @type {number}
 */
export const PDF_PAGE_NONE = 0;
export const PDF_PAGE_STUDENT = -1;
export const PDF_PAGE_INSTRUCTOR = -2;

declare global {
    interface Window{
        NO_COMPONENT_ID: number;
        CUSTOM_MARK_ID: number;
        PDF_PAGE_NONE: number;
        PDF_PAGE_STUDENT: number;
        PDF_PAGE_INSTRUCTOR: number;
    }
}

/**
 * Gets a unique mark id for adding new marks
 * @return {number}
 */
export function getNewMarkId(): number {
    return MARK_ID_COUNTER--;
}

export function getEditModeEnabled(): boolean {
    return EDIT_MODE_ENABLED;
}

export function setEditModeEnabled(editModeEnabled: boolean): void {
    EDIT_MODE_ENABLED = editModeEnabled;
}

export function getTAGradedPeer(): boolean {
    return TA_GRADING_PEER;
}

export function setTAGradedPeer(taGradedPeer: boolean): void {
    TA_GRADING_PEER = taGradedPeer;
}

/**
 * Used to check if two marks are equal
 * @param {Object} mark0
 * @param {Object} mark1
 * @return {boolean}
 */
export function marksEqual(mark0: Mark, mark1: Mark): boolean {
    return mark0.points === mark1.points && mark0.title === mark1.title
        && mark0.publish === mark1.publish;
}

/**
 * Searches a array of marks for a mark with an id
 * @param {Object[]} marks
 * @param {number} mark_id
 * @return {Object}
 */
export function getMarkFromMarkArray(marks: Mark[], mark_id: number): Mark | null {
    for (let i = 0; i < marks.length; ++i) {
        if (marks[i].id === mark_id) {
            return marks[i];
        }
    }
    return null;
}

/**
 * Returns true if dividend is evenly divisible by divisor, false otherwise
 * @param {number} dividend
 * @param {number} divisor
 * @returns {boolean}
 */
export function dividesEvenly(dividend: number, divisor: number): boolean {
    const multiplier = Math.pow(10, Math.max(decimalLength(dividend), decimalLength(divisor)));
    return ((dividend * multiplier) % (divisor * multiplier) === 0);
}

/**
 * Returns number of digits after decimal point
 * @param {number} num
 * @returns {number}
 */
function decimalLength(num: number): number {
    return (num.toString().split('.')[1] || '').length;
}

window.NO_COMPONENT_ID = NO_COMPONENT_ID;
window.CUSTOM_MARK_ID = CUSTOM_MARK_ID;
window.PDF_PAGE_NONE = PDF_PAGE_NONE;
window.PDF_PAGE_STUDENT = PDF_PAGE_STUDENT;
window.PDF_PAGE_INSTRUCTOR = PDF_PAGE_INSTRUCTOR;
