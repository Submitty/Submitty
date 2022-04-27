import { COUNT_DIRECTION_DOWN, COUNT_DIRECTION_UP, CUSTOM_MARK_ID, getEditModeEnabled, NO_COMPONENT_ID, setEditModeEnabled } from './rubric-base';
import { onComponentOrderChange } from './rubric-dom-callback';
import { Component } from './types/Component';
import { GradedComponent } from './types/GradedComponent';
import { Mark } from './types/Mark';
import { MarkStats } from './types/MarkStats';
import { RubricTotal } from './types/RubricTotal';
import { Score } from './types/Score';

// TODO: This should be removed as code that uses these functions are moved
// to modules.
declare global {
    interface Window{
        getGradeableId(): string;
        getAnonId(): string;
        isSilentEditModeEnabled(): boolean;
        isItempoolAvailable(): boolean;
        getItempoolOptions(): string[];
        getComponentIdByOrder(order: number): number;
        getNextComponentId(component_id: number): number;
        getPrevComponentId(component_id: number): number;
        getFirstOpenComponentId(itempool_only: boolean): number;
        getMarkIdFromOrder(component_id: number, mark_order: number): number;
    }
}


/**
 * Gets the id of the open gradeable
 * @return {string}
 */
export function getGradeableId(): string {
    return $('#gradeable-rubric').attr('data-gradeable_id') as string;
}

/**
 * Gets the anon_id of the submitter being graded
 * @return {string}
 */
export function getAnonId(): string {
    return $('#anon-id').attr('data-anon_id') as string;
}

/**
 * Gets the id of the grader
 * @returns {string}
 */
export function getGraderId(): string {
    return $('#grader-info').attr('data-grader_id') as string;
}

/**
 * Used to determine if the interface displayed is for
 *  instructor edit mode (i.e. in the Edit Gradeable page)
 *  @return {boolean}
 */
export function isInstructorEditEnabled(): boolean {
    return $('#edit-gradeable-instructor-flag').length > 0;
}

/**
 * Used to determine if the 'verify grader' button should be displayed
 * @returns {boolean}
 */
export function canVerifyGraders(): boolean {
    return $('#grader-info').attr('data-can_verify') != '';
}

/**
 * Gets if grading is disabled since the selected version isn't the same
 *  as the one chosen for grading
 * @return {boolean}
 */
export function isGradingDisabled(): boolean {
    return $('#version-conflict-indicator').length > 0;
}

/**
 * Gets the gradeable version being disaplyed
 * @return {number}
 */
export function getDisplayVersion(): number {
    return parseInt($('#gradeable-version-container').attr('data-gradeable_version') as string);
}

/**
 * Gets the precision for component/mark point values
 * @returns {number}
 */
export function getPointPrecision(): number {
    return parseFloat($('#point_precision_id').val() as string);
}

export function getAllowCustomMarks(): string {
    return ($('#allow_custom_marks').attr('data-gradeable_custom_marks') as string);
}

/**
 * Used to determine if the mark list should be displayed in 'edit' mode
 *  @return {boolean}
 */
export function isEditModeEnabled(): boolean {
    return getEditModeEnabled() || isInstructorEditEnabled();
}

/**
 * Updates the edit mode state.  This is used to the mode
 * does not change before the components close
 */
export function updateEditModeEnabled(): void {
    // noinspection JSUndeclaredVariable
    setEditModeEnabled($('#edit-mode-enabled').is(':checked'));
}

/**
 * Gets if silent edit mode is enabled
 * @return {boolean}
 */
export function isSilentEditModeEnabled(): boolean {
    // noinspection JSValidateTypes
    return $('#silent-edit-id').is(':checked');
}

/**
 * Sets the DOM elements to render for the entire rubric
 * @param elements
 */
export function setRubricDOMElements(elements: string): void {
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
export function getComponentIdFromDOMElement(me: HTMLElement): number {
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
export function getMarkIdFromDOMElement(me: HTMLElement): number {
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
export function getComponentJQuery(component_id: number): JQuery {
    return $(`#component-${component_id}`);
}

/**
 * Gets the JQuery selector for the mark id
 * @param {number} mark_id
 * @return {jQuery}
 */
export function getMarkJQuery(mark_id: number): JQuery {
    return $(`#mark-${mark_id}`);
}

/**
 * Gets the JQuery selector for the component's custom mark
 * @param {number} component_id
 * @return {jQuery}
 */
export function getCustomMarkJQuery(component_id: number): JQuery {
    return getComponentJQuery(component_id).find('.custom-mark-container');
}

/**
 * Gets the JQuery selector for the overall comment container
 * @return {jQuery}
 */
export function getOverallCommentJQuery(): JQuery {
    return $('#overall-comment-container');
}

/**
 * Returns whether the current is of type notebook
 */
export function isItempoolAvailable(): boolean {
    return !!($('#gradeable_rubric.electronic_file').attr('data-itempool-available'));
}

/**
 * Returns the itempool options
 */
export function getItempoolOptions(): string[] {
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
 */
export function setComponentInProgress(component_id: number, show = true): void {
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
export function setupSortableMarks(component_id: number): void {
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
export function setupSortableComponents(): void {
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
export function keyPressHandler(e: JQuery.KeyDownEvent): void {
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
export function setComponentContents(component_id: number, contents: string): void {
    getComponentJQuery(component_id).parent('.component-container').html(contents);

    // Enable sorting for this component if in edit mode
    if (isEditModeEnabled()) {
        setupSortableMarks(component_id);
    }
}

/**
 * Sets the HTML contents of the specified component's header
 */
export function setComponentHeaderContents(component_id: number, contents: string): void {
    getComponentJQuery(component_id).find('.header-block').html(contents);
}

/**
 * Sets the HTML contents of the total scores box
 */
export function setTotalScoreBoxContents(contents: string): void {
    $('#total-score-container').html(contents);
}

/**
 * Sets the HTML contents of the rubric total box (instructor edit mode)
 */
export function setRubricTotalBoxContents(contents: string): void {
    $('#rubric-total-container').html(contents);
}

/**
 * Gets the count direction for a component in instructor edit mode
 * @returns {number} COUNT_DIRECTION_UP or COUNT_DIRECTION_DOWN
 */
export function getCountDirection(component_id: number): number {
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
export function setMarkTitle(mark_id: number, title: string): void {
    getMarkJQuery(mark_id).find('.mark-title textarea').val(title);
}

/**
 * Loads all components from the DOM
 */
export function getAllComponentsFromDOM(): Component[] {
    const components: Component[] = [];
    $('.component').each((index, element) => {
        components.push(getComponentFromDOM(getComponentIdFromDOMElement(element)));
    });
    return components;
}

/**
 * Gets the page number assigned to a component
 */
export function getComponentPageNumber(component_id: number): number {
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
export function getComponentFromDOM(component_id: number): Component {
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
export function getMarkListFromDOM(component_id: number): Mark[] {
    const domElement = getComponentJQuery(component_id);
    const markList: Mark[] = [];
    let i = 0;
    domElement.find('.ta-rubric-table .mark-container').each((index, element) => {
        const mark = getMarkFromDOM(parseInt($(element).attr('data-mark_id') as string));

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
export function getMarkFromDOM(mark_id: number): Mark | null {
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
export function componentExists(component_id: number): boolean {
    return getComponentJQuery(component_id).length > 0;
}

/**
 * Extracts a graded component object from the DOM
 * @param {number} component_id
 * @return {Object}
 */
export function getGradedComponentFromDOM(component_id: number): GradedComponent {
    const domElement = getComponentJQuery(component_id);
    const customMarkContainer = domElement.find('.custom-mark-container');

    // Get all of the marks that are 'selected'
    const mark_ids: number[] = [];
    let customMarkSelected = false;
    domElement.find('span.mark-selected').each((index, element) => {
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
export function getScoresFromDOM(): Score {
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
export function getRubricTotalFromDOM(): RubricTotal {
    let total = 0;
    let extra_credit = 0;
    getAllComponentsFromDOM().forEach((component: Component) => {
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
export function getTaGradingEarned(): number | undefined {
    let total = 0.0;
    let anyPoints = false;
    $('.graded-component-data').each((index, element) => {
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
export function getPeerGradingEarned(): number | undefined {
    let total = 0.0;
    let anyPoints = false;
    $('.peer-graded-component-data').each((index, element) => {
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
export function getTaGradingComplete(): boolean {
    let anyIncomplete = false;
    $('.graded-component-data').each((index, element) => {
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
export function getTaGradingTotal(): number {
    let total = 0.0;
    $('.ta-component').each((index, element) => {
        total += parseFloat($(element).attr('data-max_value') as string);
    });
    return total;
}
/**
 * Gets the number of Peer grading points that can be earned
 * @return {number}
 */
export function getPeerGradingTotal(): number {
    let total = 0.0;
    $('.peer-component').each((index, element) => {
        total += parseFloat($(element).attr('data-max_value') as string);
    });
    return total;
}

/**
 * Gets the ids of all open components
 */
export function getOpenComponentIds(itempool_only = false): number[] {
    const component_ids: number[] = [];
    if (itempool_only) {
        $('.ta-rubric-table:visible').each((index, element) => {
            const component = $(`#component-${$(element).attr('data-component_id')}`);
            if (component && component.attr('data-itempool_id')) {
                component_ids.push(parseInt($(element).attr('data-component_id') as string));
            }
        });
    }
    else {
        $('.ta-rubric-table:visible').each((index, element) => {
            component_ids.push(parseInt($(element).attr('data-component_id') as string));
        });
    }
    return component_ids;
}

/**
 * Gets the component id from its order on the page
 */
export function getComponentIdByOrder(order: number): number {
    return parseInt($('.component-container').eq(order).find('.component').attr('data-component_id') as string);
}

/**
 * Gets the orders of the components indexed by component id
 */
export function getComponentOrders(): { [key: number]: number } {
    const orders: { [key: number]: number } = {};
    $('.component').each((order, element) => {
        const id = getComponentIdFromDOMElement(element);
        orders[id] = order;
    });
    return orders;
}

/**
 * Gets the id of the next component in the list
 */
export function getNextComponentId(component_id: number): number {
    //TODO: confirm behavior when data-component_id not set (originally returns string)
    return parseInt(getComponentJQuery(component_id).parent('.component-container').next().children('.component').attr('data-component_id') as string);
}

/**
 * Gets the id of the previous component in the list
 */
export function getPrevComponentId(component_id: number): number {
    //TODO: confirm behavior when data-component_id not set (originally returns string)
    return parseInt(getComponentJQuery(component_id).parent('.component-container').prev().children('.component').attr('data-component_id') as string);
}

/**
 * Gets the first open component on the page
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
 */
export function getComponentCount(): number {
    return $('.component-container').length;
}

/**
 * Gets the mark id for a component and order
 * @returns {number} Mark id or 0 if out of bounds
 */
export function getMarkIdFromOrder(component_id: number, mark_order: number): number {
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
export function getOpenComponentIdFromCookie(): number {
    const component_id = parseInt(document.cookie.replace(/(?:(?:^|.*;\s*)open_component_id\s*=\s*([^;]*).*$)|^.*$/, '$1'));
    if (isNaN(component_id)) {
        return NO_COMPONENT_ID;
    }
    return component_id;
}

/**
 * Updates the open component in the cookie
 */
export function updateCookieComponent(): void {
    document.cookie = `open_component_id=${getFirstOpenComponentId()}; path=/;`;
}

/**
 * Gets the id of the no credit / full credit mark of a component
 */
export function getComponentFirstMarkId(component_id: number): number {
    return parseInt(getComponentJQuery(component_id).find('.mark-container').first().attr('data-mark_id') as string);
}

/**
 * Gets if a component is open
 */
export function isComponentOpen(component_id: number): boolean {
    return !getComponentJQuery(component_id).find('.ta-rubric-table').is(':hidden');
}

/**
 * Gets if a mark is 'checked'
 */
export function isMarkChecked(mark_id: number): boolean {
    return getMarkJQuery(mark_id).find('span.mark-selected').length > 0;
}

/**
 * Gets if a mark is disabled (shouldn't be checked
 */
export function isMarkDisabled(mark_id: number): boolean {
    return getMarkJQuery(mark_id).hasClass('mark-disabled');
}

/**
 * Gets if a mark was marked for deletion
 */
export function isMarkDeleted(mark_id: number): boolean {
    return getMarkJQuery(mark_id).hasClass('mark-deleted');
}

/**
 * Gets if the state of the custom mark is such that it should appear checked
 * Note: if the component is in edit mode, this will never return true
 */
export function hasCustomMark(component_id: number): boolean {
    if (isEditModeEnabled()) {
        return false;
    }
    const gradedComponent = getGradedComponentFromDOM(component_id);
    return gradedComponent.comment !== '';
}

/**
 * Gets if the custom mark on a component is 'checked'
 */
export function isCustomMarkChecked(component_id: number): boolean {
    return getCustomMarkJQuery(component_id).find('.mark-selected').length > 0;
}

/**
 * Checks the custom mark checkbox
 */
export function checkDOMCustomMark(component_id: number): void {
    getCustomMarkJQuery(component_id).find('.mark-selector').addClass('mark-selected');
}

/**
 * Un-checks the custom mark checkbox
 */
export function unCheckDOMCustomMark(component_id: number): void {
    getCustomMarkJQuery(component_id).find('.mark-selector').removeClass('mark-selected');
}

/**
 * Toggles the state of the custom mark checkbox in the DOM
 * @param {number} component_id
 */
export function toggleDOMCustomMark(component_id: number): void {
    getCustomMarkJQuery(component_id).find('.mark-selector').toggleClass('mark-selected');
}

/**
 * Opens the 'users who got mark' dialog
 * @param {string} component_title
 * @param {string} mark_title
 * @param {Object} stats
 */
export function openMarkStatsPopup(component_title: string, mark_title: string, stats: MarkStats): void {
    const popup = $('#student-marklist-popup');

    popup.find('.question-title').html(component_title);
    popup.find('.mark-title').html(mark_title);
    popup.find('.section-submitter-count').html(stats.section_submitter_count.toString());
    popup.find('.total-submitter-count').html(stats.total_submitter_count.toString());
    popup.find('.section-graded-component-count').html(stats.section_graded_component_count.toString());
    popup.find('.total-graded-component-count').html(stats.total_graded_component_count.toString());
    popup.find('.section-total-component-count').html(stats.section_total_component_count.toString());
    popup.find('.total-total-component-count').html(stats.total_total_component_count.toString());

    // Create an array of links for each submitter
    const submitterHtmlElements: string[] = [];
    const urlSplit = location.href.split('?');
    let base_url = urlSplit[0];
    if (base_url.slice(base_url.length - 6) == 'update') {
        base_url = `${base_url.slice(0, -6)}grading/grade`;
    }
    const search_params = new URLSearchParams(urlSplit[1]);
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
export function anyUnverifiedComponents(): boolean {
    return $('.verify-container').length > 0;
}

/**
 * Hides the verify all button if there are no components to verify
 */
export function updateVerifyAllButton(): void {
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
export function getComponentVersionConflict(graded_component: { graded_version: number; } | undefined): boolean {
    return graded_component !== undefined && graded_component.graded_version !== getDisplayVersion();
}

/**
 * Sets the error state of the custom mark message
 * @param {number} component_id
 * @param {boolean} show_error
 */
export function setCustomMarkError(component_id: number, show_error: boolean): void {
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
export function disableEditModeBox(disabled: boolean): void {
    $('#edit-mode-enabled').prop('disabled', disabled);
}

window.getAnonId = getAnonId;
window.isSilentEditModeEnabled = isSilentEditModeEnabled;
window.getGradeableId = getGradeableId;
window.getAnonId = getAnonId;
window.isSilentEditModeEnabled = isSilentEditModeEnabled;
window.isItempoolAvailable = isItempoolAvailable;
window.getItempoolOptions = getItempoolOptions;
window.getComponentIdByOrder = getComponentIdByOrder;
window.getNextComponentId = getNextComponentId;
window.getPrevComponentId = getPrevComponentId;
window.getFirstOpenComponentId = getFirstOpenComponentId;
window.getMarkIdFromOrder = getMarkIdFromOrder;
