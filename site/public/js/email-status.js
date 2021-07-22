function loadPage(page, load_page_url) {
    $.ajax({
        type: "GET",
        url: load_page_url,
        data: {
            'csrf_token': csrf_token,
            'page': page
        },
        success: function() {

        },
        error: function() {

        },
    });
}