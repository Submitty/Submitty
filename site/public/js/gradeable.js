
function renderGradeable(into, grading_data) {
    Twig.twig({
        id: "Gradeable",
        href: "templates/grading/Gradeable.twig",
        async: true,
        load: function(template) {
            into.append(template.render(grading_data));
        },
        error: function(e) {
            console.log(e);
        }
    });
}