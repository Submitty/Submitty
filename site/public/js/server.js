var siteUrl = undefined;

function setSiteUrl(url) {
    siteUrl = url
}

/**
 * Acts in a similar fashion to Core->buildUrl() function within the PHP code
 * so that we do not have to pass in fully built URL to JS functions, but rather
 * construct them there as it makes sense (which helps on cutting down on potential
 * duplication of effort where we can replicate JS functions across multiple pages).
 *
 * @param parts - Object representing URL parts to append to the URL
 * @returns {string} - Built up URL to use
 */
function buildUrl(parts) {
    var url = siteUrl;
    var constructed = "";
    for (var part in parts) {
        if (parts.hasOwnProperty(part)) {
            constructed += "&" + part + "=" + parts[part];
        }
    }
    return url + constructed;
}

/**
 *
 */
function editUserForm(user_id) {
    var url = buildUrl({'component': 'admin', 'page': 'users', 'action': 'get_user_details', 'user_id': user_id});
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data);
            var form = $("#edit-user-form");
            form.css("display", "block");
            $('[name="edit_user"]', form).val("true");
            var user = $('[name="user_id"]', form);
            user.val(json['user_id']);
            user.attr('readonly', 'readonly');
            if (!user.hasClass('readonly')) {
                user.addClass('readonly');
            }
            $('[name="user_firstname"]', form).val(json['user_firstname']);
            if (json['user_preferred_firstname'] == null) {
                json['user_preferred_firstname'] = "";
            }
            $('[name="user_preferred_firstname"]', form).val(json['user_preferred_firstname']);
            $('[name="user_lastname"]', form).val(json['user_lastname']);
            $('[name="user_email"]', form).val(json['user_email']);
            var registration_section;
            if (json['registration_section'] == null) {
                registration_section = "null";
            }
            else {
                registration_section = json['registration_section'].toString();
            }
            var rotating_section;
            if (json['rotating_section'] == null) {
                rotating_section = "null";
            }
            else {
                rotating_section = json['rotating_section'].toString();
            }
            $('[name="registered_section"] option[value="' + registration_section + '"]', form).prop('selected', true);
            $('[name="rotating_section"] option[value="' + rotating_section + '"]', form).prop('selected', true);
            $('[name="manual_registration"]', form).prop('checked', json['manual_registration']);
            $('[name="user_group"] option[value="' + json['user_group'] + '"]', form).prop('selected', true);
            $("[name='grading_registration_section[]']").prop('checked', false);
            if (json['grading_registration_sections'] != null && json['grading_registration_sections'] != undefined) {
                json['grading_registration_sections'].forEach(function(val) {
                    $('#grs_' + val).prop('checked', true);
                });
            }

        },
        error: function() {
            alert("Could not load user data, please refresh the page and try again.");
        }
    })
}

function newUserForm() {
    var form = $("#edit-user-form");
    form.css("display", "block");
    $('[name="edit_user"]', form).val("false");
    $('[name="user_id"]', form).removeClass('readonly').removeAttr('readonly').val("");
    $('[name="user_firstname"]', form).val("");
    $('[name="user_preferred_firstname"]', form).val("");
    $('[name="user_lastname"]', form).val("");
    $('[name="user_email"]', form).val("");
    $('[name="registered_section"] option[value="null"]', form).prop('selected', true);
    $('[name="rotating_section"] option[value="null"]', form).prop('selected', true);
    $('[name="manual_registration"]', form).prop('checked', true);
    $('[name="user_group"] option[value="4"]', form).prop('selected', true);
    $("[name='grading_registration_section[]']").prop('checked', false);
}

/**
 * Toggles the page details box of the page, showing or not showing various information
 * such as number of queries run, length of time for script execution, and other details
 * useful for developers, but shouldn't be shown to normal users
 */
function togglePageDetails() {
    if (document.getElementById('page-info').style.visibility == 'visible') {
        document.getElementById('page-info').style.visibility = 'hidden';
    }
    else {
        document.getElementById('page-info').style.visibility = 'visible';
    }
}

/**
 * Remove an alert message from display. This works for successes, warnings, or errors to the
 * user
 * @param elem
 */
function removeMessagePopup(elem) {
    $('#' + elem).fadeOut('slow', function() {
        $('#' + elem).remove();
    });
}

function gradeableChange(url, sel){
    url = url + sel.value;
    window.location.href = url;
}
function versionChange(url, sel){
    url = url + sel.value;
    window.location.href = url;
}

function checkVersionChange(days_late, late_days_allowed){
    if(days_late > late_days_allowed){
        var message = "The max late days allowed for this assignment is " + late_days_allowed + " days. ";
        message += "You are not supposed to change your active version after this time unless you have permission from the instructor. Are you sure you want to continue?";
        return confirm(message);
    }
    return true;
}

function checkVersionsUsed(gradeable, versions_used, versions_allowed) {
    versions_used = parseInt(versions_used);
    versions_allowed = parseInt(versions_allowed);
    if (versions_used >= versions_allowed) {
        return confirm("Are you sure you want to upload for " + gradeable + "? You have already used up all of your free submissions (" + versions_used + " / " + versions_allowed + "). Uploading may result in loss of points.");
    }
    return true;
}

function toggleDiv(id) {
    $("#" + id).toggle();
    return true;
}


function checkRefreshSubmissionPage(url) {
    setTimeout(function() {
        check_server(url)
    }, 1000);
}

function check_server(url) {
    $.post(url,
        function(data) {
            if (data.indexOf("REFRESH_ME") > -1) {
                location.reload(true);
            } else {
                checkRefreshSubmissionPage(url);
            }
        }
    );
}

function batchImportJSON(url, csrf_token){
    $.ajax(url, {
        type: "POST",
        data: {
            csrf_token: csrf_token
        }
    })
    .done(function(response) {
        window.alert(response);
        location.reload(true);
    })
    .fail(function() {
        window.alert("[AJAX ERROR] Refresh page");
    });
}

/*
var hasNav = false;

function UpdateTableHeaders() {
    var count = 0;
    var scrollTop = parseInt($(window).scrollTop());
    $(".persist-area").each(function() {
        var el = $(".persist-thead", this);
        var height = parseFloat(el.height());
        var offset = parseFloat(el.offset().top);
        var floatingHeader = $(".floating-thead", this);
        if (scrollTop > (offset - height)) {
            if (floatingHeader.css("visibility") != "visible") {
                var cnt = 0;
                $("#floating-thead-0>td").each(function() {
                    $(this).css("width", $($("#anchor-thead").children()[cnt]).width());
                    cnt++;
                });
                floatingHeader.css("visibility", "visible");
            }
        }
        else {
            floatingHeader.css("visibility", "hidden");
        }
        $(".persist-header", this).each(function() {
            floatingHeader = $("#floating-header-" + count);
            el = $(this);
            height = parseFloat(el.height());
            offset = parseFloat(el.offset().top);
            if (scrollTop > (offset - height)) {
                if (floatingHeader.css("visibility") != "visible") {
                    floatingHeader.css("visibility", "visible");
                    var cnt = 0;
                    $("#floating-header-" + count + ">td").each(function() {
                        $(this).css("width", $($("#anchor-head-" + count).children()[cnt]).width());
                        cnt++;
                    });
                }
            }
            else {
                floatingHeader.css("visibility", "hidden");
            }
            count++;
        });
    });
}

$(function() {
    //hasNav = $("#nav").length > 0;
    hasNav = false; // nav doesn't float anymore so we don't have to account for it.
    // Each persist-area can have multiple persist-headers, we need to create each one with a new z-index
    var persist = $(".persist-area");
    var z_index = 900;
    var count = 0;
    persist.each(function() {
        var el = $(".persist-thead>tr", this);
        el.attr('id', 'anchor-thead');

        el.before(el.clone()).css({"width": el.width(), "top": "30px", "z-index": "899"}).addClass('floating-thead')
            .attr('id', 'floating-thead-' + count);
        $(".floating-thead", this).each(function() {
           $(this).children().removeAttr('width');
        });
        $(".persist-header", this).each(function() {
            $(this).attr('id', 'anchor-head-' + count);
            var clone = $(this);
            clone.before(clone.clone()).css({
                "width": clone.width(),
                "top": (30 + el.height()) + "px",
                "z-index": "" + z_index
            }).addClass("floating-header").removeClass("persist-header").attr('id', 'floating-header-' + count);
            z_index++;
            count++;
        });
    });

    if (persist.length > 0) {
        $(window).scroll(UpdateTableHeaders).trigger("scroll");
    }

    if (window.location.hash != "") {
        if ($(window.location.hash).offset().top > 0) {
            var minus = 60;
            if (hasNav) {
                minus += 30;
            }
            $("html, body").animate({scrollTop: ($(window.location.hash).offset().top - minus)}, 800);
        }
    }

    setTimeout(function() {
        $('.inner-message').fadeOut();
    }, 5000);
});
*/

$(function() {
    if (window.location.hash !== "") {
        if ($(window.location.hash).offset().top > 0) {
            var minus = 60;
            $("html, body").animate({scrollTop: ($(window.location.hash).offset().top - minus)}, 800);
        }
    }

    setTimeout(function() {
        $('.inner-message').fadeOut();
    }, 5000);
});