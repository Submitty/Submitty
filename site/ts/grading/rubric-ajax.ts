import { getCsrfToken } from '../utils/server';
import { Component } from './types/Component';
import { GradeableRubric } from './types/GradeableRubric';
import { GradedComponent } from './types/GradedComponent';
import { GradedGradeable } from './types/GradedGradeable';
import { AddNewMarkResponse } from './types/Mark';
import { MarkStats } from './types/MarkStats';

/**
 * Whether ajax requests will be asynchronous or synchronous.  This
 *  is used instead of passing an 'async' parameter to every function.
 * @type {boolean}
 */
let AJAX_USE_ASYNC = true;

export function getAjaxUseAsync(): boolean {
    return AJAX_USE_ASYNC;
}

export function setAjaxUseAsync(ajax_use_async: boolean): void {
    AJAX_USE_ASYNC = ajax_use_async;
}

/**
 * Called internally when an ajax function irrecoverably fails before rejecting
 * @param err
 */
function displayAjaxError(err: unknown): void {
    console.error("Failed to parse response.  The server isn't playing nice...");
    console.error(err);
    // alert("There was an error communicating with the server. Please refresh the page and try again.");
}

/**
 * ajax call to fetch the gradeable's rubric
 * @param {string} gradeable_id
 * @return {Promise} Rejects except when the response returns status 'success'
 */
export function ajaxGetGradeableRubric(gradeable_id: string): Promise<GradeableRubric> {
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
export function ajaxSaveComponent(gradeable_id: string, component_id: number, title: string, ta_comment: string, student_comment: string, page: number, lower_clamp: number, default_value: number, max_value: number, upper_clamp: number, is_itempool_linked: boolean, itempool_option: string | undefined): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxGetComponentRubric(gradeable_id: string, component_id: number): Promise<Component> {
    return new Promise((resolve, reject) => {
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

/**
 * ajax call to get the entire graded gradeable for a user
 * @param {string} gradeable_id
 * @param {string} anon_id
 * @param {boolean} all_peers
 * @return {Promise} Rejects except when the response returns status 'success'
 */
export function ajaxGetGradedGradeable(gradeable_id: string, anon_id: string, all_peers: boolean): Promise<GradedGradeable | null> {
    return new Promise((resolve, reject) => {
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
                    resolve(response.data as (GradedGradeable | null));
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
export function ajaxGetGradedComponent(gradeable_id: string, component_id: number, anon_id: string): Promise<GradedComponent> {
    return new Promise((resolve, reject) => {
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
export function ajaxSaveGradedComponent(gradeable_id: string, component_id: number, anon_id: string, graded_version: number, custom_points: number, custom_message: string, silent_edit: boolean, mark_ids: number[]): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxSaveOverallComment(gradeable_id: string, anon_id: string, overall_comment: string): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
 * ajax call to add a new mark to the component
 * @param {string} gradeable_id
 * @param {number} component_id
 * @param {string} title
 * @param {number} points
 * @param {boolean} publish
 * @return {Promise} Rejects except when the response returns status 'success'
 */
export function ajaxAddNewMark(gradeable_id: string, component_id: number, title: string, points: number, publish: boolean): Promise<AddNewMarkResponse> {
    return new Promise((resolve, reject) => {
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
export function ajaxDeleteMark(gradeable_id: string, component_id: number, mark_id: number): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxSaveMark(gradeable_id: string, component_id: number, mark_id: number, title: string, points: number, publish: boolean): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxGetMarkStats(gradeable_id: string, component_id: number, mark_id: number): Promise<MarkStats> {
    return new Promise((resolve, reject) => {
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
                    resolve(response.data as MarkStats);
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
export function ajaxSaveMarkOrder(gradeable_id: string, component_id: number, order: { [key: number]: number | undefined }): Promise<void> { //TODO: UPDATE
    return new Promise((resolve, reject) => {
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
                    resolve();
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
 * @param {*} pages format: { <component0-id> : <page0>, <component1-id> : <page1>, ... } OR { page } to set all
 * @return {Promise<void>} Rejects except when the response returns status 'success'
 */
export function ajaxSaveComponentPages(gradeable_id: string, pages: { [key: number]: number } | { page: number }): Promise<void> { //TODO: ANY
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxSaveComponentOrder(gradeable_id: string, order: { [key: number]: number }): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxAddComponent(gradeable_id: string, peer: boolean): Promise<{ 'component_id': number }> { //TODO: CHECK COMMENT
    return new Promise((resolve, reject) => {
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
                    resolve(response.data as { 'component_id': number });
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
export function ajaxDeleteComponent(gradeable_id: string, component_id: number): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxVerifyComponent(gradeable_id: string, component_id: number, anon_id: string): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
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
export function ajaxVerifyAllComponents(gradeable_id: string, anon_id: string): Promise<void> {
    return new Promise((resolve, reject) => {
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
                    resolve();
                }
            },
            error: function (err) {
                displayAjaxError(err);
                reject(err);
            },
        });
    });
}
