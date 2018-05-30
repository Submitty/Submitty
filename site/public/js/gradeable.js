
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
}