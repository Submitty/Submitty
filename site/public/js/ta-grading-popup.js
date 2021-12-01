$(function () {
    $(window).on('unload', function(e) {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({"type": "ta-grading-popup-removal", "data": {"url": window.location.href, "panel": $("body").attr("data-panel-type")}}, "*");   
        }
    })
});