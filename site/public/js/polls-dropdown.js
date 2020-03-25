$(document).ready(function() {
    $(".dropdown-btn").on("click", function() {
    $(this).siblings("table").toggle();
    $(this).children("i").toggleClass("down right");
    });
});