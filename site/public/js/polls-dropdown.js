$(document).ready(function() {
    $(".dropdown_bar").on("click", function() {
    $(this).siblings("table").toggle();
    $(this).find("i").toggleClass("down right");
    });
});
