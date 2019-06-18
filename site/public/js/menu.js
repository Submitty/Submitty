$(document).ready(function() {
    function openMenu() {
        $("body").css("overflow", "hidden");
        window.scroll(0, 0);
        $("#menu-overlay").css("display", "block");
        $("#mobile-menu").css("display", "block");
    }
    
    function closeMenu() {
        $("body").css("overflow", "unset");
        $("#menu-overlay").css("display", "none");
        $("#mobile-menu").css("display", "none");
    }

    $("#menu-button").click(openMenu);
    $("#menu-exit").click(closeMenu);
    $("#menu-overlay").click(closeMenu);
}); 