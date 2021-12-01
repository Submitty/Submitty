$(function () {
    $(window).on('unload', function(e) {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({"type": "ta-grading-popup-removal", "data": $("body").attr("data-panel-type")}, "*");   
        }
    })
});