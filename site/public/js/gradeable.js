/**
 * Asynchronously render a gradeable using the passed data
 * @param {Object} gradeable
 * @param {Object} graded_gradeable
 * @returns {Promise} Will fire when the template is loaded and rendered
 */
function renderGradingGradeable(gradeable, graded_gradeable) {
    return new Promise(function (accept, reject) {
        Twig.twig({
            id: "GradeGradeable",
            href: "templates/grading/GradeGradeable.twig",
            async: true,
            load: function (template) {
                accept(template.render({
                    'gradeable': gradeable,
                    'graded_gradeable': graded_gradeable,
                    'editable': false
                }));
            },
            error: reject
        });
        Twig.twig({
            id: "Gradeable",
            href: "templates/grading/Gradeable.twig",
            async: true,
        });
        Twig.twig({
            id: "GradeComponent",
            href: "templates/grading/GradeComponent.twig",
            async: true,
        });
        Twig.twig({
            id: "Component",
            href: "templates/grading/Component.twig",
            async: true
        });
        Twig.twig({
            id: "Mark",
            href: "templates/grading/Mark.twig",
            async: true
        });
    });
}

function renderInstructorEditGradeable(gradeable) {

}