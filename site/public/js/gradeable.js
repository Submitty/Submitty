/**
 * Asynchronously render a gradeable using the passed data
 * @param grading_data Gradeable data structure (see Gradeable.php/getGradedData())
 * @returns {Promise} Will fire when the template is loaded and rendered
 */
function renderGradingGradeable(gradeable, graded_gradeable) {
    return new Promise(function(accept, reject) {
        Twig.twig({
            id: "GradeGradeable",
            href: "templates/grading/GradeGradeable.twig",
            async: true,
            load: function (template) {
                accept(template.render({
                    'gradeable': gradeable,
                    'graded_gradeable': graded_gradeable
                }));
            },
            error: reject
        });
        Twig.twig({
            id: "GradeComponent",
            href: "templates/grading/GradeComponent.twig",
            async: true
        });
        Twig.twig({
            id: "GradeMark",
            href: "templates/grading/GradeMark.twig",
            async: true
        });
    });
}
