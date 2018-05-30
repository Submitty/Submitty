var gradeableTemplate;

function renderGradeable(into, grading_data) {
    $.get("templates/grading/Gradeable.twig").done(function (data) {
        gradeableTemplate = Twig.twig({data: data});

        into.append(gradeableTemplate.render(grading_data))

    }).fail(function (data) {
        // Oh no
        alert("Cannot load templates");
    });
}