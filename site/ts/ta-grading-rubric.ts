import { viewFileFullPanel } from './ta-grading';
import { openMarkConflictPopup } from './ta-grading-rubric-conflict';

/**
 *  Notes: Some variables have 'domElement' in their name, but they may be jquery objects
 */

/**
 * Global variables.  Add these very sparingly
 */

const GRADED_COMPONENTS_LIST: Record<string, ComponentGradeInfo | undefined> = {};
const COMPONENT_RUBRIC_LIST: Record<string, Component> = {};
const ACTIVE_GRADERS_LIST: Record<string, string[]> = {};
let GRADED_GRADEABLE: { peer_gradeable: boolean; user_group: number; active_graders: Record<string, string>[]; active_graders_timestamps: Record<string, string>[]; graded_components: Record<string, GradedComponent> } | null = null;
type Stats = { section_submitter_count: string; total_submitter_count: string; section_graded_component_count: string; total_graded_component_count: string; section_total_component_count: string; total_total_component_count: string; submitter_ids: string[]; submitter_anon_ids: Record<string, string> };
type Gradeable = {
    components: Component[];
};
type Component = {
    id: number; title: string; ta_comment: string; student_comment: string; page: number; lower_clamp: number; default: number; max_value: number; upper_clamp: number; is_itempool_linked: boolean; itempool_option: string; peer: boolean;
    marks: Mark[];
};
interface GradedComponent extends Component, ComponentGradeInfo {
    component_id: number;
}
export type ComponentGradeInfo = {
    comment: string;
    score: number;
    custom_mark_selected: boolean;
    mark_ids: number[];
    graded_version: number;
    grade_time: string;
    grader_id: string;
    verifier_id: string;
    custom_mark_enabled: number;
};
type Mark = {
    id: number;
    points: number;
    title: string | undefined;
    publish: boolean;
    order?: number;
    deleted?: boolean;
};

type MarkConflictInfo = {
    domMark: Mark;
    serverMark: Mark | null;
    oldServerMark: Mark | null;
    localDeleted: boolean;
};

export type MarkConflicts = Record<number, MarkConflictInfo>;

/**
 * An associative object of <component-id> : <mark[]>
 * Each 'mark' has at least properties 'id', 'points', 'title', which is sufficient
 *  to determine conflict resolutions.  These are updated when a component is opened.
 * @type {Object}
 */
const OLD_MARK_LIST: Record<string, Mark[]> = {};

/**
 * An associative object of <component-id> : <graded_component[]>
 * Each 'graded_component' has at least properties 'score', 'mark_ids', 'comment'
 * @type {{Object}}
 */
const OLD_GRADED_COMPONENT_LIST: Record<number, ComponentGradeInfo> = {};

/**
 * A number to represent the id of no component
 * @type {int}
 */
export const NO_COMPONENT_ID = -1;

/**
 * The id of the custom mark for a component
 * @type {int}
 */
export const CUSTOM_MARK_ID = 0;

/**
 * A counter to given unique, negative ids to new marks that haven't been
 *  added to the server yet
 * @type {int}
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
 * @type {int}
 */
const COUNT_DIRECTION_UP = 1;
const COUNT_DIRECTION_DOWN = -1;

/**
 * Pdf Page settings for components
 * @type {int}
 */
// eslint-disable-next-line no-unused-vars, no-var
window.PDF_PAGE_NONE = 0;
// eslint-disable-next-line no-var
window.PDF_PAGE_STUDENT = -1;
window.PDF_PAGE_INSTRUCTOR = -2;

/**
 * Whether ajax requests will be asynchronous or synchronous.  This
 *  is used instead of passing an 'async' parameter to every function.
 * @type {boolean}
 */
// eslint-disable-next-line no-var
var AJAX_USE_ASYNC = true;

/**
 * Keep All of the ajax functions at the top of this file
 *
 */

/**
 * Called internally when an ajax function irrecoverably fails before rejecting
 * @param err
 */
function displayAjaxError(err: unknown) {
    console.error("Failed to parse response.  The server isn't playing nice...");
    console.error(err);
    // alert("There was an error communicating with the server. Please refresh the page and try again.");
}

/**
 * ajax call to fetch the gradeable's rubric
 * @param {string} gradeable_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxGetGradeableRubric(gradeable_id: string) {
    let response: { status: string; message: string; data: Gradeable } | null;
    try {
        response = await $.ajax({
            type: 'GET',
            dataType: 'json',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'rubric']),
        }) as typeof response;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response!.status !== 'success') {
        console.error(`Something went wrong fetching the gradeable rubric: ${response!.message}`);
        throw new Error(response!.message);
    }
    else {
        return response!.data;
    }
}

/**
 * ajax call to save the component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} title
 * @param {string} ta_comment
 * @param {string} student_comment
 * @param {int} page
 * @param {number} lower_clamp
 * @param {number} default_value
 * @param {number} max_value
 * @param {number} upper_clamp
 * @param {boolean} is_itempool_linked
 * @param {string} itempool_option
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @returns {Object}
 */
async function ajaxSaveComponent(gradeable_id: string | undefined, component_id: number, title: string | number | string[] | undefined, ta_comment: string | number | string[] | undefined, student_comment: string | number | string[] | undefined, page: number, lower_clamp: number, default_value: number, max_value: number, upper_clamp: number, is_itempool_linked: boolean, itempool_option: string | number | string[] | undefined) {
    let response: Record<string, string> | null;
    try {
        response = await $.ajax({
            type: 'POST',
            dataType: 'json',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'save']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                title: title,
                ta_comment: ta_comment,
                student_comment: student_comment,
                page_number: page,
                lower_clamp: lower_clamp,
                default: default_value,
                max_value: max_value,
                upper_clamp: upper_clamp,
                is_itempool_linked: is_itempool_linked,
                itempool_option: itempool_option === 'null' ? undefined : itempool_option,
                peer: false,
            },
        }) as Record<string, string>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong saving the component: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to fetch the component's rubric
 * @param {string} gradeable_id
 * @param {int} component_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @returns {Object}
 */
async function ajaxGetComponentRubric(gradeable_id: string | undefined, component_id: number) {
    let response: { status: string; message: string; data: Component } | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'components'])}?component_id=${component_id}`,
        }) as typeof response;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response!.status !== 'success') {
        console.error(`Something went wrong fetching the component rubric: ${response!.message}`);
        throw new Error(response!.message);
    }
    else {
        return response!.data;
    }
}

/**
 * ajax call to get the entire graded gradeable for a user
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {boolean} all_peers
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxGetGradedGradeable(gradeable_id: string, anon_id: string | undefined, all_peers: boolean) {
    let response: { status: string; message: string; data: typeof GRADED_GRADEABLE } | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'graded_gradeable'])}?anon_id=${anon_id}&all_peers=${all_peers.toString()}`,
        }) as typeof response;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response!.status !== 'success') {
        console.error(`Something went wrong fetching the gradeable grade: ${response!.message}`);
        throw new Error(response!.message);
    }
    else {
        return response!.data;
    }
}

/**
 * ajax call to fetch an updated Graded Component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxGetGradedComponent(gradeable_id: string | undefined, component_id: number, anon_id: string | undefined) {
    let response: { status: string; message: string; data?: GradedComponent } | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'graded_gradeable', 'graded_component'])}?anon_id=${anon_id}&component_id=${component_id}`,
        }) as typeof response;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response!.status !== 'success') {
        console.error(`Something went wrong fetching the component grade: ${response!.message}`);
        throw new Error(response!.message);
    }
    else {
        // null is not the same as undefined, so we need to make that conversion before resolving
        if (response!.data === null) {
            response!.data = undefined;
        }
        return response!.data;
    }
}

/**
 * ajax call to save the grading information for a component and submitter
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @param {int} graded_version
 * @param {number} custom_points
 * @param {string} custom_message
 * @param {boolean} silent_edit True to edit marks assigned without changing the grader
 * @param {int[]} mark_ids
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxSaveGradedComponent(gradeable_id: string | undefined, component_id: number, anon_id: string | undefined, graded_version: number, custom_points: number, custom_message: string, silent_edit: boolean, mark_ids: number[]) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'grading', 'graded_gradeable', 'graded_component']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                anon_id: anon_id,
                graded_version: graded_version,
                custom_points: custom_points,
                custom_message: custom_message,
                silent_edit: silent_edit,
                mark_ids: mark_ids,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong saving the component grade: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to save the general comment for the graded gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {string} overall_comment
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxSaveOverallComment(gradeable_id: string | undefined, anon_id: string | undefined, overall_comment: string | number | string[]) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'grading', 'comments']),
            data: {
                csrf_token: window.csrfToken,
                gradeable_id: gradeable_id,
                anon_id: anon_id,
                overall_comment: overall_comment,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong saving the overall comment: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to add a new mark to the component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} title
 * @param {number} points
 * @param {boolean} publish
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
export async function ajaxAddNewMark(gradeable_id: string | undefined, component_id: number, title: string, points: number, publish: boolean) {
    let response: { status: string; message: string; data: { mark_id: number } } | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'add']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                title: title,
                points: points,
                publish: publish,
            },
        }) as typeof response;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response!.status !== 'success') {
        console.error(`Something went wrong adding a new mark: ${response!.message}`);
        throw new Error(response!.message);
    }
    else {
        return response!.data;
    }
}

/**
 * ajax call to delete a mark
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @async
 * @throws {Error} Throws except when the response returns status
 * @return {Object}
 */
export async function ajaxDeleteMark(gradeable_id: string | undefined, component_id: number, mark_id: number) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'delete']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                mark_id: mark_id,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong deleting the mark: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to save mark point value / title
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @param {string} title
 * @param {number} points
 * @param {boolean} publish
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
export async function ajaxSaveMark(gradeable_id: string | undefined, component_id: number, mark_id: number, title: string, points: number, publish: boolean) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'save']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                mark_id: mark_id,
                points: points,
                title: title,
                publish: publish,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong saving the mark: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to get the stats about a mark
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {int} mark_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxGetMarkStats(gradeable_id: string | undefined, component_id: number, mark_id: number) {
    let response: { status: string; message: string; data: Stats } | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'stats']),
            data: {
                component_id: component_id,
                mark_id: mark_id,
                csrf_token: window.csrfToken,
            },
        }) as typeof response;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response!.status !== 'success') {
        console.error(`Something went wrong getting mark stats: ${response!.message}`);
        throw new Error(response!.message);
    }
    else {
        return response!.data;
    }
}

/**
 * ajax call to update the order of marks in a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {*} order format: { <mark0-id> : <order0>, <mark1-id> : <order1>, ... }
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxSaveMarkOrder(gradeable_id: string | undefined, component_id: number, order: Record<string, number>) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'save_order']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                order: JSON.stringify(order),
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong saving the mark order: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to update the pages of components in the gradeable
 * @param {string} gradeable_id
 * @param {*} pages format: { <component0-id> : <page0>, <component1-id> : <page1>, ... } OR { page } to set all
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxSaveComponentPages(gradeable_id: string | undefined, pages: { page: number }) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'save_pages']),
            data: {
                csrf_token: window.csrfToken,
                pages: JSON.stringify(pages),
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong saving the component pages: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to update the order of components in the gradeable
 * @param {string} gradeable_id
 * @param {*} order format: { <component0-id> : <order0>, <component1-id> : <order1>, ... }
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxSaveComponentOrder(gradeable_id: string | undefined, order: Record<string, number>) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'order']),
            data: {
                csrf_token: window.csrfToken,
                order: JSON.stringify(order),
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong saving the component order: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to add a generate component on the server
 * @param {string} gradeable_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxAddComponent(gradeable_id: string | undefined, peer: boolean) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'new']),
            data: {
                csrf_token: window.csrfToken,
                peer: peer,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong adding the component: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to delete a component from the server
 * @param {string} gradeable_id
 * @param {int} component_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxDeleteComponent(gradeable_id: string | undefined, component_id: number) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'delete']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong deleting the component: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to verify the grader of a component
 * @param {string} gradeable_id
 * @param {int} component_id
 * @param {string} anon_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxVerifyComponent(gradeable_id: string | undefined, component_id: number, anon_id: string | undefined) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: true,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'verify']),
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                anon_id: anon_id,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong verifying the component: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to verify the grader of a component
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxVerifyAllComponents(gradeable_id: string | undefined, anon_id: string | undefined) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: true,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'components', 'verify'])}?verify_all=true`,
            data: {
                csrf_token: window.csrfToken,
                anon_id: anon_id,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong verifying the all components: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * ajax call to change the graded version of the gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @async
 * @throws {Error} Throws except when the response returns status 'success'
 * @return {Object}
 */
async function ajaxChangeGradedVersion(gradeable_id: string | undefined, anon_id: string | undefined, component_version: number, component_ids: number[]) {
    let response: Record<string, string | undefined> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'grading', 'graded_gradeable', 'change_grade_version']),
            data: {
                anon_id,
                graded_version: component_version,
                component_ids,
                csrf_token: window.csrfToken,
            },
        }) as Record<string, string | undefined>;
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong changing graded version: ${response.message}`);
        throw new Error(response.message);
    }
    else {
        return response.data;
    }
}

/**
 * Gets if the 'verify' button should show up for a component
 * @param {ComponentGradeInfo} graded_component
 * @param {string} grader_id
 * @returns {boolean}
 */
export function showVerifyComponent(graded_component: ComponentGradeInfo | undefined, grader_id: string): boolean {
    return graded_component !== undefined && graded_component.grader_id !== '' && grader_id !== graded_component.grader_id;
}

window.showVerifyComponent = function (graded_component_object: object | undefined, grader_id: string): boolean {
    const graded_component = graded_component_object as ComponentGradeInfo;
    return graded_component !== undefined && graded_component.grader_id !== '' && grader_id !== graded_component.grader_id;
};

/**
 * Put all DOM accessing methods here to abstract the DOM from the other function
 *  of the interface
 */

/**
 * Gets the id of the open gradeable
 * @return {string}
 */
export function getGradeableId() {
    return $('#gradeable-rubric').attr('data-gradeable_id')!;
}

/**
 * Gets the anon_id of the submitter being graded
 * @return {string}
 */
export function getAnonId(): string {
    return $('#anon-id').attr('data-anon_id')!;
}

/**
 * Gets the id of the grader
 * @returns {*|void|jQuery}
 */
function getGraderId() {
    return $('#grader-info').attr('data-grader_id')!;
}

/**
 * Used to determine if the interface displayed is for
 *  instructor edit mode (i.e. in the Edit Gradeable page)
 *  @return {boolean}
 */
function isInstructorEditEnabled() {
    return $('#edit-gradeable-instructor-flag').length > 0;
}

/**
 * Used to determine if the 'verify grader' button should be displayed
 * @returns {boolean}
 */
function canVerifyGraders() {
    return $('#grader-info').attr('data-can_verify');
}

/**
 * Gets if grading is disabled since the selected version isn't the same
 *  as the one chosen for grading
 * @return {boolean}
 */
function isGradingDisabled() {
    return $('#version-conflict-indicator').length > 0;
}

/**
 * Gets the gradeable version being disaplyed
 * @return {int}
 */
function getDisplayVersion() {
    return parseInt($('#gradeable-version-container').attr('data-gradeable_version')!);
}

/**
 * Gets the precision for component/mark point values
 * @returns {number}
 */
function getPointPrecision() {
    return parseFloat($('#point_precision_id').val() as string);
}

function getAllowCustomMarks() {
    return $('#allow_custom_marks').attr('data-gradeable_custom_marks');
}

/**
 * Used to determine if the mark list should be displayed in 'edit' mode
 *  @return {boolean}
 */
function isEditModeEnabled() {
    return EDIT_MODE_ENABLED || isInstructorEditEnabled();
}

/**
 * Updates the edit mode state.  This is used to the mode
 * does not change before the components close
 */
function updateEditModeEnabled() {
    // noinspection JSUndeclaredVariable
    EDIT_MODE_ENABLED = $('#edit-mode-enabled').is(':checked');
}

/**
 * Gets if silent edit mode is enabled
 * @return {boolean}
 */
export function isSilentEditModeEnabled() {
    // noinspection JSValidateTypes
    return $('#silent-edit-id').is(':checked');
}

/**
 * Gets a unique mark id for adding new marks
 * @return {int}
 */
function getNewMarkId() {
    return MARK_ID_COUNTER--;
}

/**
 * Sets the DOM elements to render for the entire rubric
 * @param elements
 */
function setRubricDOMElements(elements: string | Element | DocumentFragment | Document | Comment | ((this: HTMLElement, index: number, oldhtml: JQuery.htmlString) => JQuery.htmlString | JQuery.Node)) {
    const gradingBox = $('#grading-box');
    gradingBox.html(elements);

    if (isInstructorEditEnabled()) {
        setupSortableComponents();
    }
}

/**
 * Gets the component id of a DOM element inside a component
 * @param me DOM element
 * @return {int}
 */
function getComponentIdFromDOMElement(me: HTMLElement) {
    if ($(me).hasClass('component')) {
        return parseInt($(me).attr('data-component_id')!);
    }
    return parseInt($(me).parents('.component').attr('data-component_id')!);
}

/**
 * Gets the mark id of a DOM element inside a mark
 * @param me DOM element
 * @return {int}
 */
function getMarkIdFromDOMElement(me: HTMLElement) {
    if ($(me).hasClass('mark-container')) {
        return parseInt($(me).attr('data-mark_id')!);
    }
    return parseInt($(me).parents('.mark-container').attr('data-mark_id')!);
}

/**
 * Gets the JQuery selector for the component id
 * Note: This is not the component container
 * @param {int} component_id
 * @return {jQuery}
 */
export function getComponentJQuery(component_id: number) {
    return $(`#component-${component_id}`);
}

/**
 * Gets the JQuery selector for the mark id
 * @param {int} mark_id
 * @return {jQuery}
 */
function getMarkJQuery(mark_id: number) {
    return $(`#mark-${mark_id}`);
}

/**
 * Gets the JQuery selector for the component's custom mark
 * @param {int} component_id
 * @return {jQuery}
 */
function getCustomMarkJQuery(component_id: number) {
    return getComponentJQuery(component_id).find('.custom-mark-container');
}

/**
 * Gets the JQuery selector for the overall comment container
 * @return {jQuery}
 */
function getOverallCommentJQuery() {
    return $('#overall-comment-container');
}

/**
 * Returns whether the current is of type notebook
 * @return {string}
 */
function isItempoolAvailable() {
    return $('#gradeable_rubric.electronic_file').attr('data-itempool-available');
}

/**
 * Returns the itempool options
 * @return array|string
 */
function getItempoolOptions(parsed = false): string | Record<string, string[]> {
    if (parsed) {
        try {
            return isItempoolAvailable() ? JSON.parse($('#gradeable_rubric.electronic_file').attr('data-itempool-options')!) as Record<string, string[]> : {};
        }
        catch {
            displayErrorMessage('Something went wrong retrieving itempool options');
            return {};
        }
    }
    else {
        return $('#gradeable_rubric.electronic_file').attr('data-itempool-options')!;
    }
}

/**
 * Shows the 'in progress' indicator for a component
 * @param {int} component_id
 * @param {boolean} show
 */
function setComponentInProgress(component_id: number, show = true) {
    const domElement = getComponentJQuery(component_id);
    domElement.find('.save-tools span').hide();
    if (show) {
        domElement.find('.save-tools-in-progress').show();
    }
    else {
        domElement.find('.save-tools :not(.save-tools-in-progress)').show();
    }
}

/**
 * Enables reordering on marks in an edit-mode component
 * @param {int} component_id
 */
function setupSortableMarks(component_id: number) {
    const markList: JQuery<HTMLElement> = getComponentJQuery(component_id).find('.ta-rubric-table');
    markList.sortable({
        items: 'div:not(.mark-first,.add-new-mark-container)',
    });
    markList.on('keydown', keyPressHandler);
    markList.disableSelection();
}

/**
 * Enables reordering on components for instructor edit mode
 */
function setupSortableComponents() {
    const componentList = $('#component-list');
    componentList.sortable({
        update: void onComponentOrderChange,
        handle: '.reorder-component-container',
    });
    componentList.on('keydown', keyPressHandler);
    componentList.disableSelection();
}

/**
 * Key press handler for jquery sortable elements
 * @param {KeyboardEvent} e
 */
function keyPressHandler(e: JQueryKeyEventObject) {
    // Enable ctrl-a to select all
    if (e.key === 'a' && e.ctrlKey) {
        (e.target as HTMLInputElement).select();
    }
}

/**
 * Sets the HTML contents of the specified component container
 * @param {int} component_id
 * @param {string} contents
 */
function setComponentContents(component_id: number, contents: string) {
    getComponentJQuery(component_id).parent('.component-container').html(contents);

    // Enable sorting for this component if in edit mode
    if (isEditModeEnabled()) {
        setupSortableMarks(component_id);
    }
}

/**
 * Sets the HTML contents of the specified component's header
 * @param {int} component_id
 * @param {string} contents
 */
function setComponentHeaderContents(component_id: number, contents: string | Element | DocumentFragment | Document | Comment | ((this: HTMLElement, index: number, oldhtml: JQuery.htmlString) => JQuery.htmlString | JQuery.Node)) {
    getComponentJQuery(component_id).find('.header-block').html(contents);
}

/**
 * Sets the HTML contents of the total scores box
 * @param {string} contents
 */
function setTotalScoreBoxContents(contents: string | Element | DocumentFragment | Document | Comment | ((this: HTMLElement, index: number, oldhtml: JQuery.htmlString) => JQuery.htmlString | JQuery.Node)) {
    $('#total-score-container').html(contents);
}

/**
 * Sets the HTML contents of the rubric total box (instructor edit mode)
 * @param contents
 */
function setRubricTotalBoxContents(contents: string | Element | DocumentFragment | Document | Comment | ((this: HTMLElement, index: number, oldhtml: JQuery.htmlString) => JQuery.htmlString | JQuery.Node)) {
    $('#rubric-total-container').html(contents);
}

/**
 * Gets the count direction for a component in instructor edit mode
 * @param {int} component_id
 * @returns {int} COUNT_DIRECTION_UP or COUNT_DIRECTION_DOWN
 */
function getCountDirection(component_id: number) {
    if (getComponentJQuery(component_id).find('input.count-up-selector').is(':checked')) {
        return COUNT_DIRECTION_UP;
    }
    else {
        return COUNT_DIRECTION_DOWN;
    }
}

/**
 * Sets the title of a mark
 * Note: This only changes the text in the DOM, so it should be only called on open components
 * @param {int} mark_id
 * @param {string} title
 */
function setMarkTitle(mark_id: number, title: string | number | string[] | ((this: HTMLElement, index: number, value: string) => string)) {
    getMarkJQuery(mark_id).find('.mark-title textarea').val(title);
}

/**
 * Loads all components from the DOM
 * @returns {Array}
 */
function getAllComponentsFromDOM() {
    const components: Component[] = [];
    $('.component').each(function () {
        components.push(getComponentFromDOM(getComponentIdFromDOMElement(this)));
    });
    return components;
}

/**
 * Gets the page number assigned to a component
 * @param {int} component_id
 * @returns {int}
 */
function getComponentPageNumber(component_id: number) {
    const domElement = getComponentJQuery(component_id);
    if (isInstructorEditEnabled()) {
        const pageNumberInput: JQuery<HTMLInputElement> = domElement.find('input.page-number');
        return parseInt(pageNumberInput.val()!);
    }
    else {
        return parseInt(domElement.attr('data-page')!);
    }
}

/**
 * Extracts a component object from the DOM
 * @param {int} component_id
 * @return {Object}
 */
function getComponentFromDOM(component_id: number):
Component {
    const domElement = getComponentJQuery(component_id);

    if (isInstructorEditEnabled() && isComponentOpen(component_id)) {
        const penaltyInput: JQuery<HTMLInputElement> = domElement.find('input.penalty-points');
        const penaltyPoints = Math.abs(parseFloat(penaltyInput.val()!));
        const maxValueInput: JQuery<HTMLInputElement> = domElement.find('input.max-points');
        const maxValue = Math.abs(parseFloat(maxValueInput.val()!));
        const extraCreditInput: JQuery<HTMLInputElement> = domElement.find('input.extra-credit-points');
        const extraCreditPoints = Math.abs(parseFloat(extraCreditInput.val()!));
        const countUp = getCountDirection(component_id) !== COUNT_DIRECTION_DOWN;

        return {
            id: component_id,
            title: domElement.find('input.component-title').val() as string,
            ta_comment: domElement.find('textarea.ta-comment').val() as string,
            student_comment: domElement.find('textarea.student-comment').val() as string,
            page: getComponentPageNumber(component_id),
            lower_clamp: -penaltyPoints,
            default: countUp ? 0.0 : maxValue,
            max_value: maxValue,
            upper_clamp: maxValue + extraCreditPoints,
            marks: getMarkListFromDOM(component_id),
            is_itempool_linked: domElement.find(`#yes-link-item-pool-${component_id}`).is(':checked'),
            itempool_option: domElement.find('select[name="component-itempool"]').val() as string,
            peer: (domElement.attr('data-peer') === 'true'),
        };
    }
    return {
        id: component_id,
        title: domElement.attr('data-title') as string,
        ta_comment: domElement.attr('data-ta_comment') as string,
        student_comment: domElement.attr('data-student_comment') as string,
        page: parseInt(domElement.attr('data-page')!),
        lower_clamp: parseFloat(domElement.attr('data-lower_clamp')!),
        default: parseFloat(domElement.attr('data-default')!),
        max_value: parseFloat(domElement.attr('data-max_value')!),
        upper_clamp: parseFloat(domElement.attr('data-upper_clamp')!),
        marks: getMarkListFromDOM(component_id),
        is_itempool_linked: domElement.find(`#yes-link-item-pool-${component_id}`).is(':checked'),
        itempool_option: domElement.find('select[name="component-itempool"]').val() as string,
        peer: (domElement.attr('data-peer') === 'true'),
    };
}

/**
 * Extracts an array of marks from the DOM
 * @param {int} component_id
 * @return {Array}
 */
function getMarkListFromDOM(component_id: number): Mark[] {
    const domElement = getComponentJQuery(component_id);
    const markList: Mark[] = [];
    let i = 0;
    domElement.find('.ta-rubric-table .mark-container').each(function () {
        const mark = getMarkFromDOM(parseInt($(this).attr('data-mark_id')!));

        // Don't add the custom mark
        if (mark === null) {
            return;
        }
        mark.order = i;
        markList.push(mark);
        i++;
    });
    return markList;
}

/**
 * Extracts a mark from the DOM
 * @param {int} mark_id
 * @return {Object}
 */
function getMarkFromDOM(mark_id: number): Mark | null {
    const domElement = getMarkJQuery(mark_id);
    if (isEditModeEnabled()) {
        const pointsInput: JQuery<HTMLInputElement> = domElement.find('input[type=number]');
        return {
            id: parseInt(domElement.attr('data-mark_id')!),
            points: parseFloat(pointsInput.val()!),
            title: domElement.find('textarea').val()!,
            deleted: domElement.hasClass('mark-deleted'),
            publish: domElement.find('.mark-publish-container input[type=checkbox]').is(':checked'),
        };
    }
    else {
        if (mark_id === 0) {
            return null;
        }
        return {
            id: parseInt(domElement.attr('data-mark_id')!),
            points: parseFloat(domElement.find('.mark-points').attr('data-points')!),
            title: domElement.find('.mark-title').attr('data-title')!,
            publish: domElement.attr('data-publish') === 'true',
        };
    }
}

/**
 * Gets if a component exists for this gradeable
 * @param {int} component_id
 * @return {boolean}
 */
function componentExists(component_id: number) {
    return getComponentJQuery(component_id).length > 0;
}

/**
 * Extracts a graded component object from the DOM
 * @param {int} component_id
 * @return {Object}
 */
function getGradedComponentFromDOM(component_id: number): ComponentGradeInfo {
    const domElement = getComponentJQuery(component_id);
    const customMarkContainer = domElement.find('.custom-mark-container');

    // Get all of the marks that are 'selected'
    const mark_ids: number[] = [];
    let customMarkSelected = false;
    domElement.find('span.mark-selected').each(function () {
        const mark_id = parseInt($(this).attr('data-mark_id')!);
        if (mark_id === CUSTOM_MARK_ID) {
            customMarkSelected = true;
        }
        else {
            mark_ids.push(mark_id);
        }
    });

    let score = 0.0;
    let comment = '';
    if (isEditModeEnabled()) {
        const customMarkDOMElement = domElement.find('.custom-mark-data');
        score = parseFloat(customMarkDOMElement.attr('data-score')!);
        comment = customMarkDOMElement.attr('data-comment')!;
        customMarkSelected = customMarkDOMElement.attr('data-selected') === 'true';
    }
    else {
        const scoreInput: JQuery<HTMLInputElement> = customMarkContainer.find('input[type=number]');
        score = parseFloat(scoreInput.val()!);
        comment = customMarkContainer.find('textarea').val()!;
    }

    const dataDOMElement = domElement.find('.graded-component-data');
    let gradedVersion = dataDOMElement.attr('data-graded_version')!;
    if (gradedVersion === '') {
        gradedVersion = getDisplayVersion().toString();
    }
    return {
        score: score,
        comment: comment,
        custom_mark_selected: customMarkSelected,
        mark_ids: mark_ids,
        graded_version: parseInt(gradedVersion),
        grade_time: dataDOMElement.attr('data-grade_time')!,
        grader_id: dataDOMElement.attr('data-grader_id')!,
        verifier_id: dataDOMElement.attr('data-verifier_id')!,
        custom_mark_enabled: CUSTOM_MARK_ID,
    };
}
/**
 * Gets the scores data from the DOM (auto grading earned/possible and ta grading possible)
 * @return {Object}
 */
function getScoresFromDOM() {
    const dataDOMElement = $('#gradeable-scores-id');
    const scores: {
        user_group: number;
        ta_grading_earned: number | undefined;
        ta_grading_total: number;
        peer_grade_earned: number | undefined;
        peer_total: number;
        auto_grading_earned?: number;
        auto_grading_total?: number;
        auto_grading_complete: boolean;
    } = {
        user_group: GRADED_GRADEABLE!.user_group,
        ta_grading_earned: getTaGradingEarned(),
        ta_grading_total: getTaGradingTotal(),
        peer_grade_earned: getPeerGradingEarned(),
        peer_total: getPeerGradingTotal(),
        auto_grading_complete: false,
    };

    // Then check if auto grading scorse exist before adding them
    const autoGradingTotal = dataDOMElement.attr('data-auto_grading_total')!;
    if (autoGradingTotal !== '') {
        scores.auto_grading_earned = parseInt(dataDOMElement.attr('data-auto_grading_earned')!);
        scores.auto_grading_total = parseInt(autoGradingTotal);
        scores.auto_grading_complete = true;
    }

    return scores;
}

/**
 * Gets the rubric total / extra credit from the DOM
 * @return {Object}
 */
function getRubricTotalFromDOM() {
    let total = 0;
    let extra_credit = 0;
    getAllComponentsFromDOM().forEach((component) => {
        total += component.max_value;
        extra_credit += component.upper_clamp - component.max_value;
    });
    return {
        total: total,
        extra_credit: extra_credit,
    };
}

/**
 * Gets the number of ta grading points the student has been awarded
 * @return {number|undefined} Undefined if no score data exists
 */
function getTaGradingEarned() {
    let total = 0.0;
    let anyPoints = false;
    $('.graded-component-data').each(function () {
        const pointsEarned = $(this).attr('data-total_score')!;
        if (pointsEarned === '') {
            return;
        }
        total += parseFloat(pointsEarned);
        anyPoints = true;
    });
    if (!anyPoints) {
        return undefined;
    }
    return total;
}

/**
 * Gets the number of peer grading points the student has been awarded
 * @return {number|undefined} Undefined if no score data exists
 */
function getPeerGradingEarned() {
    let total = 0.0;
    let anyPoints = false;
    $('.peer-graded-component-data').each(function () {
        const pointsEarned = $(this).attr('data-total_score')!;
        if (pointsEarned === '') {
            return;
        }
        total += parseFloat(pointsEarned);
        anyPoints = true;
    });
    if (!anyPoints) {
        return undefined;
    }
    return total;
}

/**
 * Gets the number of ta grading points that can be earned
 * @return {number}
 */
function getTaGradingTotal() {
    let total = 0.0;
    $('.ta-component').each(function () {
        total += parseFloat($(this).attr('data-max_value')!);
    });
    return total;
}
/**
 * Gets the number of Peer grading points that can be earned
 * @return {number}
 */
function getPeerGradingTotal() {
    let total = 0.0;
    $('.peer-component').each(function () {
        total += parseFloat($(this).attr('data-max_value')!);
    });
    return total;
}

/**
 * Gets the ids of all open components
 * @return {Array}
 */
function getOpenComponentIds(itempool_only = false) {
    const component_ids: number[] = [];
    if (itempool_only) {
        $('.ta-rubric-table:visible').each(function () {
            const component = $(`#component-${$(this).attr('data-component_id')}`);
            if (component && component.attr('data-itempool_id')) {
                component_ids.push(parseInt($(this).attr('data-component_id')!));
            }
        });
    }
    else {
        $('.ta-rubric-table:visible').each(function () {
            component_ids.push(parseInt($(this).attr('data-component_id')!));
        });
    }
    return component_ids;
}

/**
 * Gets the component id from its order on the page
 * @param {int} order
 * @return {int}
 */
export function getComponentIdByOrder(order: number) {
    return parseInt($('.component-container').eq(order).find('.component').attr('data-component_id')!);
}

/**
 * Gets the orders of the components indexed by component id
 * @return {Object}
 */
function getComponentOrders() {
    const orders: Record<number, number> = {};
    $('.component').each(function (order) {
        const id = getComponentIdFromDOMElement(this);
        orders[id] = order;
    });
    return orders;
}

/**
 * Gets the id of the next component in the list
 * @param {int} component_id
 * @return {int}
 */
export function getNextComponentId(component_id: number): number {
    return parseInt(getComponentJQuery(component_id).parent('.component-container').next().children('.component').attr('data-component_id')!, 10);
}

/**
 * Gets the id of the previous component in the list
 * @param {int} component_id
 * @return {int}
 */
export function getPrevComponentId(component_id: number): number {
    return parseInt(getComponentJQuery(component_id).parent('.component-container').prev().children('.component').attr('data-component_id')!, 10);
}

/**
 * Gets the first open component on the page
 * @return {int}
 */
export function getFirstOpenComponentId(itempool_only = false): number {
    const component_ids = getOpenComponentIds(itempool_only);
    if (component_ids.length === 0) {
        return NO_COMPONENT_ID;
    }
    return component_ids[0];
}

/**
 * Gets the number of components on the page
 * @return {int}
 */
function getComponentCount() {
    // noinspection JSValidateTypes
    return $('.component-container').length;
}

/**
 * Gets the mark id for a component and order
 * @param {int} component_id
 * @param {int} mark_order
 * @returns {int} Mark id or 0 if out of bounds
 */
export function getMarkIdFromOrder(component_id: number, mark_order: number): number {
    const jquery = getComponentJQuery(component_id).find('.mark-container');
    if (mark_order < jquery.length) {
        return parseInt(jquery.eq(mark_order).attr('data-mark_id')!);
    }
    return 0;
}

/**
 * Gets the id of the open component from the cookie
 * @return {int} Returns zero of no open component exists
 */
function getOpenComponentIdFromCookie() {
    const component_id = parseInt(window.Cookies.get('open_component_id') ?? '');
    if (isNaN(component_id)) {
        return NO_COMPONENT_ID;
    }
    return component_id;
}

/**
 * Updates the open component in the cookie
 */
function updateCookieComponent() {
    window.Cookies.set('open_component_id', getFirstOpenComponentId().toString(), { path: '/' });
}

/**
 * Gets the id of the no credit / full credit mark of a component
 * @param {int} component_id
 * @return {int}
 */
function getComponentFirstMarkId(component_id: number) {
    return parseInt(getComponentJQuery(component_id).find('.mark-container').first().attr('data-mark_id')!);
}

/**
 * Gets if a component is open
 * @param {int} component_id
 * @return {boolean}
 */
function isComponentOpen(component_id: number) {
    return !getComponentJQuery(component_id).find('.ta-rubric-table').is(':hidden');
}

/**
 * Gets if a mark is 'checked'
 * @param {int} mark_id
 * @return {boolean}
 */
function isMarkChecked(mark_id: number) {
    return getMarkJQuery(mark_id).find('span.mark-selected').length > 0;
}

/**
 * Gets if a mark is disabled (shouldn't be checked
 * @param {int} mark_id
 * @returns {boolean}
 */
function isMarkDisabled(mark_id: number) {
    return getMarkJQuery(mark_id).hasClass('mark-disabled');
}

/**
 * Gets if a mark was marked for deletion
 * @param {int} mark_id
 * @return {boolean}
 */
export function isMarkDeleted(mark_id: number) {
    return getMarkJQuery(mark_id).hasClass('mark-deleted');
}

/**
 * Gets if the state of the custom mark is such that it should appear checked
 * Note: if the component is in edit mode, this will never return true
 * @param {int} component_id
 * @return {boolean}
 */
function hasCustomMark(component_id: number) {
    if (isEditModeEnabled()) {
        return false;
    }
    const gradedComponent = getGradedComponentFromDOM(component_id);
    return gradedComponent.comment !== '';
}

/**
 * Gets if the custom mark on a component is 'checked'
 * @param {int} component_id
 * @return {boolean}
 */
function isCustomMarkChecked(component_id: number) {
    return getCustomMarkJQuery(component_id).find('.mark-selected').length > 0;
}

/**
 * Checks the custom mark checkbox
 * @param {int} component_id
 */
function checkDOMCustomMark(component_id: number) {
    getCustomMarkJQuery(component_id).find('.mark-selector').addClass('mark-selected');
}

/**
 * Un-checks the custom mark checkbox
 * @param {int} component_id
 */
function unCheckDOMCustomMark(component_id: number) {
    getCustomMarkJQuery(component_id).find('.mark-selector').removeClass('mark-selected');
}

/**
 * Toggles the state of the custom mark checkbox in the DOM
 * @param {int} component_id
 */
function toggleDOMCustomMark(component_id: number) {
    getCustomMarkJQuery(component_id).find('.mark-selector').toggleClass('mark-selected');
}

/**
 * Opens the 'users who got mark' dialog
 * @param {string} component_title
 * @param {string} mark_title
 * @param {Object} stats
 */
function openMarkStatsPopup(component_title: string, mark_title: string, stats: Stats) {
    const popup = $('#student-marklist-popup');

    popup.find('.question-title').html(component_title);
    popup.find('.mark-title').html(mark_title);
    popup.find('.section-submitter-count').html(stats.section_submitter_count);
    popup.find('.total-submitter-count').html(stats.total_submitter_count);
    popup.find('.section-graded-component-count').html(stats.section_graded_component_count);
    popup.find('.total-graded-component-count').html(stats.total_graded_component_count);
    popup.find('.section-total-component-count').html(stats.section_total_component_count);
    popup.find('.total-total-component-count').html(stats.total_total_component_count);

    // Create an array of links for each submitter
    const submitterHtmlElements: string[] = [];
    const location = window.location.href.split('?');
    let base_url = location[0];
    if (base_url.slice(base_url.length - 6) === 'update') {
        base_url = `${base_url.slice(0, -6)}grading/grade`;
    }
    const search_params = new URLSearchParams(location[1]);
    stats.submitter_ids.forEach((id: string | number) => {
        search_params.set('who_id', stats.submitter_anon_ids[id] ?? id);
        submitterHtmlElements.push(`<a href="${base_url}?${search_params.toString()}">${id}</a>`);
    });
    popup.find('.student-names').html(submitterHtmlElements.join(', '));

    // Hide all other (potentially) open popups
    $('.popup-form').hide();

    // Open the popup
    popup.show();
}

/**
 * Gets if there are any loaded unverified components
 * @returns {boolean}
 */
function anyUnverifiedComponents() {
    return $('.verify-container').length > 0;
}

/**
 * Hides the verify all button if there are no components to verify
 */
function updateVerifyAllButton() {
    if (!anyUnverifiedComponents()) {
        $('#verify-all').hide();
    }
    else {
        $('#verify-all').show();
    }
}

/**
 * Gets if the provided graded component is in conflict with the display version
 * @param {Object} graded_component
 * @returns {boolean}
 */
function getComponentVersionConflict(graded_component: { graded_version: number } | undefined) {
    return graded_component !== undefined && graded_component.graded_version !== getDisplayVersion();
}

/**
 * Sets the error state of the custom mark message
 * @param {int} component_id
 * @param {boolean} show_error
 */
function setCustomMarkError(component_id: number, show_error: boolean) {
    const jquery = getComponentJQuery(component_id).find('textarea.mark-note-custom');
    const c = 'custom-mark-error';
    if (show_error) {
        jquery.addClass(c);
        jquery.prop('title', 'Custom mark cannot be blank!');
    }
    else {
        jquery.removeClass(c);
        jquery.prop('title', '');
    }
}

/**
 * Changes the disabled state of the edit mode box
 * @param disabled
 */
function disableEditModeBox(disabled: string | number | boolean | symbol | object | null | undefined) {
    $('#edit-mode-enabled').prop('disabled', disabled);
}

/**
 * DOM Callback methods
 *
 */

/**
 * Called when the 'add new mark' div gets pressed
 * @param me DOM element of the 'add new mark' div
 */
window.onAddNewMark = async function (me: HTMLElement) {
    try {
        await addNewMark(getComponentIdFromDOMElement(me));
    }
    catch (err) {
        console.error(err);
        alert(`Error adding mark! ${(err as Error).message}`);
    }
};

/**
 * Called when a mark is marked for deletion
 * @param me DOM Element of the delete button
 */
window.onDeleteMark = function (me: HTMLElement) {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
};

/**
 * Called when a mark marked for deletion gets restored
 * @param me DOM Element of the restore button
 */
window.onRestoreMark = function (me: HTMLElement) {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
};

/**
 * Called when a component is deleted
 * @param me DOM Element of the delete button
 */
window.onDeleteComponent = async function (me: HTMLElement) {
    const componentCount = $('.component-container').length;
    if (componentCount === 1) {
        displayErrorMessage('Cannot delete the only component.');
        return;
    }

    if (!confirm('Are you sure you want to delete this component?')) {
        return;
    }
    try {
        await deleteComponent(getComponentIdFromDOMElement(me));
    }
    catch (err) {
        console.error(err);
        alert(`Failed to delete component! ${(err as Error).message}`);
    }
    try {
        await reloadInstructorEditRubric(getGradeableId(), !!isItempoolAvailable(), getItempoolOptions() as Record<string, string[]>);
    }
    catch (err) {
        alert(`Failed to reload rubric! ${(err as Error).message}`);
    }
};

/**
 * Called when the 'add new component' button is pressed
 */
window.onAddComponent = async function (peer: boolean) {
    try {
        await addComponent(peer);
    }
    catch (err) {
        console.error(err);
        alert(`Failed to add component! ${(err as Error).message}`);
    }
    try {
        await closeAllComponents(true, true);
        await reloadInstructorEditRubric(getGradeableId(), !!isItempoolAvailable(), getItempoolOptions() as Record<string, string[]>);
        await openComponent(getComponentIdByOrder(getComponentCount() - 1));
    }
    catch (err) {
        alert(`Failed to reload rubric! ${(err as Error).message}`);
    }
};

/**
 * Called when the 'Import Components' button is pressed
 */
window.importComponentsFromFile = async function () {
    const submit_url = buildCourseUrl(['gradeable', getGradeableId(), 'components', 'import']);
    const formData = new FormData();

    const files = ($('#import-components-file')[0] as HTMLInputElement).files!;

    if (files.length === 0) {
        return;
    }

    // Files selected
    for (let i = 0; i < files.length; i++) {
        formData.append(`files${i}`, files[i], files[i].name);
    }

    formData.append('csrf_token', window.csrfToken);

    let response: Record<string, string> | null;
    try {
        response = await $.ajax({
            dataType: 'json',
            url: submit_url,
            data: formData,
            processData: false,
            contentType: false,
            type: 'POST',
        }) as Record<string, string>;
    }
    catch (err) {
        console.log(err);
        alert('Error parsing response from server. Please copy the contents of your Javascript Console and '
            + 'send it to an administrator, as well as what you were doing and what files you were uploading. - [handleUploadGradeableComponents]');
        return;
    }
    if (response.status !== 'success') {
        console.error(`Something went wrong importing components: ${response.message}`);
    }
    else {
        location.reload();
    }
};

/**
 * Called when the point value of a common mark changes
 * @param me DOM Element of the mark point entry
 */
window.onMarkPointsChange = async function (me: HTMLElement) {
    try {
        await refreshComponentHeader(getComponentIdFromDOMElement(me), true);
    }
    catch (err) {
        console.error(err);
        alert(`Error updating component! ${(err as Error).message}`);
    }
};

/**
 * Called when the mark stats button is pressed
 * @param me DOM Element of the mark stats button
 */
window.onGetMarkStats = async function (me: HTMLElement) {
    const component_id = getComponentIdFromDOMElement(me);
    const mark_id = getMarkIdFromDOMElement(me);
    try {
        const stats = await ajaxGetMarkStats(getGradeableId(), component_id, mark_id);
        const component_title = getComponentFromDOM(component_id).title;
        const mark_title = getMarkFromDOM(mark_id)!.title;

        openMarkStatsPopup(component_title, mark_title!, stats);
    }
    catch (err) {
        alert(`Failed to get stats for mark: ${(err as Error).message}`);
    }
};

/**
 * Called when a component gets clicked (for opening / closing)
 * @param me DOM Element of the component header div
 * @param edit_mode editing from ta grading page or instructor edit gradeable page
 */
window.onClickComponent = async function (me: HTMLElement, edit_mode = false) {
    const component_id = getComponentIdFromDOMElement(me);
    try {
        await toggleComponent(component_id, true, edit_mode);
    }
    catch (err) {
        console.error(err);
        setComponentInProgress(component_id, false);
        alert(`Error opening/closing component! ${(err as Error).message}`);
    }
};

/**
 * Called when the 'cancel' button is pressed on an open component
 * @param me DOM Element of the cancel button
 */
window.onCancelComponent = async function (me: HTMLElement) {
    const component_id = getComponentIdFromDOMElement(me);
    const gradeable_id = getGradeableId();
    const anon_id = getAnonId();
    const component = await ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
    const customMarkNote = $(`#component-${component_id}`).find('.mark-note-custom').val();
    // If there is any changes made in comment of a component , prompt the TA
    if ((component && component.comment !== customMarkNote) || (!component && customMarkNote !== '')) {
        if (confirm('Are you sure you want to discard all changes to the student message?')) {
            try {
                await toggleComponent(component_id, false);
            }
            catch (err) {
                console.error(err);
                alert(`Error closing component! ${(err as Error).message}`);
            }
        }
    }
    // There is no change in comment, i.e it is same as the saved comment (before)
    else {
        try {
            await toggleComponent(component_id, false);
        }
        catch (err) {
            console.error(err);
            alert(`Error closing component! ${(err as Error).message}`);
        }
    }
};

window.onCancelEditRubricComponent = function (me: HTMLElement) {
    const component_id = getComponentIdFromDOMElement(me);
    void toggleComponent(component_id, false, true);
};

/**
 * Called when the overall comment box is changed
 */
window.onChangeOverallComment = async function () {
    // Get the current grader so that we can get their comment from the dom.
    const grader = getGraderId();
    const currentOverallComment = $(`textarea#overall-comment-${grader}`).val();
    const previousOverallComment = $(`textarea#overall-comment-${grader}`).data('previous-comment') as string;

    if (currentOverallComment !== previousOverallComment && currentOverallComment !== undefined) {
        $('.overall-comment-status').text('Saving Changes...');
        // If anything has changed, save the changes.
        try {
            await ajaxSaveOverallComment(getGradeableId(), getAnonId(), currentOverallComment);
            $('.overall-comment-status').text('All Changes Saved');
            // Update the current comment in the DOM.
            $(`textarea#overall-comment-${grader}`).data('previous-comment', currentOverallComment);
        }
        catch {
            $('.overall-comment-status').text('Error Saving Changes');
        }
    }
};

/**
 * When the component order changes, update the server
 */
async function onComponentOrderChange() {
    try {
        await ajaxSaveComponentOrder(getGradeableId(), getComponentOrders());
    }
    catch (err) {
        console.error(err);
        alert(`Error reordering components! ${(err as Error).message}`);
    }
}

/**
 * Called when a mark is clicked in grade mode
 * @param me DOM Element of the mark div
 */
window.onToggleMark = async function (me: HTMLElement) {
    try {
        await toggleCommonMark(getComponentIdFromDOMElement(me), getMarkIdFromDOMElement(me));
    }
    catch (err) {
        console.error(err);
        alert(`Error toggling mark! ${(err as Error).message}`);
    }
};

/**
 * Called when one of the custom mark fields changes
 * @param me DOM Element of one of the custom mark's elements
 */
window.onCustomMarkChange = async function (me: HTMLElement) {
    try {
        await updateCustomMark(getComponentIdFromDOMElement(me));
    }
    catch (err) {
        console.error(err);
        alert(`Error updating custom mark! ${(err as Error).message}`);
    }
};

/**
 * Toggles the 'checked' state of the custom mark.  This effectively
 *  makes the 'score' and 'comment' values 0 and '' respectively when
 *  loading the graded component from the DOM, but leaves the values in
 *  the DOM if the user toggles this again.
 * @param me
 */
window.onToggleCustomMark = async function (me: HTMLElement) {
    const component_id = getComponentIdFromDOMElement(me);
    const graded_component = getGradedComponentFromDOM(component_id);
    if (graded_component.comment === '') {
        setCustomMarkError(component_id, true);
        return;
    }
    toggleDOMCustomMark(component_id);
    try {
        await toggleCustomMark(component_id);
    }
    catch (err) {
        console.error(err);
        alert(`Error toggling custom mark! ${(err as Error).message}`);
    }
};

/**
 * Callback for the 'verify' buttons
 * @param me DOM Element of the verify button
 */
window.onVerifyComponent = async function (me: HTMLElement) {
    try {
        await verifyComponent(getComponentIdFromDOMElement(me));
    }
    catch (err) {
        console.error(err);
        alert(`Error verifying component! ${(err as Error).message}`);
    }
};

/**
 * Callback for the 'verify all' button
 */
window.onVerifyAll = async function () {
    try {
        await verifyAllComponents();
    }
    catch (err) {
        console.error(err);
        alert(`Error verifying all components! ${(err as Error).message}`);
    }
};

/**
 * Callback for the 'edit mode' checkbox changing states
 */
export async function onToggleEditMode() {
    // Get the open components so we know which one to open once they're all saved
    const open_component_ids = getOpenComponentIds();
    let reopen_component_id = NO_COMPONENT_ID;

    // This prevents multiple sequential toggles from screwing things up
    disableEditModeBox(true);

    if (open_component_ids.length !== 0) {
        reopen_component_id = open_component_ids[0];
    }
    else {
        updateEditModeEnabled();
        disableEditModeBox(false);
        return;
    }

    setComponentInProgress(reopen_component_id);
    try {
        await saveComponent(reopen_component_id);
    }
    catch (err) {
        console.error(err);
        alert(`Error saving component! ${(err as Error).message}`);
    }
    try {
    // Once components are saved, reload the component in edit mode
        updateEditModeEnabled();
        if (reopen_component_id !== NO_COMPONENT_ID) {
            await reloadGradingComponent(reopen_component_id, isEditModeEnabled(), true);
        }
    }
    catch (err) {
        console.error(err);
        alert(`Error reloading component! ${(err as Error).message}`);
    }
    disableEditModeBox(false);
}

/**
 * Callback for the 'count up' option of a component in instructor edit mode
 * @param me DOM element of the 'count up' radio button
 */
window.onClickCountUp = function (me: HTMLElement) {
    const component_id = getComponentIdFromDOMElement(me);
    const mark_id = getComponentFirstMarkId(component_id);
    setMarkTitle(mark_id, 'No Credit');
    $.get('Mark.twig', '', () => {
        const marks: JQuery<HTMLInputElement> = $("input[id^='mark-editor-']");
        marks.each(function () {
            $(this).attr('overall', 'No Credit');
            if (parseInt(this.value) < 0) {
                this.style.backgroundColor = 'var(--standard-vibrant-yellow)';
            }
            else {
                this.style.backgroundColor = 'var(--default-white)';
            }
        });
    });
};

/**
 * Callback for the 'count down' option of a component in instructor edit mode
 * @param me DOM element of the 'count down' radio button
 */
window.onClickCountDown = function (me: HTMLElement) {
    const component_id = getComponentIdFromDOMElement(me);
    const mark_id = getComponentFirstMarkId(component_id);
    setMarkTitle(mark_id, 'Full Credit');
    $.get('Mark.twig', '', () => {
        const marks: JQuery<HTMLInputElement> = $("input[id^='mark-editor-']");
        marks.each(function () {
            $(this).attr('overall', 'Full Credit');
            if (parseInt(this.value) > 0) {
                this.style.backgroundColor = 'var(--standard-vibrant-yellow)';
            }
            else {
                this.style.backgroundColor = 'var(--default-white)';
            }
        });
    });
};

/**
 * Callback for changing on the point values for a component
 * Does not change point value if not divisible by precision
 * @param me DOM element of the input box
 */
window.onComponentPointsChange = async function (me: HTMLElement) {
    if (dividesEvenly($(me).val() as number, getPointPrecision())) {
        $(me).css('background-color', 'var(--standard-input-background)');
        try {
            await refreshInstructorEditComponentHeader(getComponentIdFromDOMElement(me), true);
        }
        catch (err) {
            console.error(err);
            alert(`Failed to refresh component! ${(err as Error).message}`);
        }
    }
    else {
        // Make box red to indicate error
        $(me).css('background-color', '#ff7777');
    }
};

/**
 * Returns true if dividend is evenly divisible by divisor, false otherwise
 * @param {number} dividend
 * @param {number} divisor
 * @returns {boolean}
 */
function dividesEvenly(dividend: number, divisor: number) {
    const multiplier = Math.pow(10, Math.max(decimalLength(dividend), decimalLength(divisor)));
    return ((dividend * multiplier) % (divisor * multiplier) === 0);
}

/**
 * Returns number of digits after decimal point
 * @param {number} num
 * @returns {int}
 */
function decimalLength(num: { toString: () => string }) {
    return (num.toString().split('.')[1] || '').length;
}

/**
 * Callback for changing the title for a component
 * @param me DOM element of the input box
 */
window.onComponentTitleChange = function (me: HTMLElement) {
    getComponentJQuery(getComponentIdFromDOMElement(me)).find('.component-title-text').text($(me).val() as string);
};

/**
 * Callback for changing the page number for a component
 * @param me DOM element of the input box
 */
window.onComponentPageNumberChange = function (me: HTMLElement) {
    getComponentJQuery(getComponentIdFromDOMElement(me)).find('.component-page-number-text').text($(me).val() as string);
};

/**
 * Callback for changing the 'publish' setting of a mark
 * @param me DOM element of the check box
 */
window.onMarkPublishChange = function (me: HTMLElement) {
    getMarkJQuery(getMarkIdFromDOMElement(me)).toggleClass('mark-publish');
};

/**
 * Put all of the primary logic of the TA grading rubric here
 *
 */

/**
 * Verifies a component with the grader and reloads the component
 * @param {int} component_id
 * @async
 * @returns {void}
 */
async function verifyComponent(component_id: number) {
    const gradeable_id = getGradeableId();
    await ajaxVerifyComponent(gradeable_id, component_id, getAnonId());
    await reloadGradingComponent(component_id);
    updateVerifyAllButton();
}

/**
 * Verifies all graded components and reloads the rubric
 * @async
 * @returns {void}
 */
async function verifyAllComponents() {
    const gradeable_id = getGradeableId();
    const anon_id = getAnonId();
    await ajaxVerifyAllComponents(gradeable_id, anon_id);
    await window.reloadGradingRubric(gradeable_id, anon_id);
    updateVerifyAllButton();
}

/**
 * Adds a blank component to the gradeable
 * @return {Promise}
 */
async function addComponent(peer: boolean) {
    return ajaxAddComponent(getGradeableId(), peer);
}

/**
 * Deletes a component from the server
 * @param {int} component_id
 * @returns {Promise}
 */
function deleteComponent(component_id: number) {
    return ajaxDeleteComponent(getGradeableId(), component_id);
}

/**
 * Sets the gradeable-wide page setting
 * @param {int} page PDF_PAGE_INSTRUCTOR, PDF_PAGE_STUDENT, or PDF_PAGE_NONE
 * @async
 * @return {void}
 */
window.setPdfPageAssignment = async function (page: number) {
    if (page === window.PDF_PAGE_INSTRUCTOR) {
        page = 1;
    }

    await closeAllComponents(true, true);
    await ajaxSaveComponentPages(getGradeableId(), { page: page });
    await reloadInstructorEditRubric(getGradeableId(), !!isItempoolAvailable(), getItempoolOptions() as Record<string, string[]>);
};

/**
 * Searches a array of marks for a mark with an id
 * @param {Object[]} marks
 * @param {int} mark_id
 * @return {Object}
 */
function getMarkFromMarkArray(marks: ReturnType<typeof getMarkListFromDOM>, mark_id: number) {
    for (let i = 0; i < marks.length; ++i) {
        if (marks[i].id === mark_id) {
            return marks[i];
        }
    }
    return null;
}

/**
 * Call this once on page load to load the rubric for grading a submitter
 * Note: This takes 'gradeable_id' and 'anon_id' parameters since it gets called
 *  in the 'RubricPanel.twig' server template
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @async
 * @return {void}
 */
window.reloadGradingRubric = async function (gradeable_id: string, anon_id: string | undefined) {
    let gradeable: Gradeable;
    try {
        gradeable = await ajaxGetGradeableRubric(gradeable_id);
    }
    catch (err) {
        alert(`Could not fetch gradeable rubric: ${(err as Error).message}`);
        return;
    }
    try {
        GRADED_GRADEABLE = await ajaxGetGradedGradeable(gradeable_id, anon_id, false);
    }
    catch (err) {
        alert(`Could not fetch graded gradeable: ${(err as Error).message}`);
        return;
    }
    try {
        loadComponentData(gradeable, GRADED_GRADEABLE);
        const elements = await renderGradingGradeable(getGraderId(), gradeable, GRADED_GRADEABLE!,
            ACTIVE_GRADERS_LIST,
            isGradingDisabled(), !!canVerifyGraders(), getDisplayVersion());
        setRubricDOMElements(elements);
        await openCookieComponent();
    }
    catch (err) {
        alert(`Could not render gradeable: ${(err as Error).message}`);
        console.error(err);
    }
};
/**
* Call this to save components and graded components to global list
* @param {Promise} gradeable
* @param {Promise} graded_gradeable
* @return {Promise}
*/
function loadComponentData(gradeable: Gradeable, graded_gradeable: typeof GRADED_GRADEABLE) {
    for (const component of gradeable.components) {
        COMPONENT_RUBRIC_LIST[component.id] = component;
        if (graded_gradeable!.active_graders[component.id]) {
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            ACTIVE_GRADERS_LIST[component.id] = Object.entries(graded_gradeable!.active_graders[component.id]).map(([_, index]: [string, string | number]) => {
                const grader = graded_gradeable!.active_graders[component.id][index];
                const graderAge = window.luxon.DateTime.fromISO(graded_gradeable!.active_graders_timestamps[component.id][index]).toRelative();
                return `${grader} (${graderAge})`;
            }) ?? [];
        }
        else {
            ACTIVE_GRADERS_LIST[component.id] = [];
        }
    }
    if (graded_gradeable!.graded_components) {
        const graded_array = Object.values(graded_gradeable!.graded_components);
        for (const component of graded_array) {
            GRADED_COMPONENTS_LIST[component.component_id] = component;
        }
    }
    else {
        for (const component of gradeable.components) {
            GRADED_COMPONENTS_LIST[component.id] = undefined;
        }
    }
}
/**
 * Call this to update the totals and subtotals once a grader is done grading a component
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @async
 * @return {void}
 */

async function updateTotals(gradeable_id: string | undefined, anon_id: string | undefined) {
    let gradeable, graded_gradeable;
    try {
        gradeable = await ajaxGetGradeableRubric(gradeable_id!);
    }
    catch (err) {
        alert(`Could not fetch gradeable rubric: ${(err as Error).message}`);
    }
    try {
        graded_gradeable = await ajaxGetGradedGradeable(gradeable_id!, anon_id, false);
    }
    catch (err) {
        alert(`Could not fetch graded gradeable: ${(err as Error).message}`);
    }
    const elements = await renderGradingGradeable(getGraderId(), gradeable as object, graded_gradeable as object,
        ACTIVE_GRADERS_LIST,
        isGradingDisabled(), !!canVerifyGraders(), getDisplayVersion());
    setRubricDOMElements(elements);
}

/**
 * Call this once on page load to load the peer panel.
 * Note: This takes 'gradeable_id' and 'anon_id' parameters since it gets called
 *  in the 'PeerPanel.twig' server template
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @async
 * @return {void}
 */
window.reloadPeerRubric = async function (gradeable_id: string, anon_id: string) {
    TA_GRADING_PEER = true;
    let gradeable, graded_gradeable;
    try {
        gradeable = await ajaxGetGradeableRubric(gradeable_id);
    }
    catch (err) {
        alert(`Could not fetch gradeable rubric: ${(err as Error).message}`);
    }
    try {
        graded_gradeable = await ajaxGetGradedGradeable(gradeable_id, anon_id, true);
    }
    catch (err) {
        alert(`Could not fetch graded gradeable: ${(err as Error).message}`);
    }
    try {
        const elements = await renderPeerGradeable(getGraderId(), gradeable as object, graded_gradeable as object,
            true, false, getDisplayVersion());
        const gradingBox = $('#peer-grading-box');
        gradingBox.html(elements);
    }
    catch (err) {
        alert(`Could not render gradeable: ${(err as Error).message}`);
        console.error(err);
    }
};

/**
 * Call this once on page load to load the rubric instructor editing
 * @param {string} gradeable_id
 * @param {bool} itempool_available
 * @param {array} itempool_options
 * @async
 * @return {void}
 */
async function reloadInstructorEditRubric(gradeable_id: string, itempool_available: boolean, itempool_options: Record<string, string[]>) {
    let gradeable;
    try {
        gradeable = await ajaxGetGradeableRubric(gradeable_id);
    }
    catch (err) {
        alert(`Could not fetch gradeable rubric: ${(err as Error).message}`);
    }
    try {
        const elements = await renderInstructorEditGradeable(gradeable, itempool_available, itempool_options);
        setRubricDOMElements(elements);
        await refreshRubricTotalBox();
        await openCookieComponent();
    }
    catch (err) {
        alert(`Could not render gradeable: ${(err as Error).message}`);
        console.error(err);
    }
}
window.reloadInstructorEditRubric = reloadInstructorEditRubric;

/**
 * Reloads the provided component with the grader view
 * @param {int} component_id
 * @param {boolean} editable True to load component in edit mode
 * @param {boolean} showMarkList True to show mark list as open
 * @async
 * @returns {void}
 */
async function reloadGradingComponent(component_id: number, editable = false, showMarkList = false) {
    const gradeable_id = getGradeableId();
    void ajaxGetGradedGradeable(gradeable_id, getAnonId(), false);
    const component = await ajaxGetComponentRubric(gradeable_id, component_id);
    // Set the global mark list data for this component for conflict resolution
    OLD_MARK_LIST[component_id] = component.marks;
    COMPONENT_RUBRIC_LIST[component_id] = component;
    const graded_component = await ajaxGetGradedComponent(gradeable_id, component_id, getAnonId());
    // Set the global graded component list data for this component to detect changes
    OLD_GRADED_COMPONENT_LIST[component_id] = graded_component!;
    GRADED_COMPONENTS_LIST[component_id] = graded_component;
    return await injectGradingComponent(component, graded_component!, editable, showMarkList);
}

/**
 * Opens the component in the cookie
 * @async
 * @returns {void}
 */
async function openCookieComponent() {
    const cookieComponent = getOpenComponentIdFromCookie();
    if (!componentExists(cookieComponent)) {
        return;
    }
    await toggleComponent(cookieComponent, false);
    scrollToComponent(cookieComponent);
}

/**
 * Closes all open components and the overall comment
 * @param {boolean} save_changes
 * @param {boolean} edit_mode editing from ta grading page or instructor edit gradeable page
 * @async
 * @return {void}
 */
export async function closeAllComponents(save_changes: boolean | undefined, edit_mode = false) {
    // Close all open components.  There shouldn't be more than one,
    //  but just in case there is...
    await Promise.all(getOpenComponentIds().map(async (id) => {
        if (isComponentOpen(id)) {
            await closeComponent(id, save_changes, edit_mode);
        }
    }));
}
window.closeAllComponents = closeAllComponents;

/**
 * Toggles the open/close state of a component
 * @param {int} component_id the component's id
 * @param {boolean} saveChanges
 * @param {edit_mode} editing from ta grading page or instructor edit gradeable page
 * @async
 * @return {void}
 */
async function toggleComponent(component_id: number, saveChanges: boolean, edit_mode = false) {
    // Component is open, so close it
    if (isComponentOpen(component_id)) {
        await closeComponent(component_id, saveChanges, edit_mode);
    }
    else {
        await closeAllComponents(saveChanges, edit_mode);
        await openComponent(component_id);
    }

    // Save the open component in the cookie
    updateCookieComponent();
}

window.open_overall_comment_tab = function (user: string) {
    const textarea = $(`#overall-comment-${user}`);
    const comment_root = textarea.closest('.general-comment-entry');

    $('#overall-comments').children().hide();
    $('#overall-comment-tabs').children().removeClass('active-btn');
    comment_root.show();
    $(`#overall-comment-tab-${user}`).addClass('active-btn');

    // if the tab is for the main user of the page
    if (!textarea.hasClass('markdown-preview')) {
        if ($(`#overall-comment-markdown-preview-${user}`).is(':hidden')) {
            textarea.show();
        }
    }
    else {
        textarea.show();
    }

    const attachmentsListUser = $(`#attachments-list-${user}`);
    if (attachmentsListUser.length !== 0) {
        const attachmentsList = $('#attachments-list');
        $(`#attachments-list-${attachmentsList.attr('data-active-user')}`).css('display', 'none');

        let isUser = false;
        if (attachmentsList.attr('data-user') === user) {
            $('#attachment-upload-form').css('display', '');
            $('#overall-comments-attachments').css('display', '');
            isUser = true;
        }
        else {
            $('#attachment-upload-form').css('display', 'none');
        }
        if (attachmentsListUser.children().length === 0) {
            attachmentsListUser.css('display', 'none');
            $('#attachments-header').css('display', 'none');
            if (!isUser) {
                $('#overall-comments-attachments').css('display', 'none');
            }
        }
        else {
            attachmentsListUser.css('display', '');
            $('#attachments-header').css('display', '');
            $('#overall-comments-attachments').css('display', '');
        }

        attachmentsList.attr('data-active-user', user);
    }
};

/**
 * Adds a new mark to the DOM and refreshes the display
 * @param {int} component_id
 * @async
 * @return {void}
 */
async function addNewMark(component_id: number) {
    const component = getComponentFromDOM(component_id);
    component.marks.push({
        id: getNewMarkId(),
        title: '',
        points: 0.0,
        publish: false,
        order: component.marks.length,
    });
    if (!isInstructorEditEnabled()) {
        const graded_component = getGradedComponentFromDOM(component_id);
        await injectGradingComponent(component, graded_component, true, true);
    }
    else {
        await injectInstructorEditComponent(component, true);
    }
}

/**
 * Toggles the state of a mark in grade mode
 * @return {Promise}
 */
export function toggleCommonMark(component_id: number, mark_id: number) {
    return isMarkChecked(mark_id) ? unCheckMark(component_id, mark_id) : checkMark(component_id, mark_id);
}

/**
 * Call to update the custom mark state when any of the custom mark fields change
 * @param {int} component_id
 * @return {Promise}
 */
function updateCustomMark(component_id: number) {
    if (hasCustomMark(component_id)) {
        // Check the mark if it isn't already
        checkDOMCustomMark(component_id);

        // Uncheck the first mark just in case it's checked
        return unCheckFirstMark(component_id);
    }
    else {
        // Automatically uncheck the custom mark if it's no longer relevant
        unCheckDOMCustomMark(component_id);

        // Note: this is in the else block since `unCheckFirstMark` calls this function
        return refreshGradedComponent(component_id, true);
    }
}

/**
 * Call to toggle the custom mark 'checked' state without removing its data
 * @param {int} component_id
 * @return {Promise}
 */
function toggleCustomMark(component_id: number) {
    if (isCustomMarkChecked(component_id)) {
        // Uncheck the first mark just in case it's checked
        return unCheckFirstMark(component_id);
    }
    else {
        // Note: this is in the else block since `unCheckFirstMark` calls this function
        return refreshGradedComponent(component_id, true);
    }
}
/**
 * Opens a component for instructor edit mode
 * NOTE: don't call this function on its own.  Call 'openComponent' Instead
 * @param {int} component_id
 * @async
 * @return {void}
 */
async function openComponentInstructorEdit(component_id: number) {
    const gradeable_id = getGradeableId();
    const component = await ajaxGetComponentRubric(gradeable_id, component_id);
    // Set the global mark list data for this component for conflict resolution
    OLD_MARK_LIST[component_id] = component.marks;
    await injectInstructorEditComponent(component, true, true);
}

/**
 * Opens a component for grading mode (including normal edit mode)
 * NOTE: don't call this function on its own.  Call 'openComponent' Instead
 * @param {int} component_id
 * @async
 * @return {void}
 */
async function openComponentGrading(component_id: number) {
    try {
        const response: { status: string; message: string; data: { active_graders: typeof ACTIVE_GRADERS_LIST; active_graders_timestamps: typeof ACTIVE_GRADERS_LIST } } = await $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                anon_id: getAnonId(),
            },
            url: buildCourseUrl(['gradeable', getGradeableId(), 'grading', 'graded_gradeable', 'open_component']),
        }) as typeof response;
        if (response.status !== 'success') {
            console.error(`Something went wrong fetching the gradeable rubric: ${response.message}`);
            return;
        }
        for (const component of Object.keys(ACTIVE_GRADERS_LIST)) {
            ACTIVE_GRADERS_LIST[component] = response.data.active_graders[component]?.map((_: string, index: number) => {
                const grader = response.data.active_graders[component][index];
                const graderAge = window.luxon.DateTime.fromISO(response.data.active_graders_timestamps[component][index]).toRelative();
                return `${grader} (${graderAge})`;
            }) ?? [];
        }
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }
    OLD_GRADED_COMPONENT_LIST[component_id] = GRADED_COMPONENTS_LIST[component_id]!;
    OLD_MARK_LIST[component_id] = COMPONENT_RUBRIC_LIST[component_id].marks;

    await injectGradingComponent(COMPONENT_RUBRIC_LIST[component_id], GRADED_COMPONENTS_LIST[component_id]!, isEditModeEnabled(), true);
    const page = getComponentPageNumber(component_id);
    if (page) {
        scrollToPage(page);
    }
}

/**
 * Scrolls the submission panel to the page number specified by the component
 * Will open the associated image or PDF depending on if the last opened file was a bulk upload image.
 * TODO: This is currently very clunky, and only works with test files (aka upload.pdf).
 * @param {int} page_num
 * @return {void}
 */
function scrollToPage(page_num: number) {
    const files = $('.openable-element-submissions');
    const activeView = $('#file-view').is(':visible');
    let lastLoadedFile = activeView ? $('#grading_file_name').text().trim() : localStorage.getItem('ta-grading-files-full-view-last-opened') ?? 'upload.pdf';
    if (lastLoadedFile.charAt(0) === '.') {
        lastLoadedFile = lastLoadedFile.substring(1);
    }
    if (lastLoadedFile.startsWith('upload_page_')) {
        const lastLoadedFilePageNum = parseInt(lastLoadedFile.split('_')[2].split('.')[0]);
        if (activeView && page_num === lastLoadedFilePageNum) {
            return;
        }
        let maxPage = -1;
        let maxPageName = '';
        let maxPageLoc = '';
        for (let i = 0; i < files.length; i++) {
            const filename = files[i].innerText.trim();
            const filenameNoPeriod = filename.charAt(0) === '.' ? filename.substring(1) : filename;
            if (filenameNoPeriod.startsWith('upload_page_')) {
                const currPageNum = parseInt(filename.split('_')[2].split('.')[0]);
                if (page_num === currPageNum) {
                    viewFileFullPanel(filename, files[i].getAttribute('file-url')!);
                    return;
                }
                else if (currPageNum > maxPage) {
                    maxPage = currPageNum;
                    maxPageName = filename;
                    maxPageLoc = files[i].getAttribute('file-url')!;
                }
            }
        }
        if (maxPage !== -1) {
            viewFileFullPanel(maxPageName, maxPageLoc);
            return;
        }
    }
    for (let i = 0; i < files.length; i++) {
        if (files[i].innerText.trim() === 'upload.pdf') {
            if (activeView) {
                page_num = Math.min($('#viewer > .page').length, page_num);
                const page = $(`#pageContainer${page_num}`);
                if (page.length) {
                    $('#submission_browser').scrollTop(Math.max(page[0].offsetTop - $('#file-view > .sticky-file-info').first().height()!, 0));
                }
            }
            else {
                viewFileFullPanel('upload.pdf', files[i].getAttribute('file-url')!, page_num - 1);
            }
        }
    }
}

/**
 * Opens the requested component
 * Note: This does not close the currently open component
 * @param {int} component_id
 * @async
 * @return {void}
 */
async function openComponent(component_id: number) {
    setComponentInProgress(component_id);
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    if (isInstructorEditEnabled()) {
        await openComponentInstructorEdit(component_id);
    }
    else {
        await openComponentGrading(component_id);
    }
    resizeNoScrollTextareas();
}

/**
 * Scroll such that a given component is visible
 * @param component_id
 */
export function scrollToComponent(component_id: number) {
    const component = getComponentJQuery(component_id);
    component[0].scrollIntoView();
}

/**
 * Closes a component for instructor edit mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {int} component_id
 * @param {boolean} saveChanges If the changes to the component should be saved or discarded
 * @async
 * @return {void}
 */
async function closeComponentInstructorEdit(component_id: number, saveChanges: boolean) {
    const component = getComponentFromDOM(component_id);
    const countUp = getCountDirection(component_id) !== COUNT_DIRECTION_DOWN;
    if (saveChanges) {
        const input = $(`#mark-${component.marks[0].id.toString()}`).find(':input')[1] as HTMLOptionElement;
        if (component.max_value === 0 && component.upper_clamp === 0 && component.lower_clamp < 0) {
            const mark_title = 'No Penalty Points';
            component.marks[0].title = mark_title;
            input.value = 'No Penalty Points';
        }
        else if (component.max_value === 0 && component.upper_clamp > 0 && component.lower_clamp === 0) {
            const mark_title = 'No Extra Credit Awarded';
            component.marks[0].title = mark_title;
            input.value = 'No Extra Credit Awarded';
        }
        else if (countUp) {
            const mark_title = 'No Credit';
            component.marks[0].title = mark_title;
            input.value = 'No Credit';
        }
        await saveMarkList(component_id);
        // Save the component title and comments
        await ajaxSaveComponent(getGradeableId(), component_id, component.title, component.ta_comment,
            component.student_comment, component.page, component.lower_clamp,
            component.default, component.max_value, component.upper_clamp, component.is_itempool_linked, component.itempool_option);
    }
    const component_rubric = await ajaxGetComponentRubric(getGradeableId(), component_id);
    await injectInstructorEditComponent(component_rubric, false);
}

/**
 * Closes a component for grading mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {int} component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @async
 * @return {void}
 */
async function closeComponentGrading(component_id: number, saveChanges: boolean) {
    try {
        const response: { status: string; message: string; data: { active_graders: typeof ACTIVE_GRADERS_LIST; active_graders_timestamps: typeof ACTIVE_GRADERS_LIST } } = await $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            data: {
                csrf_token: window.csrfToken,
                component_id: component_id,
                anon_id: getAnonId(),
            },
            url: buildCourseUrl(['gradeable', getGradeableId(), 'grading', 'graded_gradeable', 'close_component']),
        }) as typeof response;
        if (response.status !== 'success') {
            console.error(`Something went wrong fetching the gradeable rubric: ${response.message}`);
            return;
        }
        for (const component of Object.keys(ACTIVE_GRADERS_LIST)) {
            ACTIVE_GRADERS_LIST[component] = response.data.active_graders[component]?.map((_: string, index: number) => {
                const grader = response.data.active_graders[component][index];
                const graderAge = window.luxon.DateTime.fromISO(response.data.active_graders_timestamps[component][index]).toRelative();
                return `${grader} (${graderAge})`;
            }) ?? [];
        }
    }
    catch (err) {
        displayAjaxError(err);
        throw err;
    }

    if (saveChanges) {
        GRADED_COMPONENTS_LIST[component_id] = getGradedComponentFromDOM(component_id);
        COMPONENT_RUBRIC_LIST[component_id] = getComponentFromDOM(component_id);
        await saveComponent(component_id);
    }
    // Finally, render the graded component in non-edit mode with the mark list hidden
    void injectGradingComponent(COMPONENT_RUBRIC_LIST[component_id], GRADED_COMPONENTS_LIST[component_id]!, false, false);
}

/**
 * Closes the requested component and saves any changes if requested
 * @param {int} component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @param {boolean} edit_mode editing from ta grading page or instructor edit gradeable page
 * @async
 * @return {void}
 */
export async function closeComponent(component_id: number, saveChanges = true, edit_mode = false) {
    setComponentInProgress(component_id);
    const gradeable_id = getGradeableId();
    const anon_id = getAnonId();
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    if (isInstructorEditEnabled()) {
        await closeComponentInstructorEdit(component_id, saveChanges);
        setComponentInProgress(component_id, false);
        if (!edit_mode) {
            await updateTotals(gradeable_id, anon_id);
        }
    }
    else {
        await closeComponentGrading(component_id, saveChanges);
        setComponentInProgress(component_id, false);
        if (!edit_mode) {
            if (!GRADED_GRADEABLE!.peer_gradeable) {
                await refreshTotalScoreBox();
            }
            else {
                await updateTotals(gradeable_id, anon_id);
            }
        }
    }
}

/**
 * Scroll such that the overall comment is visible
 */
export function scrollToOverallComment() {
    const comment = getOverallCommentJQuery();
    comment[0].scrollIntoView();
}

/**
 * Checks the requested mark and refreshes the component
 * @param {int} component_id
 * @param {int} mark_id
 * @async
 * @return {void}
 */
async function checkMark(component_id: number, mark_id: number) {
    // Don't let them check a disabled mark
    if (isMarkDisabled(mark_id)) {
        return;
    }

    // First fetch the necessary information from the DOM
    const gradedComponent = getGradedComponentFromDOM(component_id);

    // Uncheck the first mark if it's checked
    const firstMarkId = getComponentFirstMarkId(component_id);
    if (isMarkChecked(firstMarkId)) {
        // If first mark is checked, it will be the first element in the array
        gradedComponent.mark_ids.splice(0, 1);
    }

    // Then add the mark id to the array
    gradedComponent.mark_ids.push(mark_id);

    // Finally, re-render the component
    await injectGradingComponent(getComponentFromDOM(component_id), gradedComponent, false, true);
}

/**
 * Un-checks the requested mark and refreshes the component
 * @param {int} component_id
 * @param {int} mark_id
 * @return {Promise}
 */
function unCheckMark(component_id: number, mark_id: number) {
    // First fetch the necessary information from the DOM
    const gradedComponent = getGradedComponentFromDOM(component_id);

    // Then remove the mark id from the array
    for (let i = 0; i < gradedComponent.mark_ids.length; ++i) {
        if (gradedComponent.mark_ids[i] === mark_id) {
            gradedComponent.mark_ids.splice(i, 1);
            break;
        }
    }

    // Finally, re-render the component
    return injectGradingComponent(getComponentFromDOM(component_id), gradedComponent, false, true);
}

/**
 * Un-checks the full credit / no credit mark of a component
 * @param {int} component_id
 * @return {Promise}
 */
function unCheckFirstMark(component_id: number) {
    return unCheckMark(component_id, getComponentFirstMarkId(component_id));
}

/**
 * Saves the mark list to the server for a component and handles any conflicts.
 * Properties that are saved are: mark point values, mark titles, and mark order
 * @param {int} component_id
 * @async
 * @return {void}
 */
async function saveMarkList(component_id: number) {
    const gradeable_id = getGradeableId();
    const component = await ajaxGetComponentRubric(gradeable_id, component_id);
    const domMarkList = getMarkListFromDOM(component_id);
    const serverMarkList = component.marks;
    const oldServerMarkList = OLD_MARK_LIST[component_id];

    // associative array of associative arrays of marks with conflicts {<mark_id>: {domMark, serverMark, oldServerMark}, ...}
    const conflictMarks: MarkConflicts = {};

    // For each DOM mark, try to save it
    await Promise.all(domMarkList.map(async (domMark) => {
        const serverMark = getMarkFromMarkArray(serverMarkList, domMark.id);
        const oldServerMark = getMarkFromMarkArray(oldServerMarkList, domMark.id);
        const success = await tryResolveMarkSave(gradeable_id, component_id, domMark, serverMark, oldServerMark);
        // success of false counts as conflict
        if (success === false) {
            conflictMarks[domMark.id] = {
                domMark: domMark,
                serverMark: serverMark,
                oldServerMark: oldServerMark,
                localDeleted: isMarkDeleted(domMark.id),
            };
        }
    }));
    // If conflicts, open the popup
    if (Object.keys(conflictMarks).length !== 0) {
        await openMarkConflictPopup(component_id, Object.values(conflictMarks));
    }

    const markOrder: Record<number, number> = {};
    domMarkList.forEach((mark) => {
        markOrder[mark.id] = mark.order!;
    });
    // Finally, save the order
    await ajaxSaveMarkOrder(gradeable_id, component_id, markOrder);
}

/**
 * Used to check if two marks are equal
 * @param {Object} mark0
 * @param {Object} mark1
 * @return {boolean}
 */
function marksEqual(mark0: Mark, mark1: Mark) {
    return mark0.points === mark1.points && mark0.title === mark1.title
        && mark0.publish === mark1.publish;
}

/**
 * Determines what to do when trying to save a mark provided the mark
 *  before edits, the DOM mark, and the server's up-to-date mark
 *  @async
 *  @throws {Error} Throws when adding or deleting mark fails
 *  @return {boolean} Resolves true on success, false on conflict
 */
async function tryResolveMarkSave(gradeable_id: string, component_id: number, domMark: Mark, serverMark: Mark | null, oldServerMark: Mark | null) {
    const markDeleted = isMarkDeleted(domMark.id);
    if (oldServerMark !== null) {
        if (serverMark !== null) {
            // Mark edited under normal conditions
            if ((marksEqual(domMark, serverMark) || marksEqual(domMark, oldServerMark)) && !markDeleted) {
                // If the domMark is not unique, then we don't need to do anything
                return true;
            }
            else if (!marksEqual(serverMark, oldServerMark)) {
                // The domMark is unique, and the serverMark is also unique,
                // which means all 3 versions are different, which is a conflict state
                return false;
            }
            else if (markDeleted) {
                // domMark was deleted and serverMark hasn't changed from oldServerMark,
                //  so try to delete the mark
                try {
                    await ajaxDeleteMark(gradeable_id, component_id, domMark.id);
                }
                catch (err) {
                    (err as Error).message = `Could not delete mark: ${(err as Error).message}`;
                    throw err;
                }
                return true;
            }
            else {
                // The domMark is unique and the serverMark is the same as the oldServerMark
                //  so we should save the domMark to the server
                await ajaxSaveMark(gradeable_id, component_id, domMark.id, domMark.title!, domMark.points, domMark.publish);
                return true;
            }
        }
        else {
            // This means it was deleted from the server.
            if (!marksEqual(domMark, oldServerMark) && !markDeleted) {
                // And the mark changed and wasn't deleted, which is a conflict state
                return false;
            }
            else {
                // And the mark didn't change or it was deleted, so don't do anything
                return domMark.id;
            }
        }
    }
    else {
        // This means it didn't exist when we started editing, so serverMark must also be null
        if (markDeleted) {
            // The mark was marked for deletion, but never existed... so do nothing
            return true;
        }
        else {
            // The mark never existed and isn't deleted, so its new
            try {
                const data = await ajaxAddNewMark(gradeable_id, component_id, domMark.title!, domMark.points, domMark.publish);
                // Success, then resolve true
                domMark.id = data.mark_id;
                return true;
            }
            catch (err) {
                // This means the user's mark was invalid
                (err as Error).message = `Failed to add mark: ${(err as Error).message}`;
                throw err;
            }
        }
    }
}

/**
 * Checks if two graded components are equal
 * @param {Object} gcDOM Must not be undefined
 * @param {Object} gcOLD May be undefined
 * @returns {boolean}
 */
function gradedComponentsEqual(gcDOM: ComponentGradeInfo, gcOLD: ComponentGradeInfo) {
    // If the OLD component is undefined, they are only equal if no marks have been assigned
    if (gcOLD === undefined) {
        return gcDOM.mark_ids.length === 0 && (!gcDOM.custom_mark_selected || (gcDOM.score === 0.0 && gcDOM.comment === ''));
    }

    // If its not the same version, then we want to save
    if (gcDOM.graded_version !== gcOLD.graded_version) {
        return false;
    }

    // Simple check, if the mark list isn't the same length
    if (gcDOM.mark_ids.length !== gcOLD.mark_ids.length) {
        return false;
    }

    // Check that they have the same marks assigned
    for (let i = 0; i < gcDOM.mark_ids.length; i++) {
        let found = false;
        for (let j = 0; j < gcOLD.mark_ids.length; j++) {
            if (gcOLD.mark_ids[j] === gcDOM.mark_ids[i]) {
                found = true;
            }
        }
        if (!found) {
            return false;
        }
    }

    // Since the custom mark can be unchecked with text / point value, treat unchecked as blank score / point values
    if (gcDOM.custom_mark_selected) {
        return gcDOM.score === gcOLD.score && gcDOM.comment === gcOLD.comment;
    }
    else {
        return gcOLD.score === 0.0 && gcOLD.comment === '';
    }
}

async function saveComponent(component_id: number) {
    // We are saving changes...
    if (isEditModeEnabled()) {
        // We're in edit mode, so save the component and fetch the up-to-date grade / rubric data
        await saveMarkList(component_id);
        const component = await ajaxGetComponentRubric(getGradeableId(), component_id);
        COMPONENT_RUBRIC_LIST[component_id] = component;
    }
    else {
        // The grader unchecked the custom mark, but didn't delete the text.  This shouldn't happen too often,
        //  so prompt the grader if this is what they really want since it will delete the text / score.
        const gradedComponent = getGradedComponentFromDOM(component_id);
        // only show error if custom marks are allowed
        if (gradedComponent.custom_mark_enabled && gradedComponent.comment !== '' && !gradedComponent.custom_mark_selected && getAllowCustomMarks()) {
            if (!confirm('Are you sure you want to delete the custom mark?')) {
                throw new Error();
            }
        }
        // We're in grade mode, so save the graded component
        // The grader didn't change the grade at all, so don't save (don't put our name on a grade we didn't contribute to)
        if (!gradedComponentsEqual(gradedComponent, OLD_GRADED_COMPONENT_LIST[component_id])) {
            await saveGradedComponent(component_id);
            if (!isSilentEditModeEnabled()) {
                GRADED_COMPONENTS_LIST[component_id]!.grader_id = getGraderId();
            }
            GRADED_COMPONENTS_LIST[component_id]!.verifier_id = '';
        }
        else if (gradedComponent.graded_version !== getDisplayVersion()) {
            await ajaxChangeGradedVersion(getGradeableId(), getAnonId(), getDisplayVersion(), [component_id]);
            await reloadGradingComponent(component_id, false, false);
        }
    }
}

/**
 * Saves the component grade information to the server
 * Note: if the mark was deleted remotely, but the submitter was assigned it locally, the mark
 *  will be resurrected with a new id
 * @param {int} component_id
 * @async
 * @return {void}
 */
async function saveGradedComponent(component_id: number) {
    const gradeable_id = getGradeableId();
    const gradedComponent = getGradedComponentFromDOM(component_id);
    gradedComponent.graded_version = getDisplayVersion();

    const component = await ajaxGetComponentRubric(getGradeableId(), component_id);
    const missingMarks: Mark[] = [];
    const domComponent = getComponentFromDOM(component_id);
    // Check each mark the submitter was assigned
    gradedComponent.mark_ids.forEach((mark_id) => {
        // Mark exists remotely, so no action required
        if (getMarkFromMarkArray(component.marks, mark_id) !== null) {
            return;
        }
        missingMarks.push(getMarkFromMarkArray(domComponent.marks, mark_id)!);
    });
    // For each mark missing from the server, add it
    await Promise.all(missingMarks.map(async (mark) => {
        const data = await ajaxAddNewMark(gradeable_id, component_id, mark.title!, mark.points, mark.publish);
        // Make sure to add it to the grade.  We don't bother removing the deleted mark ids
        //  however, because the server filters out non-existent mark ids
        gradedComponent.mark_ids.push(data.mark_id);
    }));
    await ajaxSaveGradedComponent(
        gradeable_id, component_id, getAnonId(),
        gradedComponent.graded_version,
        gradedComponent.custom_mark_selected ? gradedComponent.score : 0.0,
        gradedComponent.custom_mark_selected ? gradedComponent.comment : '',
        isSilentEditModeEnabled(),
        gradedComponent.mark_ids);
}

/**
 * Re-renders the graded component header with the data in the DOM
 *  and preserves the edit/grade mode display
 * @param {int} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
function refreshGradedComponentHeader(component_id: number, showMarkList: boolean) {
    return injectGradingComponentHeader(
        getComponentFromDOM(component_id),
        getGradedComponentFromDOM(component_id), showMarkList);
}

/**
 * Resolves all version conflicts for the gradeable by re-submitting the current marks for every component
 */
window.updateAllComponentVersions = async function () {
    if (confirm('Are you sure you want to update the version for all components without separately inspecting each component?')) {
        await ajaxChangeGradedVersion(getGradeableId(), getAnonId(), getDisplayVersion(), getAllComponentsFromDOM().map((x) => x.id));
        await Promise.all(getAllComponentsFromDOM().map((x) => reloadGradingComponent(x.id, false, false)));
        $('#change-graded-version').hide();
    }
};

/**
 * Re-renders the graded component with the data in the DOM
 *  and preserves the edit/grade mode display
 * @param {int} component_id
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function refreshGradedComponent(component_id: number, showMarkList: boolean) {
    return injectGradingComponent(
        getComponentFromDOM(component_id),
        getGradedComponentFromDOM(component_id),
        isEditModeEnabled(), showMarkList);
}

/**
 * Re-renders the component header with the data in the DOM
 * @param {int} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
function refreshInstructorEditComponentHeader(component_id: number, showMarkList: boolean) {
    return injectInstructorEditComponentHeader(getComponentFromDOM(component_id), showMarkList);
}

/**
 * Re-renders the component's header block with the data in the DOM
 * @param {int} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
function refreshComponentHeader(component_id: number, showMarkList: boolean) {
    return isInstructorEditEnabled() ? refreshInstructorEditComponentHeader(component_id, showMarkList) : refreshGradedComponentHeader(component_id, showMarkList);
}

/**
 * Refreshes the 'total scores' box at the bottom of the gradeable
 * @return {Promise}
 */
function refreshTotalScoreBox() {
    return injectTotalScoreBox(getScoresFromDOM());
}

/**
 * Refreshes the 'rubric total' box at the top of the rubric editor
 * @returns {Promise}
 */
function refreshRubricTotalBox() {
    return injectRubricTotalBox(getRubricTotalFromDOM());
}

/**
 * Renders the provided component object for instructor edit mode
 * @param {Object} component
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @param {boolean} loadItempoolOptions whether to load the itempool options or not
 * @async
 * @return {void}
 */
async function injectInstructorEditComponent(component: Component, showMarkList: boolean, loadItempoolOptions = false) {
    const elements = await renderEditComponent(component, getPointPrecision(), showMarkList) as string;
    setComponentContents(component.id, elements);
    await refreshRubricTotalBox();
    if (isItempoolAvailable() && loadItempoolOptions) {
        addItempoolOptions(component.id);
    }
}

/**
 * Renders the provided component object for instructor edit mode header
 * @param {Object} component
 * @param {boolean} showMarkList Whether to style the header like the mark list is open
 * @async
 * @return {void}
 */
async function injectInstructorEditComponentHeader(component: Component, showMarkList: boolean) {
    const elements = await renderEditComponentHeader(component, showMarkList) as string;
    setComponentHeaderContents(component.id, elements);
    await refreshRubricTotalBox();
}

/**
 * Renders the provided component/graded_component object for grading/editing
 * @param {Object} component
 * @param {Object} graded_component
 * @param {boolean} editable Whether the component should appear in edit or grade mode
 * @param {boolean} showMarkList Whether to show the mark list or not
 * @async
 * @return {void}
 */
async function injectGradingComponent(component: Component, graded_component: ComponentGradeInfo, editable: boolean, showMarkList: boolean) {
    const student_grader = $('#student-grader').attr('is-student-grader');
    const elements = await renderGradingComponent(getGraderId(), component, graded_component, ACTIVE_GRADERS_LIST[component.id], isGradingDisabled(), !!canVerifyGraders(), getPointPrecision(), editable, showMarkList, getComponentVersionConflict(graded_component), !!student_grader, TA_GRADING_PEER, getAllowCustomMarks());
    setComponentContents(component.id, elements);
}

/**
 * Renders the provided component/graded_component header
 * @param {Object} component
 * @param {Object} graded_component
 * @param {boolean} showMarkList Whether to style the header like the mark list is open
 * @async
 * @return {void}
 */
async function injectGradingComponentHeader(component: Component, graded_component: ComponentGradeInfo, showMarkList: boolean) {
    const elements = await renderGradingComponentHeader(getGraderId(), component, graded_component, isGradingDisabled(), !!canVerifyGraders(), showMarkList, getComponentVersionConflict(graded_component));
    setComponentHeaderContents(component.id, elements);
    await refreshTotalScoreBox();
}

/**
 * Renders the total scores box
 * @param {Object} scores
 * @async
 * @return {void}
 */
async function injectTotalScoreBox(scores: object) {
    try {
        const elements = await renderTotalScoreBox(scores);
        setTotalScoreBoxContents(elements);
    }
    catch (error) {
        console.error('Failed to render:', error);
    }
}

/**
 * Renders the rubric total box (instructor edit mode)
 * @param {Object} scores
 * @async
 * @returns {string}
 */
async function injectRubricTotalBox(scores: object) {
    const elements = await renderRubricTotalBox(scores);
    setRubricTotalBoxContents(elements);
}

function addItempoolOptions(componentId: number) {
    // create option elements for the itempool options
    const itempools = getItempoolOptions(true) as Record<string, string[]>;
    const select_ele = $(`#component-itempool-select-${componentId}`);
    const selected_value = select_ele.attr('data-selected') ? select_ele.attr('data-selected') : 'null';
    const itempool_options = ['<option value="null">NONE</option>'];

    for (const key in itempools) {
        itempool_options.push(`<option value='${key}'>${key} (${itempools[key].join(', ')})</option>`);
    }
    select_ele.html(itempool_options.join(''));
    select_ele.val(selected_value!).change();
}
