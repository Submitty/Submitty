var siteUrl = undefined;
var csrfToken = undefined;

function setSiteDetails(url, setCsrfToken) {
    siteUrl = url;
    csrfToken = setCsrfToken;
}

/**
 * Acts in a similar fashion to Core->buildUrl() function within the PHP code
 * so that we do not have to pass in fully built URL to JS functions, but rather
 * construct them there as it makes sense (which helps on cutting down on potential
 * duplication of effort where we can replicate JS functions across multiple pages).
 *
 * @param {object} parts - Object representing URL parts to append to the URL
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
            if (json['user_preferred_firstname'] === null) {
                json['user_preferred_firstname'] = "";
            }
            $('[name="user_preferred_firstname"]', form).val(json['user_preferred_firstname']);
            $('[name="user_lastname"]', form).val(json['user_lastname']);
            $('[name="user_email"]', form).val(json['user_email']);
            var registration_section;
            if (json['registration_section'] === null) {
                registration_section = "null";
            }
            else {
                registration_section = json['registration_section'].toString();
            }
            var rotating_section;
            if (json['rotating_section'] === null) {
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
            if (json['grading_registration_sections'] !== null && json['grading_registration_sections'] !== undefined) {
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

function openDiv(id) {
    var elem = $('#' + id);
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $('#' + id + '-span').removeClass('icon-folder-open').addClass('icon-folder-closed');
    }
    else {
        elem.show();
        elem.addClass('open');
        $('#' + id + '-span').removeClass('icon-folder-closed').addClass('icon-folder-open');
    }
    return false;
}

function openUrl(url) {
    window.open(url, "_blank", "toolbar=no, scrollbars=yes, resizable=yes, width=700, height=600");
    return false;
}

function openFrame(url, id, filename) {
    var iframe = $('#file_viewer_' + id);
    if (!iframe.hasClass('open')) {
        var iframeId = "file_viewer_" + id + "_iframe";
        // handle pdf
        if(filename.substring(filename.length - 3) === "pdf") {
            iframe.html("<iframe id='" + iframeId + "' src='" + url + "' width='750px' height='600px' style='border: 0'></iframe>");
        }
        else {
            iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='" + url + "' width='750px' style='border: 0'></iframe>");
        }
        iframe.addClass('open');
    }

    if (!iframe.hasClass('shown')) {
        iframe.show();
        iframe.addClass('shown');
        $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-plus').addClass('icon-minus');
    }
    else {
        iframe.hide();
        iframe.removeClass('shown');
        $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-minus').addClass('icon-plus');
    }
    return false;
}

function resizeFrame(id) {
    var height = parseInt($("iframe#" + id).contents().find("body").css('height').slice(0,-2));
    if (height > 500) {
        document.getElementById(id).height= "500px";
    }
    else {
        document.getElementById(id).height = (height+18) + "px";
    }
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

function updateCheckpointCell(elem, setFull) {
    elem = $(elem);
    if (!setFull && elem.data("score") === 1.0) {
        elem.data("score", 0.5);
        elem.css("background-color", "#88d0f4");
        elem.css("border-right", "15px solid #f9f9f9");
    }
    else if (!setFull && elem.data("score") === 0.5) {
        elem.data("score", 0);
        elem.css("background-color", "");
        elem.css("border-right", "15px solid #ddd");
    }
    else {
        elem.data("score", 1);
        elem.css("background-color", "#149bdf");
        elem.css("border-right", "15px solid #f9f9f9");
    }
}

function submitAJAX(url, data, callbackSuccess, callbackFailure) {
    $.ajax(url, {
        type: "POST",
        data: data
    })
    .done(function(response) {
        try{
            response = JSON.parse(response);
            if (response['status'] === 'success') {
                callbackSuccess();
            }
            else {
                console.log(response['message']);
                callbackFailure();
                if (response['status'] === 'error') {
                    window.alert("[SAVE ERROR] Refresh Page");
                }

            }
        }
        catch (e) {
            console.log(response);
            callbackFailure();
            window.alert("[SAVE ERROR] Refresh Page");
        }
    })
    .fail(function() {
        window.alert("[SAVE ERROR] Refresh Page");
    });
}

function setupCheckboxCells() {
    $("td[class^=cell-]").click(function() {
        var parent = $(this).parent();
        var elems = [];
        if ($(this).hasClass('cell-all')) {
            var lastScore = null;
            var setFull = false;
            parent.children(".cell-grade").each(function() {
                if (lastScore === null) {
                    lastScore = $(this).data("score");
                }
                else if (lastScore !== $(this).data("score")) {
                    setFull = true;
                }
            });
            parent.children(".cell-grade").each(function() {
                updateCheckpointCell(this, setFull);
                elems.push(this);
            });
        }
        else {
            updateCheckpointCell(this);
            elems.push(this);
        }
        var scores = {};
        parent.children("td.cell-grade").each(function() {
            scores[$(this).data("id")] = $(this).data("score");
        });

        submitAJAX(
            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_lab'}),
            {'csrf_token': csrfToken, 'user_id': parent.data("user"), 'g_id': parent.data('gradeable'), 'scores': scores},
            function() {
                elems.forEach(function(elem) {
                    $(elem).animate({"border-right-width": "0px"}, 400);
                });
            },
            function() {
                elems.forEach(function(elem) {
                    console.log(elem);
                    $(elem).css("border-right-width", "15px");
                    $(elem).stop(true, true).animate({"border-right-color": "#DA4F49"}, 400);
                });
            }
        );
    });
}

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

    setupCheckboxCells();
});

function setupNumericTextCells() {
    $("input[class=option-small-box]").keydown(function(key){
        var cell=this.id.split('-');
        if(key.keyCode === 39){
            if(this.selectionEnd == this.value.length){
                $('#cell-'+cell[1]+'-'+(++cell[2])).focus();
            }
        }
        else if(key.keyCode == 37){
            if(this.selectionStart == 0){
                $('#cell-'+cell[1]+'-'+(--cell[2])).focus();
            }
        }
        else if(key.keyCode == 38){
            $('#cell-'+(--cell[1])+'-'+cell[2]).focus();

        }
        else if(key.keyCode == 40){
            $('#cell-'+(++cell[1])+'-'+cell[2]).focus();
        }
    });

    $("input[class=option-small-box]").change(function() {
        elem = this;
        if(this.value == 0){
            $(this).css("color", "#bbbbbb");
        }
        else{
            $(this).css("color", "");
        }
        var scores = {};
        var total = 0;
        $(this).parent().parent().children("td.option-small-input, td.option-small-output").each(function() {
            $(this).children(".option-small-box").each(function(){
                if($(this).data('num') === true){
                    total += parseFloat(this.value);
                }
                if($(this).data('total') === true){
                    this.value = total;
                }
                else{
                    scores[$(this).data("id")] = this.value;
                }
            });
        });

        submitAJAX(
            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_numeric'}),
            {'csrf_token': csrfToken, 'user_id': $(this).parent().parent().data("user"), 'g_id': $(this).parent().parent().data('gradeable'), 'scores': scores},
            function() {
                $(elem).css("background-color", "transparent");
            },
            function() {
                $(elem).css("background-color", "#ff7777");
            }
        );
    });
}

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

    setupNumericTextCells();
});