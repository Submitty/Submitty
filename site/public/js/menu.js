$(document).ready(function() {
    function openMenu() {
        $("body").css("overflow", "hidden");
        $(document).scrollTop(0);
        $(document).scrollLeft(0);
        $("#menu-overlay").addClass("mobile-block");
        $("#mobile-menu").addClass("mobile-block");
    }
    
    function closeMenu() {
        $("body").css("overflow", "unset");
        $("#menu-overlay").removeClass("mobile-block");
        $("#mobile-menu").removeClass("mobile-block");
    }

    $("#menu-button").click(openMenu);
    $("#menu-exit").click(closeMenu);
    $("#menu-overlay").click(closeMenu);
});  