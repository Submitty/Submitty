$(document).ready(() => {
    $('.dropdown-bar').on('click', function() {
        $(this).siblings('table').toggle();
        $(this).find('i').toggleClass('down right');
    });
});
