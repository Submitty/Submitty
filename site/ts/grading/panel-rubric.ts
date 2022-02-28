import { getCsrfToken } from '../utils/server';
import { Component } from './types/Component';
import { GradedComponent } from './types/GradedComponent';
import { Mark } from './types/Mark';
import { RubricTotal } from './types/RubricTotal';
import { Score } from './types/Score';

/**
 *  Notes: Some variables have 'domElement' in their name, but they may be jquery objects
 */

/**
 * Global variables.  Add these very sparingly
 */

/**
 * An associative object of <component-id> : <mark[]>
 * Each 'mark' has at least properties 'id', 'points', 'title', which is sufficient
 *  to determine conflict resolutions.  These are updated when a component is opened.
 * @type {Object}
 */
const OLD_MARK_LIST: {[key: number]: Mark[]} = {};

/**
 * An associative object of <component-id> : <graded_component>
 * Each 'graded_component' has at least properties 'score', 'mark_ids', 'comment'
 * @type {{Object}}
 */
const OLD_GRADED_COMPONENT_LIST: {[key: number]: GradedComponent} = {};

/**
 * A number ot represent the id of no component
 * @type {number}
 */
const NO_COMPONENT_ID = -1;

/**
 * The id of the custom mark for a component
 * @type {number}
 */
const CUSTOM_MARK_ID = 0;

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
const COUNT_DIRECTION_UP = 1;
const COUNT_DIRECTION_DOWN = -1;

/**
 * Pdf Page settings for components
 * @type {number}
 */
const PDF_PAGE_NONE = 0;
const PDF_PAGE_STUDENT = -1;
const PDF_PAGE_INSTRUCTOR = -2;

/**
 * Whether ajax requests will be asynchronous or synchronous.  This
 *  is used instead of passing an 'async' parameter to every function.
 * @type {boolean}
 */
const AJAX_USE_ASYNC = true;

/**
 * Keep All of the ajax functions at the top of this file
 *
 */

/**
 * Called internally when an ajax function irrecoverably fails before rejecting
 * @param err
 */
function displayAjaxError(err: unknown): void {
    console.error("Failed to parse response.  The server isn't playing nice...");
    console.error(err);
    // alert("There was an error communicating with the server. Please refresh the page and try again.");
}

//TODO: Separate all interfaces or some or none?
export interface GradeableRubric {
    id: string,
    precision: number,
    components: Component[];
}

/**
 * ajax call to fetch the gradeable's rubric
 * @param {string} gradeable_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetGradeableRubric(gradeable_id: string): Promise<GradeableRubric> {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'rubric']),
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong fetching the gradeable rubric: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as GradeableRubric);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to save the component
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {string} title
 * @param {string} ta_comment
 * @param {string} student_comment
 * @param {number} page
 * @param {number} lower_clamp
 * @param {number} default_value
 * @param {number} max_value
 * @param {number} upper_clamp
 * @param {boolean} is_itempool_linked
 * @param {string} itempool_option
 * @returns {Promise}
 */
function ajaxSaveComponent(gradeable_id: string, component_id: number, title: string, ta_comment: string, student_comment: string, page: number, lower_clamp: number, default_value: number, max_value: number, upper_clamp: number, is_itempool_linked: boolean, itempool_option: string | undefined): Promise<null> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'save']),
            dataType: 'json',
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
                'title': title,
                'ta_comment': ta_comment,
                'student_comment': student_comment,
                'page_number': page,
                'lower_clamp': lower_clamp,
                'default': default_value,
                'max_value': max_value,
                'upper_clamp': upper_clamp,
                'is_itempool_linked': is_itempool_linked,
                'itempool_option': itempool_option === 'null' ? undefined : itempool_option,
                'peer': false,
            },
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong saving the component: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as null);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to fetch the component's rubric
 * @param {string} gradeable_id
 * @param {number} component_id
 * @returns {Promise}
 */
function ajaxGetComponentRubric(gradeable_id: string, component_id: number): Promise<Component> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'components'])}?component_id=${component_id}`,
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong fetching the component rubric: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as Component);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

export interface GradedGradeable {

}

/**
 * ajax call to get the entire graded gradeable for a user
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {boolean} all_peers
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetGradedGradeable(gradeable_id: string, anon_id: string, all_peers: boolean): Promise<> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'graded_gradeable'])}?anon_id=${anon_id}&all_peers=${all_peers.toString()}`,
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong fetching the gradeable grade: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to fetch an updated Graded Component
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetGradedComponent(gradeable_id: string, component_id: number, anon_id: string): Promise<GradedComponent> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'graded_gradeable', 'graded_component'])}?anon_id=${anon_id}&component_id=${component_id}`,
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong fetching the component grade: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    // null is not the same as undefined, so we need to make that conversion before resolving
                    if (response.data === null) {
                        response.data = undefined;
                    }
                    resolve(response.data as GradedComponent);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to save the grading information for a component and submitter
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {string} anon_id
 * @param {number} graded_version
 * @param {number} custom_points
 * @param {string} custom_message
 * @param {boolean} silent_edit True to edit marks assigned without changing the grader
 * @param {int[]} mark_ids
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveGradedComponent(gradeable_id: string, component_id: number, anon_id: string, graded_version: number, custom_points: number, custom_message: string, silent_edit: boolean, mark_ids: number[]): Promise<null> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'grading', 'graded_gradeable', 'graded_component']),
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
                'anon_id': anon_id,
                'graded_version': graded_version,
                'custom_points': custom_points,
                'custom_message': custom_message,
                'silent_edit': silent_edit,
                'mark_ids': mark_ids,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong saving the component grade: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as null);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

export interface OverallComment {
    [user_id: string]: string;
}

/**
 * ajax call to fetch the overall comment for the gradeable for the logged in user
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetOverallComment(gradeable_id: string, anon_id: string): Promise<OverallComment> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'GET',
            async: AJAX_USE_ASYNC,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'grading', 'comments'])}?anon_id=${anon_id}`,
            data: undefined,
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong fetching the gradeable comment: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as OverallComment);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to save the general comment for the graded gradeable
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {string} overall_comment
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveOverallComment(gradeable_id: string, anon_id: string, overall_comment: string): Promise<null> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'grading', 'comments']),
            data: {
                'csrf_token': getCsrfToken(),
                'gradeable_id': gradeable_id,
                'anon_id': anon_id,
                'overall_comment': overall_comment,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong saving the overall comment: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as null);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

export interface AddNewMarkResponse {
    mark_id: number
}

/**
 * ajax call to add a new mark to the component
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {string} title
 * @param {number} points
 * @param {boolean} publish
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxAddNewMark(gradeable_id: string, component_id: number, title: string, points: number, publish: boolean): Promise<AddNewMarkResponse> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'add']),
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
                'title': title,
                'points': points,
                'publish': publish,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong adding a new mark: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as AddNewMarkResponse);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to delete a mark
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {number} mark_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxDeleteMark(gradeable_id: string, component_id: number, mark_id: number): Promise<null> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'delete']),
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
                'mark_id': mark_id,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong deleting the mark: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as null);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to save mark point value / title
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {number} mark_id
 * @param {string} title
 * @param {number} points
 * @param {boolean} publish
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveMark(gradeable_id: string, component_id: number, mark_id: number, title: string, points: number, publish: boolean): Promise<null> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'save']),
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
                'mark_id': mark_id,
                'points': points,
                'title': title,
                'publish': publish,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong saving the mark: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as null);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to get the stats about a mark
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {number} mark_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxGetMarkStats(gradeable_id: string, component_id: number, mark_id: number): Promise<MarkStats> {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'stats']),
            data: {
                'component_id': component_id,
                'mark_id': mark_id,
                'csrf_token': getCsrfToken(),
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong getting mark stats: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to update the order of marks in a component
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {*} order format: { <mark0-id> : <order0>, <mark1-id> : <order1>, ... }
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveMarkOrder(gradeable_id: string, component_id: number, order: {[key: number]: number|undefined}): Promise<null> { //TODO: UPDATE
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'marks', 'save_order']),
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
                'order': JSON.stringify(order),
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong saving the mark order: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data as null);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to update the pages of components in the gradeable
 * @param {string} gradeable_id
 * @param {*} pages format: { <component0-id> : <page0>, <component1-id> : <page1>, ... } OR { page } to set all
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveComponentPages(gradeable_id: string, pages: {[key: number]: number} | {page: number} ) { //TODO: ANY
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'save_pages']),
            data: {
                'csrf_token': getCsrfToken(),
                'pages': JSON.stringify(pages),
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong saving the component pages: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to update the order of components in the gradeable
 * @param {string} gradeable_id
 * @param {*} order format: { <component0-id> : <order0>, <component1-id> : <order1>, ... }
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxSaveComponentOrder(gradeable_id: string, order: {[key: number]: number}) {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'order']),
            data: {
                'csrf_token': getCsrfToken(),
                'order': JSON.stringify(order),
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong saving the component order: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to add a generate component on the server
 * @param {string} gradeable_id
 * @param {string} peer
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxAddComponent(gradeable_id: string, peer: boolean) { //TODO: CHECK COMMENT
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'new']),
            data: {
                'csrf_token': getCsrfToken(),
                'peer': peer,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong adding the component: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to delete a component from the server
 * @param {string} gradeable_id
 * @param {number} component_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxDeleteComponent(gradeable_id: string, component_id: number) {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: AJAX_USE_ASYNC,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'delete']),
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong deleting the component: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to verify the grader of a component
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxVerifyComponent(gradeable_id: string, component_id: number, anon_id: string) {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: true,
            url: buildCourseUrl(['gradeable', gradeable_id, 'components', 'verify']),
            data: {
                'csrf_token': getCsrfToken(),
                'component_id': component_id,
                'anon_id': anon_id,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong verifying the component: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * ajax call to verify the grader of a component
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
function ajaxVerifyAllComponents(gradeable_id: string, anon_id: string) {
    return new Promise((resolve, reject) =>  {
        $.ajax({
            type: 'POST',
            async: true,
            url: `${buildCourseUrl(['gradeable', gradeable_id, 'components', 'verify'])}?verify_all=true`,
            data: {
                'csrf_token': getCsrfToken(),
                'anon_id': anon_id,
            },
            dataType: 'json',
            success: function (response: { status: string; message: string | undefined; data: unknown; }) {
                if (response.status !== 'success') {
                    console.error(`Something went wrong verifying the all components: ${response.message}`);
                    reject(new Error(response.message));
                }
                else {
                    resolve(response.data);
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}

/**
 * Gets if the 'verify' button should show up for a component
 * @param {Object} graded_component
 * @param {string} grader_id
 * @returns {boolean}
 */
function showVerifyComponent(graded_component: GradedComponent | undefined, grader_id: string) {
    return graded_component !== undefined && graded_component.grader_id !== '' && grader_id !== graded_component.grader_id;
}

/**
 * Put all DOM accessing methods here to abstract the DOM from the other function
 *  of the interface
 */

/**
 * Gets the id of the open gradeable
 * @return {string}
 */
function getGradeableId() {
    return $('#gradeable-rubric').attr('data-gradeable_id') as string;
}

/**
 * Gets the anon_id of the submitter being graded
 * @return {string}
 */
function getAnonId() {
    return $('#anon-id').attr('data-anon_id') as string;
}

/**
 * Gets the id of the grader
 * @returns {string}
 */
function getGraderId() {
    return $('#grader-info').attr('data-grader_id') as string;
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
    return $('#grader-info').attr('data-can_verify') != '';
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
 * @return {number}
 */
function getDisplayVersion() {
    return parseInt($('#gradeable-version-container').attr('data-gradeable_version') as string);
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
function isSilentEditModeEnabled() {
    // noinspection JSValidateTypes
    return $('#silent-edit-id').is(':checked');
}

/**
 * Gets a unique mark id for adding new marks
 * @return {number}
 */
function getNewMarkId() {
    return MARK_ID_COUNTER--;
}

/**
 * Sets the DOM elements to render for the entire rubric
 * @param elements
 */
function setRubricDOMElements(elements: string) {
    const gradingBox = $('#grading-box');
    gradingBox.html(elements);

    if (isInstructorEditEnabled()) {
        setupSortableComponents();
    }
}

/**
 * Gets the component id of a DOM element inside a component
 * @param me DOM element
 * @return {number}
 */
function getComponentIdFromDOMElement(me: HTMLElement) {
    if ($(me).hasClass('component')) {
        return parseInt($(me).attr('data-component_id') as string);
    }
    return parseInt($(me).parents('.component').attr('data-component_id') as string);
}

/**
 * Gets the mark id of a DOM element inside a mark
 * @param me DOM element
 * @return {number}
 */
function getMarkIdFromDOMElement(me: HTMLElement) {
    if ($(me).hasClass('mark-container')) {
        return parseInt($(me).attr('data-mark_id') as string);
    }
    return parseInt($(me).parents('.mark-container').attr('data-mark_id') as string);
}

/**
 * Gets the JQuery selector for the component id
 * Note: This is not the component container
 * @param {number} component_id
 * @return {jQuery}
 */
function getComponentJQuery(component_id: number) {
    return $(`#component-${component_id}`);
}

/**
 * Gets the JQuery selector for the mark id
 * @param {number} mark_id
 * @return {jQuery}
 */
function getMarkJQuery(mark_id: number) {
    return $(`#mark-${mark_id}`);
}

/**
 * Gets the JQuery selector for the component's custom mark
 * @param {number} component_id
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
 */
function isItempoolAvailable(): boolean {
    return !!($('#gradeable_rubric.electronic_file').attr('data-itempool-available'));
}

/**
 * Returns the itempool options
 * @return array|string
 */
function getItempoolOptions() {
    try {
        return isItempoolAvailable() ? JSON.parse($('#gradeable_rubric.electronic_file').attr('data-itempool-options') as string) : [];
    }
    catch (e) {
        displayErrorMessage('Something went wrong retrieving itempool options');
        return [];
    }
}

/**
 * Shows the 'in progress' indicator for a component
 * @param {number} component_id
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
 * @param {number} component_id
 */
function setupSortableMarks(component_id: number) {
    const markList = getComponentJQuery(component_id).find('.ta-rubric-table');
    markList.sortable({
        items: 'div:not(.mark-first,.add-new-mark-container)',
    });
    markList.on('keydown', keyPressHandler);
    markList.keydown(keyPressHandler);
    markList.disableSelection();
}

/**
 * Enables reordering on components for instructor edit mode
 */
function setupSortableComponents() {
    const componentList = $('#component-list');
    componentList.sortable({
        update: onComponentOrderChange,
        handle: '.reorder-component-container',
    });
    componentList.on('keydown', keyPressHandler);
    componentList.disableSelection();
}

/**
 * Key press handler for jquery sortable elements
 * @param {KeyboardEvent} e
 */
function keyPressHandler(e: JQuery.KeyDownEvent) {
    // Enable ctrl-a to select all
    if (e.code === 'KeyA' && e.ctrlKey) {
        e.target.select();
    }
}

/**
 * Sets the HTML contents of the specified component container
 * @param {number} component_id
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
 */
function setComponentHeaderContents(component_id: number, contents: string) {
    getComponentJQuery(component_id).find('.header-block').html(contents);
}

/**
 * Sets the HTML contents of the total scores box
 */
function setTotalScoreBoxContents(contents: string) {
    $('#total-score-container').html(contents);
}

/**
 * Sets the HTML contents of the rubric total box (instructor edit mode)
 */
function setRubricTotalBoxContents(contents: string): void {
    $('#rubric-total-container').html(contents);
}

/**
 * Gets the count direction for a component in instructor edit mode
 * @returns {number} COUNT_DIRECTION_UP or COUNT_DIRECTION_DOWN
 */
function getCountDirection(component_id: number): number {
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
 */
function setMarkTitle(mark_id: number, title: string): void {
    getMarkJQuery(mark_id).find('.mark-title textarea').val(title);
}

/**
 * Loads all components from the DOM
 */
function getAllComponentsFromDOM(): Component[] {
    const components: Component[] = [];
    $('.component').each((index, element) =>  {
        components.push(getComponentFromDOM(getComponentIdFromDOMElement(element)));
    });
    return components;
}

/**
 * Gets the page number assigned to a component
 */
function getComponentPageNumber(component_id: number): number {
    const domElement = getComponentJQuery(component_id);
    if (isInstructorEditEnabled()) {
        return parseInt(domElement.find('input.page-number').val() as string);
    }
    else {
        return parseInt(domElement.attr('data-page') as string);
    }
}

/**
 * Extracts a component object from the DOM
 * @param {number} component_id
 * @return {Object}
 */
function getComponentFromDOM(component_id: number): Component {
    const domElement = getComponentJQuery(component_id);

    if (isInstructorEditEnabled() && isComponentOpen(component_id)) {
        const penaltyPoints = Math.abs(parseFloat(domElement.find('input.penalty-points').val() as string));
        const maxValue = Math.abs(parseFloat(domElement.find('input.max-points').val() as string));
        const extraCreditPoints = Math.abs(parseFloat(domElement.find('input.extra-credit-points').val() as string));
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
        page: parseInt(domElement.attr('data-page') as string),
        lower_clamp: parseFloat(domElement.attr('data-lower_clamp') as string),
        default: parseFloat(domElement.attr('data-default') as string),
        max_value: parseFloat(domElement.attr('data-max_value') as string),
        upper_clamp: parseFloat(domElement.attr('data-upper_clamp') as string),
        marks: getMarkListFromDOM(component_id),
        is_itempool_linked: domElement.find(`#yes-link-item-pool-${component_id}`).is(':checked'),
        itempool_option: domElement.find('select[name="component-itempool"]').val() as string,
        peer: (domElement.attr('data-peer') === 'true'),
    };
}

/**
 * Extracts an array of marks from the DOM
 */
function getMarkListFromDOM(component_id: number): Mark[] {
    const domElement = getComponentJQuery(component_id);
    const markList: Mark[] = [];
    let i = 0;
    domElement.find('.ta-rubric-table .mark-container').each((index, element) =>  {
        const mark = getMarkFromDOM(parseInt($(element).attr('data-mark_id')  as string));

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
 */
function getMarkFromDOM(mark_id: number): Mark | null {
    const domElement = getMarkJQuery(mark_id);
    if (isEditModeEnabled()) {
        return {
            id: parseInt(domElement.attr('data-mark_id') as string),
            points: parseFloat(domElement.find('input[type=number]').val() as string),
            title: domElement.find('textarea').val() as string,
            deleted: domElement.hasClass('mark-deleted'),
            publish: domElement.find('.mark-publish-container input[type=checkbox]').is(':checked'),
        };
    }
    else {
        if (mark_id === 0) {
            return null;
        }
        return {
            id: parseInt(domElement.attr('data-mark_id') as string),
            points: parseFloat(domElement.find('.mark-points').attr('data-points') as string),
            title: domElement.find('.mark-title').attr('data-title') as string,
            publish: domElement.attr('data-publish') === 'true',
        };
    }
}

/**
 * Gets if a component exists for this gradeable
 */
function componentExists(component_id: number): boolean {
    return getComponentJQuery(component_id).length > 0;
}

/**
 * Extracts a graded component object from the DOM
 * @param {number} component_id
 * @return {Object}
 */
function getGradedComponentFromDOM(component_id: number): GradedComponent {
    const domElement = getComponentJQuery(component_id);
    const customMarkContainer = domElement.find('.custom-mark-container');

    // Get all of the marks that are 'selected'
    const mark_ids: number[] = [];
    let customMarkSelected = false;
    domElement.find('span.mark-selected').each((index, element) =>  {
        const mark_id = parseInt($(element).attr('data-mark_id') as string);
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
        score = parseFloat(customMarkDOMElement.attr('data-score') as string);
        comment = customMarkDOMElement.attr('data-comment') as string;
        customMarkSelected = customMarkDOMElement.attr('data-selected') === 'true';
    }
    else {
        score = parseFloat(customMarkContainer.find('input[type=number]').val() as string);
        comment = customMarkContainer.find('textarea').val() as string;
    }

    const dataDOMElement = domElement.find('.graded-component-data');
    let gradedVersion = parseInt(dataDOMElement.attr('data-graded_version') as string);
    //TODO: Double check
    if (isNaN(gradedVersion)) {
        gradedVersion = getDisplayVersion();
    }
    return {
        score: score,
        comment: comment,
        custom_mark_selected: customMarkSelected,
        mark_ids: mark_ids,
        graded_version: gradedVersion,
        grade_time: dataDOMElement.attr('data-grade_time') as string,
        grader_id: dataDOMElement.attr('data-grader_id') as string,
        verifier_id: dataDOMElement.attr('data-verifier_id') as string,
        custom_mark_enabled: CUSTOM_MARK_ID,
    };
}
/**
 * Gets the scores data from the DOM (auto grading earned/possible and ta grading possible)
 */
function getScoresFromDOM(): Score {
    const dataDOMElement = $('#gradeable-scores-id');
    const scores: Score = {
        ta_grading_complete: getTaGradingComplete(),
        ta_grading_earned: getTaGradingEarned(),
        ta_grading_total: getTaGradingTotal(),
        peer_grade_earned: getPeerGradingEarned(),
        peer_total: getPeerGradingTotal(),
        auto_grading_complete: false,
    };

    // Then check if auto grading scorse exist before adding them
    const autoGradingTotal = dataDOMElement.attr('data-auto_grading_total') as string;
    if (autoGradingTotal !== '') {
        scores.auto_grading_earned = parseInt(dataDOMElement.attr('data-auto_grading_earned') as string);
        scores.auto_grading_total = parseInt(autoGradingTotal);
        scores.auto_grading_complete = true;
    }

    return scores;
}

/**
 * Gets the rubric total / extra credit from the DOM
 */
function getRubricTotalFromDOM(): RubricTotal {
    let total = 0;
    let extra_credit = 0;
    getAllComponentsFromDOM().forEach((component: Component) =>  {
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
function getTaGradingEarned(): number | undefined {
    let total = 0.0;
    let anyPoints = false;
    $('.graded-component-data').each((index, element) =>  {
        const pointsEarned = $(element).attr('data-total_score') as string;
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
function getPeerGradingEarned(): number | undefined {
    let total = 0.0;
    let anyPoints = false;
    $('.peer-graded-component-data').each((index, element) =>  {
        const pointsEarned = $(element).attr('data-total_score') as string;
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
 * Gets if all components have a grade assigned
 * @return {boolean} If all components have at least one mark checked
 */
function getTaGradingComplete(): boolean {
    let anyIncomplete = false;
    $('.graded-component-data').each((index, element) =>  {
        const pointsEarned = $(element).attr('data-total_score');
        if (pointsEarned === '') {
            anyIncomplete = true;
        }
    });
    return !anyIncomplete;
}


/**
 * Gets the number of ta grading points that can be earned
 */
function getTaGradingTotal(): number {
    let total = 0.0;
    $('.ta-component').each((index, element) =>  {
        total += parseFloat($(element).attr('data-max_value') as string);
    });
    return total;
}
/**
 * Gets the number of Peer grading points that can be earned
 * @return {number}
 */
function getPeerGradingTotal(): number {
    let total = 0.0;
    $('.peer-component').each((index, element) =>  {
        total += parseFloat($(element).attr('data-max_value') as string);
    });
    return total;
}
/**
 * Gets the number of Peer points that were earned
 * @return {number}
 */
function getPeerGradingScore(): number {
    let total = 0.0;
    $('.peer-score').each((index, element) =>  {
        total += parseFloat($(element).attr('data-max_value') as string);
    });
    return total;
}

/**
 * Gets the overall comment message stored in the DOM
 * @return {string} This will always be blank in instructor edit mode
 */
function getOverallCommentFromDOM(user: string): string {
    return $(`textarea#overall-comment-${user}`).val() as string;
}

/**
 * Gets the ids of all open components
 */
function getOpenComponentIds(itempool_only = false): number[] {
    const component_ids: number[] = [];
    if (itempool_only) {
        $('.ta-rubric-table:visible').each((index, element) =>  {
            const component = $(`#component-${$(element).attr('data-component_id')}`);
            if (component && component.attr('data-itempool_id')) {
                component_ids.push(parseInt($(element).attr('data-component_id') as string));
            }
        });
    }
    else {
        $('.ta-rubric-table:visible').each((index, element) =>  {
            component_ids.push(parseInt($(element).attr('data-component_id') as string));
        });
    }
    return component_ids;
}

/**
 * Gets the component id from its order on the page
 */
function getComponentIdByOrder(order: number): number {
    return parseInt($('.component-container').eq(order).find('.component').attr('data-component_id') as string);
}

/**
 * Gets the orders of the components indexed by component id
 */
function getComponentOrders(): {[key: number]: number} {
    const orders: {[key: number]: number} = {};
    $('.component').each((order, element) =>  {
        const id = getComponentIdFromDOMElement(element);
        orders[id] = order;
    });
    return orders;
}

/**
 * Gets the id of the next component in the list
 */
function getNextComponentId(component_id: number): number {
    //TODO: confirm behavior when data-component_id not set (originally returns string)
    return parseInt(getComponentJQuery(component_id).parent('.component-container').next().children('.component').attr('data-component_id') as string);
}

/**
 * Gets the id of the previous component in the list
 */
function getPrevComponentId(component_id: number): number {
    //TODO: confirm behavior when data-component_id not set (originally returns string)
    return parseInt(getComponentJQuery(component_id).parent('.component-container').prev().children('.component').attr('data-component_id') as string);
}

/**
 * Gets the first open component on the page
 */
function getFirstOpenComponentId(itempool_only = false): number {
    const component_ids = getOpenComponentIds(itempool_only);
    if (component_ids.length === 0) {
        return NO_COMPONENT_ID;
    }
    return component_ids[0];
}

/**
 * Gets the number of components on the page
 */
function getComponentCount(): number {
    return $('.component-container').length;
}

/**
 * Gets the mark id for a component and order
 * @returns {number} Mark id or 0 if out of bounds
 */
function getMarkIdFromOrder(component_id: number, mark_order: number): number {
    const jquery = getComponentJQuery(component_id).find('.mark-container');
    if (mark_order < jquery.length) {
        return parseInt(jquery.eq(mark_order).attr('data-mark_id') as string);
    }
    return 0;
}

/**
 * Gets the id of the open component from the cookie
 * @return {number} Returns NO_COMPONENT_ID if no open component exists, otherwise the open component from the cookie.
 */
function getOpenComponentIdFromCookie(): number {
    const component_id = parseInt(document.cookie.replace(/(?:(?:^|.*;\s*)open_component_id\s*=\s*([^;]*).*$)|^.*$/, '$1'));
    if (isNaN(component_id)) {
        return NO_COMPONENT_ID;
    }
    return component_id;
}

/**
 * Updates the open component in the cookie
 */
function updateCookieComponent(): void {
    document.cookie = `open_component_id=${getFirstOpenComponentId()}; path=/;`;
}

/**
 * Gets the id of the no credit / full credit mark of a component
 */
function getComponentFirstMarkId(component_id: number): number {
    return parseInt(getComponentJQuery(component_id).find('.mark-container').first().attr('data-mark_id') as string);
}

/**
 * Gets if a component is open
 */
function isComponentOpen(component_id: number): boolean {
    return !getComponentJQuery(component_id).find('.ta-rubric-table').is(':hidden');
}

/**
 * Gets if a mark is 'checked'
 */
function isMarkChecked(mark_id: number): boolean {
    return getMarkJQuery(mark_id).find('span.mark-selected').length > 0;
}

/**
 * Gets if a mark is disabled (shouldn't be checked
 */
function isMarkDisabled(mark_id: number): boolean {
    return getMarkJQuery(mark_id).hasClass('mark-disabled');
}

/**
 * Gets if a mark was marked for deletion
 */
function isMarkDeleted(mark_id: number): boolean {
    return getMarkJQuery(mark_id).hasClass('mark-deleted');
}

/**
 * Gets if the state of the custom mark is such that it should appear checked
 * Note: if the component is in edit mode, this will never return true
 */
function hasCustomMark(component_id: number): boolean {
    if (isEditModeEnabled()) {
        return false;
    }
    const gradedComponent = getGradedComponentFromDOM(component_id);
    return gradedComponent.comment !== '';
}

/**
 * Gets if the custom mark on a component is 'checked'
 */
function isCustomMarkChecked(component_id: number): boolean {
    return getCustomMarkJQuery(component_id).find('.mark-selected').length > 0;
}

/**
 * Checks the custom mark checkbox
 */
function checkDOMCustomMark(component_id: number): void {
    getCustomMarkJQuery(component_id).find('.mark-selector').addClass('mark-selected');
}

/**
 * Un-checks the custom mark checkbox
 */
function unCheckDOMCustomMark(component_id: number): void {
    getCustomMarkJQuery(component_id).find('.mark-selector').removeClass('mark-selected');
}

/**
 * Toggles the state of the custom mark checkbox in the DOM
 * @param {number} component_id
 */
function toggleDOMCustomMark(component_id: number): void {
    getCustomMarkJQuery(component_id).find('.mark-selector').toggleClass('mark-selected');
}

/**
 * Opens the 'users who got mark' dialog
 * @param {string} component_title
 * @param {string} mark_title
 * @param {Object} stats
 */
function openMarkStatsPopup(component_title: string, mark_title: string, stats: MarkStats): void {
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
    const urlSplit = location.href.split('?');
    let base_url = urlSplit[0];
    if (base_url.slice(base_url.length - 6) == 'update') {
        base_url = `${base_url.slice(0, -6)}grading/grade`;
    }
    const search_params = new URLSearchParams(urlSplit[1]);
    stats.submitter_ids.forEach((id: string | number) =>  {
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
function anyUnverifiedComponents(): boolean {
    return $('.verify-container').length > 0;
}

/**
 * Hides the verify all button if there are no components to verify
 */
function updateVerifyAllButton(): void {
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
function getComponentVersionConflict(graded_component: { graded_version: number; } | undefined): boolean {
    return graded_component !== undefined && graded_component.graded_version !== getDisplayVersion();
}

/**
 * Sets the error state of the custom mark message
 * @param {number} component_id
 * @param {boolean} show_error
 */
function setCustomMarkError(component_id: number, show_error: boolean): void {
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
function disableEditModeBox(disabled: boolean): void {
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
function onAddNewMark(me: HTMLElement): void {
    addNewMark(getComponentIdFromDOMElement(me))
        .catch((err) =>  {
            console.error(err);
            alert(`Error adding mark! ${err.message}`);
        });
}

/**
 * Called when a mark is marked for deletion
 * @param me DOM Element of the delete button
 */
function onDeleteMark(me: HTMLElement): void {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
}

/**
 * Called when a mark marked for deletion gets restored
 * @param me DOM Element of the restore button
 */
function onRestoreMark(me: HTMLElement): void {
    $(me).parents('.mark-container').toggleClass('mark-deleted');
}

/**
 * Called when a component is deleted
 * @param me DOM Element of the delete button
 */
function onDeleteComponent(me: HTMLElement): void {
    if (!confirm('Are you sure you want to delete this component?')) {
        return;
    }
    deleteComponent(getComponentIdFromDOMElement(me))
        .catch((err) =>  {
            console.error(err);
            alert(`Failed to delete component! ${err.message}`);
        })
        .then(() =>  {
            return reloadInstructorEditRubric(getGradeableId(), isItempoolAvailable(), getItempoolOptions());
        })
        .catch((err) =>  {
            alert(`Failed to reload rubric! ${err.message}`);
        });
}

/**
 * Called when the 'add new component' button is pressed
 */
function onAddComponent(peer: boolean): void {
    addComponent(peer)
        .catch((err) =>  {
            console.error(err);
            alert(`Failed to add component! ${err.message}`);
        })
        .then(() =>  {
            return closeAllComponents(true);
        })
        .then(() =>  {
            return reloadInstructorEditRubric(getGradeableId(), isItempoolAvailable(), getItempoolOptions());
        })
        .then(() =>  {
            return openComponent(getComponentIdByOrder(getComponentCount() - 1));
        })
        .catch((err) =>  {
            alert(`Failed to reload rubric! ${err.message}`);
        });
}

/**
 * Called when the 'Import Components' button is pressed
 */
function importComponentsFromFile(): void {
    const submit_url = buildCourseUrl(['gradeable', getGradeableId(), 'components', 'import']);
    const formData = new FormData();

    const files = (<HTMLInputElement>$('#import-components-file')[0]).files;

    if (files === null || files.length === 0) {
        return;
    }

    // Files selected
    for (let i = 0; i < files.length; i++) {
        formData.append(`files${i}`, files[i], files[i].name);
    }

    formData.append('csrf_token', getCsrfToken());

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        dataType: 'json',
        success: function (response: { status: string; message: string | undefined; }) {
            if (response.status !== 'success') {
                console.error(`Something went wrong importing components: ${response.message}`);
            }
            else {
                location.reload();
            }
        },
        error: function (e) {
            console.log(e);
            alert('Error parsing response from server. Please copy the contents of your Javascript Console and ' +
                'send it to an administrator, as well as what you were doing and what files you were uploading. - [handleUploadGradeableComponents]');
        },
    });
}

/**
 * Called when the point value of a common mark changes
 * @param me DOM Element of the mark point entry
 */
function onMarkPointsChange(me: HTMLElement): void {
    refreshComponentHeader(getComponentIdFromDOMElement(me), true)
        .catch((err) =>  {
            console.error(err);
            alert(`Error updating component! ${err.message}`);
        });
}

/**
 * Called when the mark stats button is pressed
 * @param me DOM Element of the mark stats button
 */
function onGetMarkStats(me: HTMLElement): void {
    const component_id = getComponentIdFromDOMElement(me);
    const mark_id = getMarkIdFromDOMElement(me);
    ajaxGetMarkStats(getGradeableId(), component_id, mark_id)
        .then((stats) =>  {
            const component_title = getComponentFromDOM(component_id).title ?? '';
            const mark_dom = getMarkFromDOM(mark_id);
            const mark_title = mark_dom ? mark_dom.title : '';

            openMarkStatsPopup(component_title, mark_title, stats);
        })
        .catch((err) =>  {
            alert(`Failed to get stats for mark: ${err.message}`);
        });
}

/**
 * Called when a component gets clicked (for opening / closing)
 * @param me DOM Element of the component header div
 * @param edit_mode editing from ta grading page or instructor edit gradeable page
 */
function onClickComponent(me: HTMLElement, edit_mode = false): void {
    const component_id = getComponentIdFromDOMElement(me);
    toggleComponent(component_id, true, edit_mode)
        .catch((err) =>  {
            console.error(err);
            setComponentInProgress(component_id, false);
            alert(`Error opening/closing component! ${err.message}`);
        });
}

/**
 * Called when the 'cancel' button is pressed on an open component
 * @param me DOM Element of the cancel button
 */
function onCancelComponent(me: HTMLElement): void {
    const component_id = getComponentIdFromDOMElement(me);
    const gradeable_id = getGradeableId();
    const anon_id = getAnonId();
    ajaxGetGradedComponent(gradeable_id, component_id, anon_id).then((component) => {
        const customMarkNote = $(`#component-${component_id}`).find('.mark-note-custom').val();
        // If there is any changes made in comment of a component , prompt the TA
        if ((component && component.comment !== customMarkNote) || (!component && customMarkNote !== '')) {
            if (confirm('Are you sure you want to discard all changes to the student message?')) {
                toggleComponent(component_id, false)
                    .catch((err) => {
                        console.error(err);
                        alert(`Error closing component! ${err.message}`);
                    });
            }
        }
        // There is no change in comment, i.e it is same as the saved comment (before)
        else {
            toggleComponent(component_id, false)
                .catch((err) => {
                    console.error(err);
                    alert(`Error closing component! ${err.message}`);
                });
        }
    });
}

function onCancelEditRubricComponent(me: HTMLElement): void {
    const component_id = getComponentIdFromDOMElement(me);
    toggleComponent(component_id, false, true);
}

/**
 * Called when the overall comment box is changed
 */
function onChangeOverallComment(): void {
    // Get the current grader so that we can get their comment from the dom.
    const grader = getGraderId();
    const currentOverallComment = $(`textarea#overall-comment-${grader}`).val() as string;
    const previousOverallComment = $(`textarea#overall-comment-${grader}`).data('previous-comment') as string;

    if (currentOverallComment !== previousOverallComment && currentOverallComment !== undefined) {
        $('.overall-comment-status').text('Saving Changes...');
        // If anything has changed, save the changes.
        ajaxSaveOverallComment(getGradeableId(), getAnonId(), currentOverallComment).then(() => {
            $('.overall-comment-status').text('All Changes Saved');
            // Update the current comment in the DOM.
            $(`textarea#overall-comment-${grader}`).data('previous-comment', currentOverallComment);
        }).catch(() =>  {
            $('.overall-comment-status').text('Error Saving Changes');
        });
    }
}

/**
 * When the component order changes, update the server
 */
function onComponentOrderChange(): void {
    ajaxSaveComponentOrder(getGradeableId(), getComponentOrders())
        .catch((err) =>  {
            console.error(err);
            alert(`Error reordering components! ${err.message}`);
        });
}

/**
 * Called when a mark is clicked in grade mode
 * @param me DOM Element of the mark div
 */
function onToggleMark(me: HTMLElement): void {
    toggleCommonMark(getComponentIdFromDOMElement(me), getMarkIdFromDOMElement(me))
        .catch((err) =>  {
            console.error(err);
            alert(`Error toggling mark! ${err.message}`);
        });
}

/**
 * Called when one of the custom mark fields changes
 * @param me DOM Element of one of the custom mark's elements
 */
function onCustomMarkChange(me: HTMLElement): void {
    updateCustomMark(getComponentIdFromDOMElement(me))
        .catch((err) =>  {
            console.error(err);
            alert(`Error updating custom mark! ${err.message}`);
        });
}

/**
 * Toggles the 'checked' state of the custom mark.  This effectively
 *  makes the 'score' and 'comment' values 0 and '' respectively when
 *  loading the graded component from the DOM, but leaves the values in
 *  the DOM if the user toggles this again.
 * @param me
 */
function onToggleCustomMark(me: HTMLElement): void {
    const component_id = getComponentIdFromDOMElement(me);
    const graded_component = getGradedComponentFromDOM(component_id);
    if (graded_component.comment === '') {
        setCustomMarkError(component_id, true);
        return;
    }
    toggleDOMCustomMark(component_id);
    toggleCustomMark(component_id)
        .catch((err) =>  {
            console.error(err);
            alert(`Error toggling custom mark! ${err.message}`);
        });
}

/**
 * Callback for the 'verify' buttons
 * @param me DOM Element of the verify button
 */
function onVerifyComponent(me: HTMLElement): void {
    verifyComponent(getComponentIdFromDOMElement(me))
        .catch((err) =>  {
            console.error(err);
            alert(`Error verifying component! ${err.message}`);
        });
}

/**
 * Callback for the 'verify all' button
 * @param me DOM Element of the verify all button
 */
function onVerifyAll(me: HTMLElement): void {
    verifyAllComponents()
        .catch((err) =>  {
            console.error(err);
            alert(`Error verifying all components! ${err.message}`);
        });
}

/**
 * Callback for the 'edit mode' checkbox changing states
 * @param me DOM Element of the checkbox
 */
function onToggleEditMode(me: HTMLElement): void {
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

    // Build a sequence to save open component
    let sequence = Promise.resolve();
    sequence = sequence.then(() =>  {
        return saveComponent(reopen_component_id);
    });
    // Once components are saved, reload the component in edit mode
    sequence.catch((err) =>  {
        console.error(err);
        alert(`Error saving component! ${err.message}`);
    })
        .then(() =>  {
            updateEditModeEnabled();
            if (reopen_component_id !== NO_COMPONENT_ID) {
                return reloadGradingComponent(reopen_component_id, isEditModeEnabled(), true);
            }
        })
        .catch((err) =>  {
            console.error(err);
            alert(`Error reloading component! ${err.message}`);
        })
        .then(() =>  {
            disableEditModeBox(false);
        });
}

/**
 * Callback for the 'count up' option of a component in instructor edit mode
 * @param me DOM element of the 'count up' radio button
 */
function onClickCountUp(me: HTMLElement): void {
    const component_id = getComponentIdFromDOMElement(me);
    const mark_id = getComponentFirstMarkId(component_id);
    setMarkTitle(mark_id, 'No Credit');
    $.get('Mark.twig', () => {
        $("input[id^='mark-editor-']").each((index, element) =>  {
            const inputElement = element as HTMLInputElement;
            $(inputElement).attr('overall', 'No Credit');
            if (parseFloat(inputElement.value) < 0) { //TODO: Verify functionality
                inputElement.style.backgroundColor = 'var(--standard-vibrant-yellow)';
            }
            else {
                inputElement.style.backgroundColor = 'var(--default-white)';
            }
        });
    });
}

/**
 * Callback for the 'count down' option of a component in instructor edit mode
 * @param me DOM element of the 'count down' radio button
 */
function onClickCountDown(me: HTMLElement): void {
    const component_id = getComponentIdFromDOMElement(me);
    const mark_id = getComponentFirstMarkId(component_id);
    setMarkTitle(mark_id, 'Full Credit');
    $.get('Mark.twig', () => {
        $("input[id^='mark-editor-']").each((_index, element) =>  {
            const inputElement = element as HTMLInputElement;
            $(inputElement).attr('overall", "Full Credit');
            if (parseFloat(inputElement.value) > 0) {
                inputElement.style.backgroundColor = 'var(--standard-vibrant-yellow)';
            }
            else {
                inputElement.style.backgroundColor = 'var(--default-white)';
            }
        });
    });
}

/**
 * Callback for changing on the point values for a component
 * Does not change point value if not divisible by precision
 * @param me DOM element of the input box
 */
function onComponentPointsChange(me: HTMLElement): void {
    if (dividesEvenly(parseInt($(me).val() as string), getPointPrecision())) {
        $(me).css('background-color', 'var(--standard-input-background)');
        refreshInstructorEditComponentHeader(getComponentIdFromDOMElement(me), true)
            .catch((err) =>  {
                console.error(err);
                alert(`Failed to refresh component! ${err.message}`);
            });
    }
    else {

        // Make box red to indicate error
        $(me).css('background-color', '#ff7777');
    }
}

/**
 * Returns true if dividend is evenly divisible by divisor, false otherwise
 * @param {number} dividend
 * @param {number} divisor
 * @returns {boolean}
 */
function dividesEvenly(dividend: number, divisor: number): boolean {
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

/**
 * Callback for changing the title for a component
 * @param me DOM element of the input box
 */
function onComponentTitleChange(me: HTMLElement): void {
    getComponentJQuery(getComponentIdFromDOMElement(me)).find('.component-title-text').text($(me).val() as string);
}

/**
 * Callback for changing the page number for a component
 * @param me DOM element of the input box
 */
function onComponentPageNumberChange(me: HTMLElement): void {
    getComponentJQuery(getComponentIdFromDOMElement(me)).find('.component-page-number-text').text($(me).val() as string);
}

/**
 * Callback for changing the 'publish' setting of a mark
 * @param me DOM element of the check box
 */
function onMarkPublishChange(me: HTMLElement): void {
    getMarkJQuery(getMarkIdFromDOMElement(me)).toggleClass('mark-publish');
}

/**
 * Put all of the primary logic of the TA grading rubric here
 *
 */


/**
 * Verifies a component with the grader and reloads the component
 * @param {number} component_id
 * @returns {Promise}
 */
function verifyComponent(component_id: number) {
    const gradeable_id = getGradeableId();
    return ajaxVerifyComponent(gradeable_id, component_id, getAnonId())
        .then(() =>  {
            return reloadGradingComponent(component_id);
        })
        .then(() =>  {
            updateVerifyAllButton();
        });
}

/**
 * Verifies all graded components and reloads the rubric
 * @returns {Promise}
 */
function verifyAllComponents() {
    const gradeable_id = getGradeableId();
    const anon_id = getAnonId();
    return ajaxVerifyAllComponents(gradeable_id, anon_id)
        .then(() =>  {
            return reloadGradingRubric(gradeable_id, anon_id);
        })
        .then(() =>  {
            updateVerifyAllButton();
        });
}

/**
 * Adds a blank component to the gradeable
 * @return {Promise}
 */
function addComponent(peer: boolean) {
    return ajaxAddComponent(getGradeableId(), peer);
}

/**
 * Deletes a component from the server
 * @param {number} component_id
 * @returns {Promise}
 */
function deleteComponent(component_id: number) {
    return ajaxDeleteComponent(getGradeableId(), component_id);
}

/**
 * Sets the gradeable-wide page setting
 * @param {number} page PDF_PAGE_INSTRUCTOR, PDF_PAGE_STUDENT, or PDF_PAGE_NONE
 * @return {Promise}
 */
function setPdfPageAssignment(page: number) {
    if (page === PDF_PAGE_INSTRUCTOR) {
        page = 1;
    }

    return closeAllComponents(true)
        .then(() =>  {
            return ajaxSaveComponentPages(getGradeableId(), { 'page': page });
        })
        .then(() =>  {
            // Reload the gradeable to refresh all the component's display
            return reloadInstructorEditRubric(getGradeableId(), isItempoolAvailable(), getItempoolOptions());
        });
}

/**
 * Searches a array of marks for a mark with an id
 * @param {Object[]} marks
 * @param {number} mark_id
 * @return {Object}
 */
function getMarkFromMarkArray(marks: Mark[], mark_id: number): Mark | null {
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
 * @return {Promise}
 */
function reloadGradingRubric(gradeable_id: string, anon_id: string) {
    let gradeable_tmp: unknown = null;
    return ajaxGetGradeableRubric(gradeable_id)
        .catch((err) =>  {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) =>  {
            gradeable_tmp = gradeable;
            return ajaxGetGradedGradeable(gradeable_id, anon_id, false);
        })
        .catch((err) =>  {
            alert(`Could not fetch graded gradeable: ${err.message}`);
        })
        .then((graded_gradeable) =>  {
            return renderGradingGradeable(getGraderId(), gradeable_tmp, graded_gradeable,
                isGradingDisabled(), canVerifyGraders(), getDisplayVersion());
        })
        .then((elements) =>  {
            setRubricDOMElements(elements);
            return openCookieComponent();
        })
        .catch((err) =>  {
            alert(`Could not render gradeable: ${err.message}`);
            console.error(err);
        });

}
/**
 * Call this to update the totals and subtotals once a grader is done grading a component
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise}
 */

function updateTotals(gradeable_id: string, anon_id: string) {
    let gradeable_tmp: GradeableRubric | null = null;
    return ajaxGetGradeableRubric(gradeable_id)
        .catch((err) =>  {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) =>  {
            gradeable_tmp = gradeable!;
            return ajaxGetGradedGradeable(gradeable_id, anon_id, false);
        })
        .catch((err) =>  {
            alert(`Could not fetch graded gradeable: ${err.message}`);
        })
        .then((graded_gradeable) =>  {
            return renderGradingGradeable(getGraderId(), gradeable_tmp!, graded_gradeable,
                isGradingDisabled(), canVerifyGraders(), getDisplayVersion());
        })
        .then((elements) =>  {
            setRubricDOMElements(elements);
        });
}

/**
 * Call this once on page load to load the peer panel.
 * Note: This takes 'gradeable_id' and 'anon_id' parameters since it gets called
 *  in the 'PeerPanel.twig' server template
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @return {Promise}
 */
function reloadPeerRubric(gradeable_id: string, anon_id: string) {
    TA_GRADING_PEER = true;
    let gradeable_tmp: GradeableRubric | null = null;
    return ajaxGetGradeableRubric(gradeable_id)
        .catch((err) =>  {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) =>  {
            gradeable_tmp = gradeable!;
            return ajaxGetGradedGradeable(gradeable_id, anon_id, true);
        })
        .catch((err) =>  {
            alert(`Could not fetch graded gradeable: ${err.message}`);
        })
        .then((graded_gradeable) =>  {
            return renderPeerGradeable(getGraderId(), gradeable_tmp!, graded_gradeable,
                true, false, getDisplayVersion());
        })
        .catch((err) =>  {
            alert(`Could not render gradeable: ${err.message}`);
            console.error(err);
        })
        .then((elements) => {
            const gradingBox = $('#peer-grading-box');
            gradingBox.html(elements);
        });
}

/**
 * Call this once on page load to load the rubric instructor editing
 * @return {Promise}
 */
function reloadInstructorEditRubric(gradeable_id: string, itempool_available: boolean, itempool_options: string[]) {
    return ajaxGetGradeableRubric(gradeable_id)
        .catch((err) =>  {
            alert(`Could not fetch gradeable rubric: ${err.message}`);
        })
        .then((gradeable) =>  {
            return renderInstructorEditGradeable(gradeable, itempool_available, itempool_options);
        })
        .then((elements) =>  {
            setRubricDOMElements(elements);
            return refreshRubricTotalBox();
        })
        .then(() =>  {
            return openCookieComponent();
        })
        .catch((err) =>  {
            alert(`Could not render gradeable: ${err.message}`);
            console.error(err);
        });
}

/**
 * Reloads the provided component with the grader view
 * @param {number} component_id
 * @param {boolean} editable True to load component in edit mode
 * @param {boolean} showMarkList True to show mark list as open
 * @returns {Promise}
 */
function reloadGradingComponent(component_id: number, editable = false, showMarkList = false) {
    let component_tmp: Component | null = null;
    const gradeable_id = getGradeableId();
    //TODO: check why this is here
    const graded_gradeable = ajaxGetGradedGradeable(gradeable_id, getAnonId(), false);
    return ajaxGetComponentRubric(gradeable_id, component_id)
        .then((component) =>  {
            // Set the global mark list data for this component for conflict resolution
            OLD_MARK_LIST[component_id] = component.marks;

            component_tmp = component;
            return ajaxGetGradedComponent(gradeable_id, component_id, getAnonId());
        })
        .then((graded_component) =>  {
            // Set the global graded component list data for this component to detect changes
            OLD_GRADED_COMPONENT_LIST[component_id] = graded_component;

            // Render the grading component with edit mode if enabled,
            //  and 'true' to show the mark list
            return injectGradingComponent(component_tmp!, graded_component, editable, showMarkList);
        });
}

/**
 * Opens the component in the cookie
 * @returns {Promise}
 */
function openCookieComponent() {
    const cookieComponent = getOpenComponentIdFromCookie();
    if (!componentExists(cookieComponent)) {
        return Promise.resolve();
    }
    return toggleComponent(cookieComponent, false)
        .then(() =>  {
            scrollToComponent(cookieComponent);
        });
}

/**
 * Closes all open components and the overall comment
 * @param {boolean} save_changes
 * @param {boolean} edit_mode editing from ta grading page or instructor edit gradeable page
 * @return {Promise<void>}
 */
function closeAllComponents(save_changes: boolean, edit_mode = false) {
    let sequence = Promise.resolve();

    // Close all open components.  There shouldn't be more than one,
    //  but just in case there is...
    getOpenComponentIds().forEach((id) =>  {
        sequence = sequence.then(() =>  {
            return closeComponent(id, save_changes, edit_mode);
        });
    });
    return sequence;
}

/**
 * Toggles the open/close state of a component
 * @param {number} component_id the component's id
 * @param {boolean} saveChanges
 * @param {edit_mode} editing from ta grading page or instructor edit gradeable page
 * @return {Promise}
 */
function toggleComponent(component_id: number, saveChanges: boolean, edit_mode = false) {
    let action = Promise.resolve();
    // Component is open, so close it
    if (isComponentOpen(component_id)) {
        action = action.then(() =>  {
            return closeComponent(component_id, saveChanges, edit_mode);
        });
    }
    else {
        action = action.then(() =>  {
            return closeAllComponents(saveChanges, edit_mode)
                .then(() =>  {
                    return openComponent(component_id);
                });
        });
    }

    // Save the open component in the cookie
    return action.then(() =>  {
        updateCookieComponent();
    });
}

function open_overall_comment_tab(user: string): void {
    const textarea = $(`#overall-comment-${user}`);
    const comment_root = textarea.closest('.general-comment-entry');

    $('#overall-comments').children().hide();
    $('#overall-comment-tabs').children().removeClass('active-btn');
    comment_root.show();
    $(`#overall-comment-tab-${user}`).addClass('active-btn');

    //if the tab is for the main user of the page
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

}

/**
 * Adds a new mark to the DOM and refreshes the display
 * @param {number} component_id
 * @return {Promise}
 */
function addNewMark(component_id: number) {
    const component = getComponentFromDOM(component_id);
    component.marks.push({
        id: getNewMarkId(),
        title: '',
        points: 0.0,
        publish: false,
        order: component.marks.length,
    });
    let promise = Promise.resolve();
    if (!isInstructorEditEnabled()) {
        const graded_component = getGradedComponentFromDOM(component_id);
        promise = promise.then(() =>  {
            return injectGradingComponent(component, graded_component, true, true);
        });
    }
    else {
        promise = promise.then(() =>  {
            return injectInstructorEditComponent(component, true);
        });
    }
    return promise;
}

/**
 * Toggles the state of a mark in grade mode
 * @return {Promise}
 */
function toggleCommonMark(component_id: number, mark_id: number) {
    return isMarkChecked(mark_id) ? unCheckMark(component_id, mark_id) : checkMark(component_id, mark_id);
}

/**
 * Call to update the custom mark state when any of the custom mark fields change
 * @param {number} component_id
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
 * @param {number} component_id
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
 * @param {number} component_id
 * @return {Promise}
 */
function openComponentInstructorEdit(component_id: number) {
    const gradeable_id = getGradeableId();
    return ajaxGetComponentRubric(gradeable_id, component_id)
        .then((component) =>  {
            // Set the global mark list data for this component for conflict resolution
            OLD_MARK_LIST[component_id] = component.marks;

            // Render the component in instructor edit mode
            //  and 'true' to show the mark list
            return injectInstructorEditComponent(component, true, true);
        });
}

/**
 * Opens a component for grading mode (including normal edit mode)
 * NOTE: don't call this function on its own.  Call 'openComponent' Instead
 * @param {number} component_id
 * @return {Promise}
 */
function openComponentGrading(component_id: number) {
    return reloadGradingComponent(component_id, isEditModeEnabled(), true)
        .then(() =>  {
            const page = getComponentPageNumber(component_id);
            if (page) {
                scrollToPage(page);
            }
        });
}

/**
 * Scrolls the submission panel to the page number specified by the component
 * TODO: This is currently very clunky, and only works with test files (aka upload.pdf).
 * @param {number} page_num
 * @return {void}
 */
function scrollToPage(page_num: number): void {
    const files = $('.openable-element-submissions');
    for (let i = 0; i < files.length; i++) {
        if (files[i].innerText.trim() == 'upload.pdf') {
            const page = $(`#pageContainer${page_num}`);
            if ($('#file-view').is(':visible')) {
                if (page.length) {
                    $('#file-content').animate({ scrollTop: page[0].offsetTop }, 500);
                }
            }
            else {
                expandFile('upload.pdf', files[i].getAttribute('file-url'), page_num - 1);
            }
        }
    }
}

/**
 * Opens the requested component
 * Note: This does not close the currently open component
 * @param {number} component_id
 * @return {Promise}
 */
function openComponent(component_id: number) {
    setComponentInProgress(component_id);
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    return (isInstructorEditEnabled() ? openComponentInstructorEdit(component_id) : openComponentGrading(component_id))
        .then(resizeNoScrollTextareas);
}

/**
 * Scroll such that a given component is visible
 * @param component_id
 */
function scrollToComponent(component_id: number): void {
    const component = getComponentJQuery(component_id);
    component[0].scrollIntoView();
}

/**
 * Closes a component for instructor edit mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {number} component_id
 * @param {boolean} saveChanges If the changes to the component should be saved or discarded
 * @return {Promise}
 */
function closeComponentInstructorEdit(component_id: number, saveChanges: boolean) {
    let sequence = Promise.resolve();
    const component = getComponentFromDOM(component_id);
    const countUp = getCountDirection(component_id) !== COUNT_DIRECTION_DOWN;
    if (saveChanges) {
        sequence = sequence
            .then(() =>  {
                if (component.max_value == 0 && component.upper_clamp == 0 && component.lower_clamp < 0) {
                    const mark_title = 'No Penalty Points';
                    component.marks[0].title = mark_title;
                    ($(`#mark-${component.marks[0].id.toString()}`).find(':input')[1] as HTMLInputElement).value = 'No Penalty Points';

                }
                else if (component.max_value == 0 && component.upper_clamp > 0 && component.lower_clamp == 0) {
                    const mark_title = 'No Extra Credit Awarded';
                    component.marks[0].title = mark_title;
                    ($(`#mark-${component.marks[0].id.toString()}`).find(':input')[1] as HTMLInputElement).value = 'No Extra Credit Awarded';
                }
                else if (countUp) {
                    const mark_title = 'No Credit';
                    component.marks[0].title = mark_title;
                    ($(`#mark-${component.marks[0].id.toString()}`).find(':input')[1] as HTMLInputElement).value = 'No Credit';

                }
                return saveMarkList(component_id);
            })
            .then(() =>  {
                // Save the component title and comments
                return ajaxSaveComponent(getGradeableId(), component_id, component.title, component.ta_comment,
                    component.student_comment, component.page, component.lower_clamp,
                    component.default, component.max_value, component.upper_clamp, component.is_itempool_linked, component.itempool_option);
            });
    }
    return sequence
        .then(() =>  {
            return ajaxGetComponentRubric(getGradeableId(), component_id);
        })
        .then((component) =>  {
            // Render the component with a hidden mark list
            return injectInstructorEditComponent(component, false);
        });
}

/**
 * Closes a component for grading mode and saves changes
 * NOTE: don't call this function on its own.  Call 'closeComponent' Instead
 * @param {number} component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @return {Promise}
 */
function closeComponentGrading(component_id: number, saveChanges: boolean) {
    let sequence = Promise.resolve();
    const gradeable_id = getGradeableId();
    const anon_id = getAnonId();
    let component_tmp: Component | null = null;

    if (saveChanges) {
        sequence = sequence.then(() =>  {
            return saveComponent(component_id);
        });
    }

    // Finally, render the graded component in non-edit mode with the mark list hidden
    return sequence
        .then(() =>  {
            return ajaxGetComponentRubric(gradeable_id, component_id);
        })
        .then((component) =>  {
            component_tmp = component;
        })
        .then(() =>  {
            return ajaxGetGradedComponent(gradeable_id, component_id, anon_id);
        })
        .then((graded_component) =>  {
            return injectGradingComponent(component_tmp!, graded_component, false, false);
        });

}

/**
 * Closes the requested component and saves any changes if requested
 * @param {number} component_id
 * @param {boolean} saveChanges If the changes to the (graded) component should be saved or discarded
 * @param {boolean} edit_mode editing from ta grading page or instructor edit gradeable page
 * @return {Promise}
 */
function closeComponent(component_id: number, saveChanges = true, edit_mode = false) {
    setComponentInProgress(component_id);
    // Achieve polymorphism in the interface using this `isInstructorEditEnabled` flag
    return (isInstructorEditEnabled()
        ? closeComponentInstructorEdit(component_id, saveChanges)
        : closeComponentGrading(component_id, saveChanges))
        .then(() =>  {
            setComponentInProgress(component_id, false);
        })
        .then(() =>  {
            if (!edit_mode) {
                const gradeable_id = getGradeableId();
                const anon_id = getAnonId();
                return updateTotals(gradeable_id, anon_id);
            }
        });
}

/**
 * Scroll such that the overall comment is visible
 */
function scrollToOverallComment(): void {
    const comment = getOverallCommentJQuery();
    comment[0].scrollIntoView();
}

/**
 * Checks the requested mark and refreshes the component
 * @param {number} component_id
 * @param {number} mark_id
 * @return {Promise}
 */
function checkMark(component_id: number, mark_id: number) {
    // Don't let them check a disabled mark
    if (isMarkDisabled(mark_id)) {
        return Promise.resolve();
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
    return injectGradingComponent(getComponentFromDOM(component_id), gradedComponent, false, true);
}

/**
 * Un-checks the requested mark and refreshes the component
 * @param {number} component_id
 * @param {number} mark_id
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
 * @param {number} component_id
 * @return {Promise}
 */
function unCheckFirstMark(component_id: number) {
    return unCheckMark(component_id, getComponentFirstMarkId(component_id));
}

export interface ConflictMark {
    domMark: Mark,
    serverMark: Mark | null,
    oldServerMark: Mark | null,
    localDeleted: boolean
}

/**
 * Saves the mark list to the server for a component and handles any conflicts.
 * Properties that are saved are: mark point values, mark titles, and mark order
 * @param {number} component_id
 * @return {Promise}
 */
function saveMarkList(component_id: number) {
    const gradeable_id = getGradeableId();
    return ajaxGetComponentRubric(gradeable_id, component_id)
        .then((component) =>  {
            const domMarkList = getMarkListFromDOM(component_id);
            const serverMarkList = component.marks;
            const oldServerMarkList = OLD_MARK_LIST[component_id];

            // associative array of associative arrays of marks with conflicts {<mark_id>: {domMark, serverMark, oldServerMark}, ...}
            //TODO: maybe make interface
            const conflictMarks: {[key: number]: ConflictMark} = {};

            let sequence = Promise.resolve();

            // For each DOM mark, try to save it
            domMarkList.forEach((domMark) =>  {
                const serverMark = getMarkFromMarkArray(serverMarkList, domMark.id);
                const oldServerMark = getMarkFromMarkArray(oldServerMarkList, domMark.id);
                sequence = sequence
                    .then(() =>  {
                        return tryResolveMarkSave(gradeable_id, component_id, domMark, serverMark, oldServerMark);
                    })
                    .then((success) =>  {
                        // success of false counts as conflict
                        if (success === false) {
                            conflictMarks[domMark.id] = {
                                domMark: domMark,
                                serverMark: serverMark,
                                oldServerMark: oldServerMark,
                                localDeleted: isMarkDeleted(domMark.id),
                            };
                        }
                    });
            });

            return sequence
                .then(() =>  {
                    // No conflicts, so don't open the popup
                    if (Object.keys(conflictMarks).length === 0) {
                        return;
                    }

                    // Prompt the user with any conflicts
                    return openMarkConflictPopup(component_id, conflictMarks);
                })
                .then(() =>  {
                    const markOrder: {[key: number]: number|undefined} = {};
                    domMarkList.forEach((mark) =>  {
                        markOrder[mark.id] = mark.order;
                    });
                    // Finally, save the order
                    return ajaxSaveMarkOrder(gradeable_id, component_id, markOrder);
                });
        });
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
 *  @return {Promise<boolean>} Resolves true on success, false on conflict
 */
function tryResolveMarkSave(gradeable_id: string, component_id: number, domMark: { id: number; title: string; points: number; publish: boolean; }, serverMark: Mark | null, oldServerMark: Mark | null) {
    const markDeleted = isMarkDeleted(domMark.id);
    if (oldServerMark !== null) {
        if (serverMark !== null) {
            // Mark edited under normal conditions
            if ((marksEqual(domMark, serverMark) || marksEqual(domMark, oldServerMark)) && !markDeleted) {
                // If the domMark is not unique, then we don't need to do anything
                return Promise.resolve(true);
            }
            else if (!marksEqual(serverMark, oldServerMark)) {
                // The domMark is unique, and the serverMark is also unique,
                // which means all 3 versions are different, which is a conflict state
                return Promise.resolve(false);
            }
            else if (markDeleted) {
                // domMark was deleted and serverMark hasn't changed from oldServerMark,
                //  so try to delete the mark
                return ajaxDeleteMark(gradeable_id, component_id, domMark.id)
                    .catch((err) =>  {
                        err.message = `Could not delete mark: ${err.message}`;
                        throw err;
                    })
                    .then(() =>  {
                        // Success, then resolve success
                        return Promise.resolve(true);
                    });
            }
            else {
                // The domMark is unique and the serverMark is the same as the oldServerMark
                //  so we should save the domMark to the server
                return ajaxSaveMark(gradeable_id, component_id, domMark.id, domMark.title, domMark.points, domMark.publish)
                    .then(() =>  {
                        // Success, then resolve success
                        return Promise.resolve(true);
                    });
            }
        }
        else {
            // This means it was deleted from the server.
            if (!marksEqual(domMark, oldServerMark) && !markDeleted) {
                // And the mark changed and wasn't deleted, which is a conflict state
                return Promise.resolve(false);
            }
            else {
                // And the mark didn't change or it was deleted, so don't do anything
                return Promise.resolve(domMark.id);
            }
        }
    }
    else {
        // This means it didn't exist when we started editing, so serverMark must also be null
        if (markDeleted) {
            // The mark was marked for deletion, but never existed... so do nothing
            return Promise.resolve(true);
        }
        else {
            // The mark never existed and isn't deleted, so its new
            return ajaxAddNewMark(gradeable_id, component_id, domMark.title, domMark.points, domMark.publish)
                .then((data) =>  {
                    // Success, then resolve true
                    domMark.id = data.mark_id;
                    return Promise.resolve(true);
                })
                .catch((err) =>  {
                    // This means the user's mark was invalid
                    err.message = `Failed to add mark: ${err.message}`;
                    throw err;
                });
        }
    }
}

/**
 * Checks if two graded components are equal
 * @param {Object} gcDOM Must not be undefined
 * @param {Object} gcOLD May be undefined
 * @returns {boolean}
 */
function gradedComponentsEqual(gcDOM: GradedComponent, gcOLD: GradedComponent | undefined) {
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

function saveComponent(component_id: number) {
    // We are saving changes...
    if (isEditModeEnabled()) {
        // We're in edit mode, so save the component and fetch the up-to-date grade / rubric data
        return saveMarkList(component_id);
    }
    else {
        // The grader unchecked the custom mark, but didn't delete the text.  This shouldn't happen too often,
        //  so prompt the grader if this is what they really want since it will delete the text / score.
        const gradedComponent = getGradedComponentFromDOM(component_id);
        //only show error if custom marks are allowed
        if (gradedComponent.custom_mark_enabled && gradedComponent.comment !== '' && !gradedComponent.custom_mark_selected && getAllowCustomMarks()) {

            if (!confirm('Are you sure you want to delete the custom mark?')) {
                return Promise.reject();
            }
        }
        // We're in grade mode, so save the graded component
        return saveGradedComponent(component_id);
    }
}

/**
 * Saves the component grade information to the server
 * Note: if the mark was deleted remotely, but the submitter was assigned it locally, the mark
 *  will be resurrected with a new id
 * @param {number} component_id
 * @return {Promise}
 */
function saveGradedComponent(component_id: number) {
    const gradeable_id = getGradeableId();
    const gradedComponent = getGradedComponentFromDOM(component_id);
    gradedComponent.graded_version = getDisplayVersion();

    // The grader didn't change the grade at all, so don't save (don't put our name on a grade we didn't contribute to)
    if (gradedComponentsEqual(gradedComponent, OLD_GRADED_COMPONENT_LIST[component_id])) {
        return Promise.resolve();
    }
    return ajaxGetComponentRubric(getGradeableId(), component_id)
        .then((component) =>  {
            const missingMarks: Mark[] = [];
            const domComponent = getComponentFromDOM(component_id);

            // Check each mark the submitter was assigned
            gradedComponent.mark_ids.forEach((mark_id) =>  {
                // Mark exists remotely, so no action required
                if (getMarkFromMarkArray(component.marks, mark_id) !== null) {
                    return;
                }
                missingMarks.push(getMarkFromMarkArray(domComponent.marks, mark_id));
            });

            // For each mark missing from the server, add it
            let sequence = Promise.resolve();
            missingMarks.forEach((mark) =>  {
                sequence = sequence
                    .then(() =>  {
                        return ajaxAddNewMark(gradeable_id, component_id, mark.title, mark.points, mark.publish);
                    })
                    .then((data) =>  {
                        // Make sure to add it to the grade.  We don't bother removing the deleted mark ids
                        //  however, because the server filters out non-existent mark ids
                        gradedComponent.mark_ids.push(data.mark_id);
                    });
            });
            return sequence;
        })
        .then(() =>  {
            return ajaxSaveGradedComponent(
                getGradeableId(), component_id, getAnonId(),
                gradedComponent.graded_version,
                gradedComponent.custom_mark_selected ? gradedComponent.score : 0.0,
                gradedComponent.custom_mark_selected ? gradedComponent.comment : '',
                isSilentEditModeEnabled(),
                gradedComponent.mark_ids);
        });
}

/**
 * Re-renders the graded component header with the data in the DOM
 *  and preserves the edit/grade mode display
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
function refreshGradedComponentHeader(component_id: number, showMarkList: boolean) {
    return injectGradingComponentHeader(
        getComponentFromDOM(component_id),
        getGradedComponentFromDOM(component_id), showMarkList);
}


/**
 * Re-renders the graded component with the data in the DOM
 *  and preserves the edit/grade mode display
 * @param {number} component_id
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
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
function refreshInstructorEditComponentHeader(component_id: number, showMarkList: boolean) {
    return injectInstructorEditComponentHeader(getComponentFromDOM(component_id), showMarkList);
}

/**
 * Re-renders the component with the data in the DOM
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function refreshInstructorEditComponent(component_id: number, showMarkList: boolean) {
    return injectInstructorEditComponent(getComponentFromDOM(component_id), showMarkList);
}

/**
 * Re-renders the component's header block with the data in the DOM
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the header should be styled like the component is open
 * @return {Promise}
 */
function refreshComponentHeader(component_id: number, showMarkList: boolean) {
    return isInstructorEditEnabled() ? refreshInstructorEditComponentHeader(component_id, showMarkList) : refreshGradedComponentHeader(component_id, showMarkList);
}

/**
 * Re-renders the component with the data in the DOM
 * @param {number} component_id
 * @param {boolean} showMarkList Whether the mark list should be visible
 * @return {Promise}
 */
function refreshComponent(component_id: number, showMarkList: boolean) {
    return isInstructorEditEnabled() ? refreshInstructorEditComponent(component_id, showMarkList) : refreshGradedComponent(component_id, showMarkList);
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
 * @return {Promise}
 */
function injectInstructorEditComponent(component: Component, showMarkList: boolean, loadItempoolOptions = false) {
    return renderEditComponent(component, getPointPrecision(), showMarkList)
        .then((elements) =>  {
            setComponentContents(component.id, elements);
        })
        .then(() =>  {
            return refreshRubricTotalBox();
        }).then(() =>  {
            if (isItempoolAvailable() && loadItempoolOptions) {
                addItempoolOptions(component.id);
            }
        });
}

/**
 * Renders the provided component object for instructor edit mode header
 * @param {boolean} showMarkList Whether to style the header like the mark list is open
 * @return {Promise}
 */
function injectInstructorEditComponentHeader(component: Component, showMarkList: boolean) {
    return renderEditComponentHeader(component, getPointPrecision(), showMarkList)
        .then((elements) =>  {
            setComponentHeaderContents(component.id, elements);
        })
        .then(() =>  {
            return refreshRubricTotalBox();
        });
}

/**
 * Renders the provided component/graded_component object for grading/editing
 * @param {boolean} editable Whether the component should appear in edit or grade mode
 * @param {boolean} showMarkList Whether to show the mark list or not
 * @return {Promise}
 */
function injectGradingComponent(component: Component, graded_component: GradedComponent, editable: boolean, showMarkList: boolean) {
    const is_student_grader = !!($('#student-grader').attr('is-student-grader'));
    return renderGradingComponent(getGraderId(), component, graded_component, isGradingDisabled(), canVerifyGraders(), getPointPrecision(), editable, showMarkList, getComponentVersionConflict(graded_component), is_student_grader, TA_GRADING_PEER, getAllowCustomMarks())
        .then((elements) =>  {
            setComponentContents(component.id, elements);
        });
}

/**
 * Renders the provided component/graded_component header
 * @param showMarkList Whether to style the header like the mark list is open
 */
function injectGradingComponentHeader(component: Component, graded_component: GradedComponent, showMarkList: boolean) {
    return renderGradingComponentHeader(getGraderId(), component, graded_component, isGradingDisabled(), canVerifyGraders(), showMarkList, getComponentVersionConflict(graded_component))
        .then((elements) =>  {
            setComponentHeaderContents(component.id, elements);
        })
        .then(() =>  {
            return refreshTotalScoreBox();
        });
}

/**
 * Renders the total scores box
 */
function injectTotalScoreBox(scores: Score) {
    return renderTotalScoreBox(scores)
        .then((elements) =>  {
            setTotalScoreBoxContents(elements);
        });
}

/**
 * Renders the rubric total box (instructor edit mode)
 * @returns {Promise<string>}
 */
function injectRubricTotalBox(scores: RubricTotal) {
    return renderRubricTotalBox(scores)
        .then((elements) =>  {
            setRubricTotalBoxContents(elements);
        });
}

function addItempoolOptions(componentId: number): void {
    // create option elements for the itempool options
    const itempools = getItempoolOptions();
    const select_ele = $(`#component-itempool-select-${componentId}`);
    const selected_value = select_ele.attr('data-selected') ? select_ele.attr('data-selected') as string : 'null';
    const itempool_options = ['<option value="null">NONE</option>'];

    for (const key in itempools) {
        itempool_options.push(`<option value='${key}'>${key} (${itempools[key].join(', ')})</option>`);
    }
    select_ele.html(itempool_options);
    select_ele.val(selected_value).change();
}
