/**
 * Asynchronously load all of the templates
 * @return {Promise}
 */
function loadTemplates() {
    let templates = [
        {id: 'GradingGradeable', href: 'templates/grading/GradingGradeable.twig'},
        {id: 'Gradeable', href: "templates/grading/Gradeable.twig"},
        {id: 'GradingComponent', href: "templates/grading/GradingComponent.twig"},
        {id: 'Component', href: "templates/grading/Component.twig"},
        {id: 'Mark', href: "templates/grading/Mark.twig"},
        {id: 'OverallComment', href: "templates/grading/OverallComment.twig"}
    ];
    let promises = [];
    templates.forEach(function (template) {
        promises.push(new Promise(function (resolve, reject) {
            Twig.twig({
                id: template.id,
                href: template.href,
                allowInlineIncludes: true,
                async: true,
                error: function () {
                    reject();
                },
                load: function () {
                    resolve();
                }
            });
        }));
    });
    return Promise.all(promises);
}

/**
 * Calculates the score of a graded component
 * @param {Object} component
 * @param {Object} graded_component
 * @return {number}
 */
function calculateGradedComponentTotalScore(component, graded_component) {
    // If the mark selected isn't defined, then assume its true
    if (graded_component.custom_mark_selected === undefined) {
        graded_component.custom_mark_selected = true;
    }

    // Calculate the total
    let total = component.default + (graded_component.custom_mark_selected ? graded_component.score : 0.0);
    component.marks.forEach(function (mark) {
        if (graded_component.mark_ids.includes(mark.id)) {
            total += mark.points;
        }
    });

    // Then clamp it in range
    total = Math.min(component.upper_clamp, Math.max(total, component.lower_clamp));
    return total;
}

/**
 * Asynchronously render a gradeable using the passed data
 * @param {Object} gradeable
 * @param {Object} graded_gradeable
 * @returns {Promise}
 */
function renderGradingGradeable(gradeable, graded_gradeable) {
    return loadTemplates()
        .then(function () {
            // Calculate the total scores
            for(let i = 0; i < gradeable.components.length; i++) {
                graded_gradeable.graded_components[gradeable.components[i].id].total_score = calculateGradedComponentTotalScore(
                    gradeable.components[i],
                    graded_gradeable.graded_components[gradeable.components[i].id]
                );
            }
            // TODO: i don't think this is async
            return Twig.twig({ref: "GradingGradeable"}).render({
                'gradeable': gradeable,
                'graded_gradeable': graded_gradeable,
                'editable': false,
                'grading_disabled': false // TODO:
            });
        });
}

/**
 * Asynchronously render a component using the passed data
 * @param {Object} component
 * @param {Object} graded_component
 * @param {boolean} editable True to render with edit mode enabled
 * @param {boolean} showMarkList True to display the mark list unhidden
 * @returns {Promise<string>} the html for the graded component
 */
function renderGradingComponent(component, graded_component, editable, showMarkList) {
    return new Promise(function (resolve, reject) {
        // Make sure this is calculated so the badge can be displayed properly
        graded_component.total_score = calculateGradedComponentTotalScore(component, graded_component);

        // TODO: i don't think this is async
        resolve(Twig.twig({ref: "GradingComponent"}).render({
            'component': component,
            'graded_component': graded_component,
            'editable': editable,
            'show_mark_list': showMarkList
        }));
    });
}

function renderInstructorEditGradeable(gradeable) {

}

/**
 * Asynchronously renders the overall component using passed data
 * @param {string} comment
 * @param {boolean} editable If the comment should be open for edits, or closed
 * @return {Promise<string>}
 */
function renderOverallComment(comment, editable) {
    return new Promise(function (resolve, reject) {
        // TODO: i don't think this is async
        resolve(Twig.twig({ref: "OverallComment"}).render({
            'overall_comment': comment,
            'editable': editable,
            'disabled': false
        }));
    });
}