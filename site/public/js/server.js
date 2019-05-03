var siteUrl = undefined;
var csrfToken = undefined;

window.addEventListener("load", function() {
  for (const elem in document.body.dataset) {
    window[elem] = document.body.dataset[elem];
  }
});

window.addEventListener("resize", checkSidebarCollapse); 

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
    var constructed = "";
    for (var part in parts) {
        if (parts.hasOwnProperty(part)) {
            constructed += "&" + part + "=" + parts[part];
        }
    }
    return document.body.dataset.siteUrl + constructed;
}

function changeDiffView(div_name, gradeable_id, who_id, version, index, autocheck_cnt, helper_id){
    var actual_div_name = "#" + div_name + "_0";
    var expected_div_name = "#" + div_name + "_1";
    var actual_div = $(actual_div_name).children()[0];
    var expected_div = $(expected_div_name).children()[0];
    var args = {'component': 'grading', 'page': 'electronic', 'action': 'remove_empty'
        ,'gradeable_id': gradeable_id, 'who_id' : who_id, 'version': version, 'index' : index, 'autocheck_cnt': autocheck_cnt};
    var list_white_spaces = {};
    $('#'+helper_id).empty();
    if($("#show_char_"+index+"_"+autocheck_cnt).text() == "Visualize whitespace characters"){
        $("#show_char_"+index+"_"+autocheck_cnt).removeClass('btn-default');
        $("#show_char_"+index+"_"+autocheck_cnt).addClass('btn-primary');
        $("#show_char_"+index+"_"+autocheck_cnt).html("Display whitespace/non-printing characters as escape sequences");
        list_white_spaces['newline'] = '&#9166;';
        args['option'] = 'unicode'
    } else if($("#show_char_"+index+"_"+autocheck_cnt).text() == "Display whitespace/non-printing characters as escape sequences") {
        $("#show_char_"+index+"_"+autocheck_cnt).html("Original View");
        list_white_spaces['newline'] = '\\n';
        args['option'] = 'escape'
    } else {
        $("#show_char_"+index+"_"+autocheck_cnt).removeClass('btn-primary');
        $("#show_char_"+index+"_"+autocheck_cnt).addClass('btn-default');
        $("#show_char_"+index+"_"+autocheck_cnt).html("Visualize whitespace characters");
        args['option'] = 'original'
    }
    //Insert actual and expected one at a time
    args['which'] = 'expected';
    var url = buildUrl(args);

    let assertSuccess = function(data) {
        if (data.status === 'fail') {
            alert("Error loading diff: " + data.message);
            return false;
        } else if (data.status === 'error') {
            alert("Internal server error: " + data.message);
            return false;
        }
        return true;
    }

    $.getJSON({
        url: url,
        success: function (response) {
            if(!assertSuccess(response)) {
                return;
            }
            for (property in response.data.whitespaces) {
                list_white_spaces[property] = response.data.whitespaces[property];
            }
            $(expected_div).empty();
            $(expected_div).html(response.data.html);
            args['which'] = 'actual';
            url = buildUrl(args);
            $.getJSON({
                url: url,
                success: function (response) {
                    if(!assertSuccess(response)) {
                        return;
                    }
                    for (property in response.data.whitespaces) {
                        list_white_spaces[property] = response.data.whitespaces[property];
                    }
                    for (property in list_white_spaces) {
                        $('#' + helper_id).append('<span style=\"outline:1px blue solid;\">' + list_white_spaces[property] + "</span> = " + property + " ");
                    }
                    $(actual_div).empty();
                    $(actual_div).html(response.data.html);
                },
                error: function (e) {
                    alert("Could not load diff, please refresh the page and try again.");
                }
            });
        },
        error: function (e) {
            alert("Could not load diff, please refresh the page and try again.");
        }
    });

}

function loadTestcaseOutput(div_name, gradeable_id, who_id, index, version = ''){
    let orig_div_name = div_name;
    div_name = "#" + div_name;

    let loadingTools = $("#tc_" + index).find(".loading-tools");

    if($(div_name).is(":visible")){
        $("#show_char_"+index).toggle();
        $(div_name).empty();
        toggleDiv(orig_div_name);

        loadingTools.find("span").hide();
        loadingTools.find(".loading-tools-show").show();
    }else{
        $("#show_char_"+index).toggle();
        var url = buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'load_student_file',
            'gradeable_id': gradeable_id, 'who_id' : who_id, 'index' : index, 'version' : version});

        loadingTools.find("span").hide();
        loadingTools.find(".loading-tools-in-progress").show();
        $.getJSON({
            url: url,
            success: function(response) {
                if (response.status !== 'success') {
                    alert('Error getting file diff: ' + response.message);
                    return;
                }
                $(div_name).empty();
                $(div_name).html(response.data);
                toggleDiv(orig_div_name);

                loadingTools.find("span").hide();
                loadingTools.find(".loading-tools-hide").show();
            },
            error: function(e) {
                alert("Could not load diff, please refresh the page and try again.");
            }
        })
    }
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
            if (json['user_preferred_lastname'] === null) {
                json['user_preferred_lastname'] = "";
            }
            $('[name="user_preferred_lastname"]', form).val(json['user_preferred_lastname']);
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
            if(registration_section === 'null' && json['user_group'] === 4) {
                $('#user-form-student-error-message').css('display', 'block');
            }
            else {
                $('#user-form-student-error-message').css('display', 'none');
            }
            if(json['user_group'] == 4) {
                $('#user-form-assigned-sections').css('display', 'none');
            }
            else {
                $('#user-form-assigned-sections').css('display', 'block');
            }

        },
        error: function() {
            alert("Could not load user data, please refresh the page and try again.");
        }
    })
}

function newUserForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-user-form");
    form.css("display", "block");
    $('[name="edit_user"]', form).val("false");
    $('[name="user_id"]', form).removeClass('readonly').prop('readonly', false).val("");
    $('[name="user_firstname"]', form).val("");
    $('[name="user_preferred_firstname"]', form).val("");
    $('[name="user_lastname"]', form).val("");
    $('[name="user_email"]', form).val("");
    $('[name="registered_section"] option[value="null"]', form).prop('selected', true);
    $('[name="rotating_section"] option[value="null"]', form).prop('selected', true);
    $('[name="manual_registration"]', form).prop('checked', true);
    $('[name="user_group"] option[value="4"]', form).prop('selected', true);
    $("[name='grading_registration_section[]']").prop('checked', false);
    $('#user-form-student-error-message').css('display', 'block');
    $('#user-form-assigned-sections').css('display', 'none');
}

function extensionPopup(json){
    $('.popup-form').css('display', 'none');
    var form = $('#more_extension_popup');
    form[0].outerHTML = json['popup'];
    $('#more_extension_popup').css('display', 'block');
}

function newDownloadForm() {
    $('.popup-form').css('display', 'none');
    var form = $('#download-form');
    form.css('display', 'block');
    $("#download-form input:checkbox").each(function() {
        if ($(this).val() === 'NULL') {
            $(this).prop('checked', false);
        } else {
            $(this).prop('checked', true);
        }
    });
}

function newGraderListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#grader-list-form");
    form.css("display", "block");
    $('[name="upload"]', form).val(null);
}

function newClassListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#class-list-form");
    form.css("display", "block");
    $('[name="move_missing"]', form).prop('checked', false);
    $('[name="upload"]', form).val(null);
}

function newDeleteGradeableForm(form_action, gradeable_name) {
    $('.popup-form').css('display', 'none');
    var form = $("#delete-gradeable-form");
    $('[name="delete-gradeable-message"]', form).html('');
    $('[name="delete-gradeable-message"]', form).append('<b>'+gradeable_name+'</b>');
    $('[name="delete-confirmation"]', form).attr('action', form_action);
    form.css("display", "block");
}

function newDeleteCourseMaterialForm(form_action, file_name) {
    var current_y_offset = window.pageYOffset;
    document.cookie = 'jumpToScrollPostion='+current_y_offset;

    $('[id^=div_viewer_]').each(function() {
        var number = this.id.replace('div_viewer_', '').trim();

        var elem = $('#div_viewer_' + number);
        if (elem.hasClass('open')) {
            document.cookie = "cm_" +number+ "=1;";
        }
        else {
            document.cookie = "cm_" +number+ "=0;";
        }
    });


    $('.popup-form').css('display', 'none');
    var form = $("#delete-course-material-form");
    $('[name="delete-course-material-message"]', form).html('');
    $('[name="delete-course-material-message"]', form).append('<b>'+file_name+'</b>');
    $('[name="delete-confirmation"]', form).attr('action', form_action);
    form.css("display", "block");
}

function newUploadImagesForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#upload-images-form");
    form.css("display", "block");
    $('[name="upload"]', form).val(null);
}

function confirmExtension(option){
    $('.popup-form').css('display', 'none');
    $('input[name="option"]').val(option);
    $('#excusedAbsenceForm').submit();
    $('input[name="option"]').val(-1);
}

function userNameChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-username-form");
    form.css("display", "block");
    $('[name="user_name_change"]', form).val("");
}

function passwordChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#change-password-form");
    form.css("display", "block");
    $('[name="new_password"]', form).val("");
    $('[name="confirm_new_password"]', form).val("");
}

function closePopup(popup) {
    //See if we have a close button that lets us click to close
    var closer = $(popup).find(".close-button");
    if (closer.length) {
        closer.click();
    }
}

function newUploadCourseMaterialsForm() {

    createArray(1);

    var fileList = document.getElementsByClassName("file-viewer-data");

    var files = [];
    for(var i=0;i<fileList.length;i++){
        var file = fileList[i];
        files.push(file.getAttribute('data-file_url'));
        readPrevious(file.getAttribute('data-file_url'), 1);
    }

    $('.popup-form').css('display', 'none');
    var form = $("#upload-course-materials-form");

    $('[name="existing-file-list"]', form).html('');
    $('[name="existing-file-list"]', form).append('<b>'+JSON.stringify(files)+'</b>');

    form.css("display", "block");
    $('[name="upload"]', form).val(null);

}

function deletePlagiarismResultAndConfigForm(form_action, gradeable_title) {
    $('.popup-form').css('display', 'none');
    var form = $("#delete-plagiarism-result-and-config-form");
    $('[name="gradeable_title"]', form).html('');
    $('[name="gradeable_title"]', form).append(gradeable_title);
    $('[name="delete"]', form).attr('action', form_action);
    form.css("display", "block");
}

function addMorePriorTermGradeable(prior_term_gradeables) {
    var form = $("#save-configuration-form");
    var prior_term_gradeables_number = $('[name="prior_term_gradeables_number"]', form).val();
    var to_append = '<br /><select name="prev_sem_'+ prior_term_gradeables_number +'"><option value="">None</option>';
    $.each(prior_term_gradeables, function(sem,courses_gradeables){
        to_append += '<option value="'+ sem +'">'+ sem +'</option>';
    });
    to_append += '</select><select name="prev_course_'+ prior_term_gradeables_number +'"><option value="">None</option></select><select name="prev_gradeable_'+ prior_term_gradeables_number +'"><option value="">None</option></select>';
    $('[name="prev_gradeable_div"]', form).append(to_append);
    $('[name="prior_term_gradeables_number"]', form).val(parseInt(prior_term_gradeables_number)+1);
    $("select", form).change(function(){
        var select_element_name = $(this).attr("name");
        PlagiarismConfigurationFormOptionChanged(prior_term_gradeables, select_element_name);
    });
}

function setUserSubmittedCode(gradeable_id, changed) {
    var form = $("#users_with_plagiarism");
    var user_id_1 = $('[name="user_id_1"]', form).val();
    if(user_id_1 == ""){
        $('[name="version_user_1"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        $('[name="user_id_2"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        $('[name="code_box_1"]').empty();
        $('[name="code_box_2"]').empty();
    }
    else {
        var version_user_1 = $('[name="version_user_1"]', form).val();
        if(changed == 'version_user_1' && version_user_1 == '') {
            $('[name="user_id_2"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
            $('[name="code_box_1"]').empty();
            $('[name="code_box_2"]').empty();
        }
        else {
            if(changed == 'user_id_1' || changed =='version_user_1') {
                if( version_user_1 == '' || changed == 'user_id_1') {
                    version_user_1 = "max_matching";
                }

                var url = buildUrl({'component': 'admin', 'page': 'plagiarism', 'action': 'get_submission_concatenated',
                        'gradeable_id': gradeable_id , 'user_id_1':user_id_1, 'version_user_1': version_user_1});
                $.ajax({
                    url: url,
                    success: function(data) {

                        data = JSON.parse(data);
                        console.log(data.ci);

                        if(data.error){
                            alert(data.error);
                            return;
                        }
                        var append_options='<option value="">None</option>';
                        $.each(data.all_versions_user_1, function(i,version_to_append){
                            if(version_to_append == data.active_version_user_1 && version_to_append == data.max_matching_version){
                                append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)(Max Match)</option>';
                            }
                            if(version_to_append == data.active_version_user_1 && version_to_append != data.max_matching_version){
                                append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Active)</option>';
                            }
                            if(version_to_append != data.active_version_user_1 && version_to_append == data.max_matching_version){
                                append_options += '<option value="'+ version_to_append +'">'+ version_to_append +' (Max Match)</option>';
                            }

                            if(version_to_append != data.active_version_user_1 && version_to_append != data.max_matching_version){
                                append_options += '<option value="'+ version_to_append +'">'+ version_to_append +'</option>';
                            }
                        });
                        $('[name="version_user_1"]', form).find('option').remove().end().append(append_options).val(data.code_version_user_1);

                        $('.CodeMirror')[0].CodeMirror.getDoc().setValue(data.display_code1);
                        for(var users_color in data.ci) {
                            //console.log(data.ci[users_color]);
                            for(var pos in data.ci[users_color]) {
                                var element = data.ci[users_color][pos];
                                $('.CodeMirror')[users_color-1].CodeMirror.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_prev_color": element[4], "data_start": element[7], "data_end": element[8]}, css: "border: 1px solid black; background: " + element[4]});   
                            }
                        }
                        $('.CodeMirror')[0].CodeMirror.refresh();
                        //$('[name="code_box_1"]').empty().append(data.display_code1);
                    },
                    error: function(e) {
                        alert("Could not load submitted code, please refresh the page and try again.");
                    }
                })

                var url = buildUrl({'component': 'admin', 'page': 'plagiarism', 'action': 'get_matching_users',
                        'gradeable_id': gradeable_id , 'user_id_1':user_id_1, 'version_user_1': version_user_1});
                $.ajax({
                    url: url,
                    success: function(data) {
                        if(data == "no_match_for_this_version") {
                            var append_options='<option value="">None</option>';
                            $('[name="code_box_2"]').empty();
                        }
                        else {
                            data = JSON.parse(data);
                            if(data.error){
                                alert(data.error);
                                return;
                            }
                            var append_options='<option value="">None</option>';
                            $.each(data, function(i,matching_users){
                                append_options += '<option value="{&#34;user_id&#34;:&#34;'+ matching_users[0]+'&#34;,&#34;version&#34;:'+ matching_users[1] +'}">'+ matching_users[2]+ ' '+matching_users[3]+' &lt;'+matching_users[0]+'&gt; (version:'+matching_users[1]+')</option>';
                            });
                        }
                        $('[name="user_id_2"]', form).find('option').remove().end().append(append_options).val('');
                    },
                    error: function(e) {
                        alert("Could not load submitted code, please refresh the page and try again.");
                    }
                })
                $('[name="code_box_2"]').empty();
            }
            if (changed == 'user_id_2') {
                if (($('[name="user_id_2"]', form).val()) == '') {
                    $('[name="code_box_2"]').empty();
                    var url = buildUrl({'component': 'admin', 'page': 'plagiarism', 'action': 'get_submission_concatenated',
                        'gradeable_id': gradeable_id , 'user_id_1':user_id_1, 'version_user_1': version_user_1, 'user_id_2':'', 'version_user_2': ''});
                    $.ajax({
                        url: url,
                        success: function(data) {
                            data = JSON.parse(data);
                            if(data.error){
                                alert(data.error);
                                return;
                            }
                            $('.CodeMirror')[0].CodeMirror.getDoc().setValue(data.display_code1);
                            for(var users_color in data.ci) {
                            //console.log(data.ci[users_color]);
                            for(var pos in data.ci[users_color]) {
                                var element = data.ci[users_color][pos];
                                $('.CodeMirror')[users_color-1].CodeMirror.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_start": element[7], "data_end": element[8]}, css: "border: 1px solid black; border-right:1px solid red;background: " + element[4]});   
                            }
                        }
                        	$('.CodeMirror')[0].CodeMirror.refresh();
                            //$('[name="code_box_1"]').empty().append(data.display_code1);
                        },
                        error: function(e) {
                            alert("Could not load submitted code, please refresh the page and try again.");
                        }
                    })

                }
                else {
                    var user_id_2 = JSON.parse($('[name="user_id_2"]', form).val())["user_id"];
                    var version_user_2 = JSON.parse($('[name="user_id_2"]', form).val())["version"];
                    var url = buildUrl({'component': 'admin', 'page': 'plagiarism', 'action': 'get_submission_concatenated',
                        'gradeable_id': gradeable_id , 'user_id_1':user_id_1, 'version_user_1': version_user_1, 'user_id_2':user_id_2, 'version_user_2': version_user_2});
                    $.ajax({
                        url: url,
                        success: function(data) {
                            data = JSON.parse(data);
                            if(data.error){
                                alert(data.error);
                                return;
                            }
                            $('.CodeMirror')[0].CodeMirror.getDoc().setValue(data.display_code1);
                            $('.CodeMirror')[1].CodeMirror.getDoc().setValue(data.display_code2);
                            var code_mirror = 0;
                            console.log(data.ci);
                            for(var users_color in data.ci) {
                            for(var pos in data.ci[users_color]) {
                                var element = data.ci[users_color][pos];
                                $('.CodeMirror')[users_color-1].CodeMirror.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_start": element[7], "data_end": element[8]}, css: "border: 1px solid black; border-right:1px solid red;background: " + element[4]});   
                            }
                        }
                        	$('.CodeMirror')[0].CodeMirror.refresh();

                        	$('.CodeMirror')[1].CodeMirror.refresh();
                            // $('[name="code_box_1"]').empty().append(data.display_code1);
                            // $('[name="code_box_2"]').empty().append(data.display_code2);
                        },
                        error: function(e) {
                            alert("Could not load submitted code, please refresh the page and try again.");
                        }
                    })

                }
            }
        }
    }
}

function getMatchesForClickedMatch(gradeable_id, event, user_1_match_start, user_1_match_end, where, color , span_clicked, popup_user_2, popup_version_user_2) {
    //console.log(user_1_match_start);
    //console.log(user_1_match_end);
    var form = $("#users_with_plagiarism");
    var user_id_1 = $('[name="user_id_1"]', form).val();
    var version_user_1 = $('[name="version_user_1"]', form).val();
    var version_user_2='';
    var user_id_2='';
    if($('[name="user_id_2"]', form).val() != "") {
        user_id_2 = JSON.parse($('[name="user_id_2"]', form).val())["user_id"];
        version_user_2 = JSON.parse($('[name="user_id_2"]', form).val())["version"];
    }
    
    var url = buildUrl({'component': 'admin', 'page': 'plagiarism', 'action': 'get_matches_for_clicked_match',
                        'gradeable_id': gradeable_id , 'user_id_1':user_id_1, 'version_user_1': version_user_1, 'start':user_1_match_start.line, 'end': user_1_match_end.line});

    //console.log(user_1_match_start.line);

    $.ajax({
        url: url,
        success: function(data) {
            //console.log(data);
            data = JSON.parse(data);
            if(data.error){
                alert(data.error);
                return;
            }

            if(where == 'code_box_2') {
                var name_span_clicked = $(span_clicked).attr('name');
                var scroll_position=-1;
                $('[name="code_box_2"]').find('span').each(function(){
                    var attr = $(this).attr('name');
                    if (typeof attr !== typeof undefined && attr !== false && attr == name_span_clicked) {
                        $(this).css('background-color',"#FF0000");
                    }
                });
                $('[name="code_box_1"]').find('span').each(function(){
                    var attr = $(this).attr('name');
                    if (typeof attr !== typeof undefined && attr !== false) {
                        attr= JSON.parse(attr);
                        if(attr['start'] == user_1_match_start && attr['end'] == user_1_match_end) {
                            $(this).css('background-color',"#FF0000");
                        }
                    }
                });
                $('[name="code_box_1"]').scrollTop(0);
                var scroll_position=0;
                $('[name="code_box_1"]').find('span').each(function(){
                    if ($(this).css('background-color')=="rgb(255, 0, 0)") {
                        scroll_position = $(this).offset().top-$('[name="code_box_1"]').offset().top;
                        return false;
                    }
                });
                $('[name="code_box_1"]').scrollTop(scroll_position);
            }

            else if(where == 'code_box_1') {
                var to_append='';

                $.each(data, function(i,match){
                    //console.log(match);
                    to_append += '<li class="ui-menu-item"><div tabindex="-1" class="ui-menu-item-wrapper" onclick=getMatchesForClickedMatch("'+gradeable_id+'",event,'+user_1_match_start.line+','+ user_1_match_end.line+',"popup","'+ color+ '","","'+match[0]+'",'+match[1]+');>'+ match[3]+' '+match[4]+' &lt;'+match[0]+'&gt; (version:'+match[1]+')</div></li>';
                });
                to_append = $.parseHTML(to_append);
                $("#popup_to_show_matches_id").empty().append(to_append);
                var x = event.pageX;
                var y = event.pageY;
                $('#popup_to_show_matches_id').css('display', 'block');
                var width = $('#popup_to_show_matches_id').width();
                $('#popup_to_show_matches_id').css('top', y+5);
                $('#popup_to_show_matches_id').css('left', x-width/2.00);

            }

            else if(where == 'popup') {
                jQuery.ajaxSetup({async:false});
                $('[name="user_id_2"]', form).val('{"user_id":"'+popup_user_2+'","version":'+popup_version_user_2+'}');
                setUserSubmittedCode(gradeable_id, 'user_id_2');
                $('[name="code_box_1"]').find('span').each(function(){
                    var attr = $(this).attr('name');
                    if (typeof attr !== typeof undefined && attr !== false) {
                        attr= JSON.parse(attr);
                        if(attr['start'] == user_1_match_start && attr['end'] == user_1_match_end) {
                            $(this).css('background-color',"#FF0000");
                        }
                    }
                });
                $.each(data, function(i,match){
                    if(match[0] == popup_user_2 && match[1] == popup_version_user_2) {
                        $.each(match[2], function(j, range){
                            $('[name="code_box_2"]').find('span').each(function(){
                                var attr = $(this).attr('name');
                                if (typeof attr !== typeof undefined && attr !== false) {
                                    if((JSON.parse($(this).attr("name")))["start"] == range["start"] && (JSON.parse($(this).attr("name")))["end"] == range["end"]) {
                                        $(this).css('background-color',"#FF0000");
                                    }
                                }
                            });
                        });
                    }
                });
                $('[name="code_box_2"]').scrollTop(0);
                var scroll_position=0;
                $('[name="code_box_2"]').find('span').each(function(){
                    if ($(this).css('background-color')=="rgb(255, 0, 0)") {
                        scroll_position = $(this).offset().top-$('[name="code_box_2"]').offset().top;
                        return false;
                    }
                });
                $('[name="code_box_2"]').scrollTop(scroll_position);
                jQuery.ajaxSetup({async:true});
            }
        },
        error: function(e) {
            alert("Could not load submitted code, please refresh the page and try again.");
        }
    })
}

function toggleUsersPlagiarism(gradeable_id) {
    var form = $("#users_with_plagiarism");
    var user_id_1 = $('[name="user_id_1"]', form).val();
    var version_user_1 = $('[name="version_user_1"]', form).val();

    if( user_id_1 == '' || version_user_1 == '' || $('[name="user_id_2"]', form).val() == '') return;

    var user_id_2 = JSON.parse($('[name="user_id_2"]', form).val())["user_id"];
    var version_user_2 = JSON.parse($('[name="user_id_2"]', form).val())["version"];
    $('[name="user_id_1"]', form).val(user_id_2);
    jQuery.ajaxSetup({async:false});
    setUserSubmittedCode(gradeable_id ,'user_id_1');
    $('[name="version_user_1"]', form).val(version_user_2);
    setUserSubmittedCode(gradeable_id, 'version_user_1');
    $('[name="user_id_2"]', form).val('{"user_id":"'+user_id_1+'","version":'+version_user_1+'}');
    jQuery.ajaxSetup({async:true});
    setUserSubmittedCode(gradeable_id, 'user_id_2');
}


function configureNewGradeableForPlagiarismFormOptionChanged(prior_term_gradeables, select_element_name) {
    var form = $("#save-configuration-form");
    if(select_element_name == "language") {

        //
        // Following code is used to set default window size for different languages
        // that will appear in 'configureNewGradeableForPlagiarismForm'
        // to change the default values, just change the val attribute for the language.
        //

        if ($('[name="language"]', form).val() == "python") {
            $('[name="sequence_length"]', form).val('1');
        }
        else if ($('[name="language"]', form).val() == "cpp") {
            $('[name="sequence_length"]', form).val('2');
        }
        else if ($('[name="language"]', form).val() == "java") {
            $('[name="sequence_length"]', form).val('3');
        }
        else if ($('[name="language"]', form).val() == "plaintext") {
            $('[name="sequence_length"]', form).val('4');
        }
    }
    else if(select_element_name.substring(0, 9) == "prev_sem_") {
        var i = select_element_name.substring(9);
        var selected_sem = $('[name="prev_sem_'+ i +'"]', form).val();
        $('[name="prev_gradeable_'+ i +'"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        $('[name="prev_course_'+ i +'"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        if(selected_sem != '') {
            var append_options = '';
            $.each(prior_term_gradeables, function(sem,courses_gradeables){
                if(selected_sem == sem) {
                    $.each(courses_gradeables, function(course,gradeables){
                        append_options += '<option value="'+ course +'">'+ course +'</option>';
                    });
                }
            });
            $('[name="prev_course_'+ i +'"]', form).find('option').remove().end().append('<option value="">None</option>'+ append_options).val('');
        }
    }
    else if(select_element_name.substring(0, 12) == "prev_course_") {
        var i = select_element_name.substring(12);
        var selected_sem = $('[name="prev_sem_'+ i +'"]', form).val();
        var selected_course = $('[name="prev_course_'+ i +'"]', form).val();
        $('[name="prev_gradeable_'+ i +'"]', form).find('option').remove().end().append('<option value="">None</option>').val('');
        if(selected_course != '') {
            var append_options = '';
            $.each(prior_term_gradeables, function(sem,courses_gradeables){
                if(selected_sem == sem) {
                    $.each(courses_gradeables, function(course,gradeables){
                        if(selected_course == course) {
                            $.each(gradeables, function (index, gradeable) {
                                append_options += '<option value="'+ gradeable +'">'+ gradeable +'</option>';
                            });
                        }
                    });
                }
            });
            $('[name="prev_gradeable_'+ i +'"]', form).find('option').remove().end().append('<option value="">None</option>'+ append_options).val('');
        }
    }
}

function copyToClipboard(code) {
    var download_info = JSON.parse($('#download_info_json_id').val());
    var required_emails = [];

    $('#download-form input:checkbox').each(function() {
        if ($(this).is(':checked')) {
            var thisVal = $(this).val();

            if (thisVal === 'instructor') {
                for (var i = 0; i < download_info.length; ++i) {
                    if (download_info[i].group === 'Instructor') {
                        required_emails.push(download_info[i].email);
                    }
                }
            }
            else if (thisVal === 'full_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if (download_info[i].group === 'Full Access Grader (Grad TA)') {
                        required_emails.push(download_info[i].email);
                    }
                }
            }
            else if (thisVal === 'limited_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if (download_info[i].group === "Limited Access Grader (Mentor)") {
                        required_emails.push(download_info[i].email);
                    }
                }
            }
            else {
                for (var i = 0; i < download_info.length; ++i) {
                    if (code === 'user') {
                        if (download_info[i].reg_section === thisVal) {
                            required_emails.push(download_info[i].email);
                        }
                    }
                    else if (code === 'grader') {
                        if (download_info[i].reg_section === 'All') {
                            required_emails.push(download_info[i].email);
                        }

                        if ($.inArray(thisVal, download_info[i].reg_section.split(',')) !== -1) {
                            required_emails.push(download_info[i].email);
                        }
                    }
                }
            }
        }
    });

    required_emails = $.unique(required_emails);
    var temp_element = $("<textarea></textarea>").text(required_emails.join(','));
    $(document.body).append(temp_element);
    temp_element.select();
    document.execCommand('copy');
    temp_element.remove();
    setTimeout(function() {
        $('#copybuttonid').prop('value', 'Copied');
    }, 0);
    setTimeout(function() {
        $('#copybuttonid').prop('value', 'Copy Emails to Clipboard');
    }, 1000);
}

function downloadCSV(code) {
    var download_info = JSON.parse($('#download_info_json_id').val());
    var csv_data = 'First Name,Last Name,User ID,Email,Registration Section,Rotation Section,Group\n';
    var required_user_id = [];

    $('#download-form input:checkbox').each(function() {
        if ($(this).is(':checked')) {
            var thisVal = $(this).val();

            if (thisVal === 'instructor') {
                for (var i = 0; i < download_info.length; ++i) {
                    if ((download_info[i].group === 'Instructor') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                        csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                        required_user_id.push(download_info[i].user_id);
                    }
                }
            }
            else if (thisVal === 'full_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if ((download_info[i].group === 'Full Access Grader (Grad TA)') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                        csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                        required_user_id.push(download_info[i].user_id);
                    }
                }
            }
            else if (thisVal === 'limited_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if ((download_info[i].group === 'Limited Access Grader (Mentor)') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                        csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                        required_user_id.push(download_info[i].user_id);
                    }
                }
            }
            else {
                for (var i = 0; i < download_info.length; ++i) {
                    if (code === 'user') {
                        if ((download_info[i].reg_section === thisVal) && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                            csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                            required_user_id.push(download_info[i].user_id);
                        }
                    }
                    else if (code === 'grader') {
                        if ((download_info[i].reg_section === 'All') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                            csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                            required_user_id.push(download_info[i].user_id);
                        }
                        if (($.inArray(thisVal, download_info[i].reg_section.split(',')) !== -1) && ($.inArray(download_info[i].user_id, required_user_id) === -1)) {
                            csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                            required_user_id.push(download_info[i].user_id);
                        }
                    }
                }
            }
        }
    });

    var temp_element = $('<a id="downloadlink"></a>');
    var address = "data:text/csv;charset=utf-8," + encodeURIComponent(csv_data);
    temp_element.attr('href', address);
    temp_element.attr('download', 'submitty_user_emails.csv');
    temp_element.css('display', 'none');
    $(document.body).append(temp_element);
    $('#downloadlink')[0].click();
    $('#downloadlink').remove();
}

function adminTeamForm(new_team, who_id, reg_section, rot_section, user_assignment_setting_json, members, pending_members, max_members) {
    $('.popup-form').css('display', 'none');
    var form = $("#admin-team-form");
    form.css("display", "block");

    $('[name="new_team"]', form).val(new_team);
    $('[name="reg_section"] option[value="' + reg_section + '"]', form).prop('selected', true);
    $('[name="rot_section"] option[value="' + rot_section + '"]', form).prop('selected', true);
    if(new_team) {
        $('[name="num_users"]', form).val(3);
    }
    else if (!new_team) {
        $('[name="num_users"]', form).val(members.length+pending_members.length+2);
    }

    var title_div = $("#admin-team-title");
    title_div.empty();
    var members_div = $("#admin-team-members");
    members_div.empty();
    var team_history_title_div = $("#admin-team-history-title");
    team_history_title_div.empty();
    var team_history_div_left = $("#admin-team-history-left");
    team_history_div_left.empty();
    var team_history_div_right = $("#admin-team-history-right");
    team_history_div_right.empty();
    members_div.append('Team Member IDs:<br />');
    var student_full = JSON.parse($('#student_full_id').val());
    if (new_team) {
        $('[name="new_team_user_id"]', form).val(who_id);
        $('[name="edit_team_team_id"]', form).val("");

        title_div.append('Create New Team: ' + who_id);
        members_div.append('<input class="readonly" type="text" name="user_id_0" readonly="readonly" value="' + who_id + '" />');
        for (var i = 1; i < 3; i++) {
            members_div.append('<input type="text" name="user_id_' + i + '" /><br />');
            $('[name="user_id_'+i+'"]', form).autocomplete({
                source: student_full
            });
        }
        members_div.find('[name="reg_section"]').val(reg_section);
        members_div.find('[name="rot_section"]').val(rot_section);
    }
    else {
        $('[name="new_team_user_id"]', form).val("");
        $('[name="edit_team_team_id"]', form).val(who_id);

        title_div.append('Edit Team: ' + who_id);
        for (var i = 0; i < members.length; i++) {
            members_div.append('<input class="readonly" type="text" name="user_id_' + i + '" readonly="readonly" value="' + members[i] + '" /> \
                <input id="remove_member_'+i+'" class = "btn btn-danger" type="submit" value="Remove" onclick="removeTeamMemberInput('+i+');" \
                style="cursor:pointer; width:80px; padding-top:3px; padding-bottom:3px;" aria-hidden="true"></input><br />');
        }
        for (var i = members.length; i < members.length+pending_members.length; i++) {
            members_div.append('<input class="readonly" type="text" style= "font-style: italic; color:grey;" name="pending_user_id_' + i + '" readonly="readonly" value="Pending: ' + pending_members[i-members.length] + '" />\
                <input id="approve_member_'+i+'" class = "btn btn-success" type="submit" value="Accept" onclick="approveTeamMemberInput(this,'+i+');" \
                style="cursor:pointer; width:80px; padding-top:3px; padding-bottom:3px;" aria-hidden="true"></input><br />');
        }
        for (var i = members.length+pending_members.length; i < (members.length+pending_members.length+2); i++) {
            members_div.append('<input type="text" name="user_id_' + i + '" /><br />');
            $('[name="user_id_'+i+'"]', form).autocomplete({
                source: student_full
            });
        }
        var team_history_len=user_assignment_setting_json.team_history.length;
        team_history_title_div.append('Team History: ');
        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="team_formation_date_left" readonly="readonly" value="Team formed on: " /><br />');
        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="team_formation_date_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[0].time+ '" /><br />');
        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="last_edit_left" readonly="readonly" value="Last edited on: " /><br />');
        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="last_edit_date_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[team_history_len-1].time+ '" /><br />');
        for (var j = 0; j <=team_history_len-1; j++) {
            if(user_assignment_setting_json.team_history[j].action == "admin_create"){
                for (var i = 0; i < members.length; i++) {
                    if(user_assignment_setting_json.team_history[j].first_user == members[i] || user_assignment_setting_json.team_history[j].added_user == members[i]){
                        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_left" readonly="readonly" value="'+members[i]+ ' added on: " /><br />');
                        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[j].time+ '" /><br />');
                    }
                }
            }
            if(user_assignment_setting_json.team_history[j].action == "admin_add_user"){
                for (var i = 0; i < members.length; i++) {
                    if(user_assignment_setting_json.team_history[j].added_user == members[i]){
                        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_left" readonly="readonly" value="'+members[i]+ ' added on: " /><br />');
                        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[j].time+ '" /><br />');
                    }
                }
            }
            if(user_assignment_setting_json.team_history[j].action == "admin_remove_user"){
                team_history_div_left.append('<input class="readonly" type="text" style="width:100%;"  readonly="readonly" value="'+user_assignment_setting_json.team_history[j].removed_user+ ' removed on: " /><br />');
                team_history_div_right.append('<input class="readonly" type="text" style="width:100%;"  readonly="readonly" value="' +user_assignment_setting_json.team_history[j].time+ '" /><br />');
            }
        }

    }
    var param = (new_team ? 3 : members.length+2);
    members_div.append('<span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+param+');"><i class="fas fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
}

function removeTeamMemberInput(i) {
    var form = $("#admin-team-form");
    $('[name="user_id_'+i+'"]', form).removeClass('readonly').prop('readonly', false).val("");
    $("#remove_member_"+i).remove();
    var student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function approveTeamMemberInput(old, i) {
    var form = $("#admin-team-form");
    $("#approve_member_"+i).remove();
    $('[name="pending_user_id_'+i+'"]', form).attr("name", "user_id_"+i);
    $('[name="user_id_'+i+'"]', form).attr("style", "font-style: normal;");
    var student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function addTeamMemberInput(old, i) {
    old.remove()
    var form = $("#admin-team-form");
    $('[name="num_users"]', form).val( parseInt($('[name="num_users"]', form).val()) + 1);
    var members_div = $("#admin-team-members");
    members_div.append('<input type="text" name="user_id_' + i + '" /><br /> \
        <span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+ (i+1) +');"><i class="fas fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
    var student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function addCategory(old, i) {
    old.remove()
    var form = $("#admin-team-form");
    $('[name="num_users"]', form).val( parseInt($('[name="num_users"]', form).val()) + 1);
    var members_div = $("#admin-team-members");
    members_div.append('<input type="text" name="user_id_' + i + '" /><br /> \
        <span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+ (i+1) +');"><i class="fas fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
    var student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function importTeamForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#import-team-form");
    form.css("display", "block");
    $('[name="upload_team"]', form).val(null);
}

/**
 * Toggles the page details box of the page, showing or not showing various information
 * such as number of queries run, length of time for script execution, and other details
 * useful for developers, but shouldn't be shown to normal users
 */
function togglePageDetails() {
    var element = document.getElementById('page-info');
    if (element.style.display === 'block') {
        element.style.display = 'none';
    }
    else {
        element.style.display = 'block';
        // Hide the box if you click outside of it
        document.body.addEventListener('mouseup', function pageInfo(event) {
            if (!element.contains(event.target)) {
                element.style.display = 'none';
                document.body.removeEventListener('mouseup', pageInfo, false);
            }
        });
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

function checkTaVersionChange(){
    var message = "You are overriding the student's chosen submission. Are you sure you want to continue?";
    return confirm(message);
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


function checkRefreshPage(url) {
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
                checkRefreshPage(url);
            }
        }
    );
}

function checkRefreshLichenMainPage(url, semester, course) {
    // refresh time for lichen main page
    var refresh_time = 5000;
    setTimeout(function() {
        check_lichen_jobs(url, semester, course);
    }, refresh_time);
}

function check_lichen_jobs(url, semester, course) {
    $.post(url,
        function(data) {
            var last_data = localStorage.getItem("last_data");
            if (data == "REFRESH_ME") {
                last_data= "REFRESH_ME";
                localStorage.setItem("last_data", last_data);
                window.location.href = buildUrl({'component':'admin', 'page' :'plagiarism', 'course':course, 'semester': semester});
            }
            else if(data="NO_REFRESH" && last_data == "REFRESH_ME"){
                last_data= "NO_REFRESH";
                localStorage.setItem("last_data", last_data);
                window.location.href = buildUrl({'component':'admin', 'page' :'plagiarism', 'course':course, 'semester': semester});
            }
            else {
                checkRefreshLichenMainPage(url, semester, course);
            }
        }
    );
}

function downloadFile(file, path) {
    window.location = buildUrl({
        'component': 'misc',
        'page': 'download_file',
        'dir': 'submissions',
        'file': file,
        'path': path});
}

function downloadZip(grade_id, user_id, version = null) {
    var url_components = {
        'component': 'misc',
        'page': 'download_zip',
        'dir': 'submissions',
        'gradeable_id': grade_id,
        'user_id': user_id
    };

    if(version !== null) {
        url_components['version'] = version;
    }
    window.location = buildUrl(url_components);
    return false;
}

function downloadFileWithAnyRole(file_name, path) {
    // Trim file without path
    var file = file_name;
    if (file.indexOf("/") != -1) {
        file = file.substring(file.lastIndexOf('/')+1);
    }
    window.location = buildUrl({'component': 'misc', 'page': 'download_file_with_any_role', 'dir': 'course_materials', 'file': file, 'path': path});
}

function checkColorActivated() {
    var pos = 0;
    var seq = "&&((%'%'BA\r";
    $(document.body).keyup(function colorEvent(e) {
        pos = seq.charCodeAt(pos) === e.keyCode ? pos + 1 : 0;
        if (pos === seq.length) {
            setInterval(function() { $("*").addClass("rainbow"); }, 100);
            $(document.body).off('keyup', colorEvent);
        }
    });
}
$(checkColorActivated);

function changeColor(div, hexColor){
    div.style.color = hexColor;
}

function openDiv(id) {
    var elem = $('#' + id);
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $('#' + id + '-span').removeClass('fa-folder-open').addClass('fa-folder');
    }
    else {
        elem.show();
        elem.addClass('open');
        $('#' + id + '-span').removeClass('fa-folder').addClass('fa-folder-open');
    }
    return false;
}

function openDivForCourseMaterials(num) {
    var elem = $('#div_viewer_' + num);
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder-open').addClass('fa-folder');
    }
    else {
        elem.show();
        elem.addClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');
    }
    return false;
}

function openUrl(url) {
    window.open(url, "_blank", "toolbar=no, scrollbars=yes, resizable=yes, width=700, height=600");
    return false;
}

function changeName(element, user, visible_username, anon){
    var new_element = element.getElementsByTagName("strong")[0];
    anon = (anon == 'true');
    icon = element.getElementsByClassName("fas fa-eye")[0];
    if(icon == undefined){
        icon = element.getElementsByClassName("fas fa-eye-slash")[0];
        if(anon) {
            new_element.style.color = "black";
            new_element.style.fontStyle = "normal";
        }
        new_element.innerHTML = visible_username;
        icon.className = "fas fa-eye";
        icon.title = "Show full user information";
    } else {
        if(anon) {
            new_element.style.color = "grey";
            new_element.style.fontStyle = "italic";
        }
        new_element.innerHTML = user;
        icon.className = "fas fa-eye-slash";
        icon.title = "Hide full user information";
    }
}

function openFileForum(directory, file, path ){
    var url = buildUrl({'component': 'misc', 'page': 'display_file', 'dir': directory, 'file': file, 'path': path});
    window.open(url,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
}

function openFrame(url, id, filename) {
    var iframe = $('#file_viewer_' + id);
    if (!iframe.hasClass('open')) {
        var iframeId = "file_viewer_" + id + "_iframe";
        // handle pdf
        if(filename.substring(filename.length - 3) === "pdf") {
            iframe.html("<iframe id='" + iframeId + "' src='" + url + "' width='750px' height='1200px' style='border: 0'></iframe>");
        }
        else {
            iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='" + url + "' width='750px' style='border: 0'></iframe>");
        }
        iframe.addClass('open');
    }

    if (!iframe.hasClass('shown')) {
        iframe.show();
        iframe.addClass('shown');
        $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-plus-circle').addClass('fa-minus-circle');
    }
    else {
        iframe.hide();
        iframe.removeClass('shown');
        $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-minus-circle').addClass('fa-plus-circle');
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

function submitAJAX(url, data, callbackSuccess, callbackFailure) {
    $.ajax(url, {
        type: "POST",
        data: data
    })
    .done(function(response) {
        try{
            response = JSON.parse(response);
            if (response['status'] === 'success') {
                callbackSuccess(response);
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

function getFileExtension(filename){
    return (filename.substring(filename.lastIndexOf(".")+1)).toLowerCase();
}

function openPopUp(css, title, count, testcase_num, side) {
    var element_id = "container_" + count + "_" + testcase_num + "_" + side;
    var elem_html = "<link rel=\"stylesheet\" type=\"text/css\" href=\"" + css + "\" />";
    elem_html += title + document.getElementById(element_id).innerHTML;
    my_window = window.open("", "_blank", "status=1,width=750,height=500");
    my_window.document.write(elem_html);
    my_window.document.close();
    my_window.focus();
}

function checkForumFileExtensions(files){
    var count = 0;
    for(var i = 0; i < files.length; i++){
        var extension = getFileExtension(files[i].name);
        if(extension == "gif" || extension == "png" || extension == "jpg" || extension == "jpeg" || extension == "bmp"){
            count++;
        }
    } return count == files.length;
}

function displayError(message){
    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + message + '</div>';
    $('#messages').append(message);
    $('#messages').fadeIn("slow");
}

function resetForumFileUploadAfterError(displayPostId){
    $('#file_name' + displayPostId).html('');
    document.getElementById('file_input_label' + displayPostId).style.border = "2px solid red";
    document.getElementById('file_input' + displayPostId).value = null;
}

function checkNumFilesForumUpload(input, post_id){
    var displayPostId = (typeof post_id !== "undefined") ? "_" + escape(post_id) : "";
    if(input.files.length > 5){
        displayError('Max file upload size is 5. Please try again.');
        resetForumFileUploadAfterError(displayPostId);
    } else {
        if(!checkForumFileExtensions(input.files)){
            displayError('Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)');
            resetForumFileUploadAfterError(displayPostId);
            return;
        }
        $('#file_name' + displayPostId).html('<p style="display:inline-block;">' + input.files.length + ' files selected.</p>');
        $('#messages').fadeOut();
        document.getElementById('file_input_label' + displayPostId).style.border = "";
    }
}

function testAndGetAttachments(post_box_id, dynamic_check) {
    var index = post_box_id - 1;
    // Files selected
    var files = [];
    for (var j = 0; j < file_array[index].length; j++) {
        if (file_array[index][j].name.indexOf("'") != -1 ||
            file_array[index][j].name.indexOf("\"") != -1) {
            alert("ERROR! You may not use quotes in your filename: " + file_array[index][j].name);
            return false;
        }
        else if (file_array[index][j].name.indexOf("\\\\") != -1 ||
            file_array[index][j].name.indexOf("/") != -1) {
            alert("ERROR! You may not use a slash in your filename: " + file_array[index][j].name);
            return false;
        }
        else if (file_array[index][j].name.indexOf("<") != -1 ||
            file_array[index][j].name.indexOf(">") != -1) {
            alert("ERROR! You may not use angle brackets in your filename: " + file_array[index][j].name);
            return false;
        }
        files.push(file_array[index][j]);
    }
    if(files.length > 5){
        if(dynamic_check) {
            displayError('Max file upload size is 5. Please remove attachments accordingly.');
        } else {
            displayError('Max file upload size is 5. Please try again.');
        }
        return false;
    } else {
        if(!checkForumFileExtensions(files)){
            displayError('Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)');
            return false;
        }
    }
    return files;
}

function publishFormWithAttachments(form, test_category, error_message) {
    if(!form[0].checkValidity()) {
        form[0].reportValidity();
        return false;
    }
    if(test_category) {
        if((!form.prop("ignore-cat")) && form.find('.cat-selected').length == 0) {
            alert("At least one category must be selected.");
            return false;
        }
    }
    var post_box_id = form.find(".thread-post-form").attr("post_box_id");
    var formData = new FormData(form[0]);

    var files = testAndGetAttachments(post_box_id, false);
    if(files === false) {
        return false;
    }
    for(var i = 0; i < files.length ; i++) {
        formData.append('file_input[]', files[i], files[i].name);
    }
    var submit_url = form.attr('action');

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data){
            try {
                var json = JSON.parse(data);
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            window.location.href = json['next_page'];
        },
        error: function(){
            var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + error_message + '</div>';
            $('#messages').append(message);
            return;
        }
    });
    return false;
}

function createThread() {
    return publishFormWithAttachments($(this), true, "Something went wrong while creating thread. Please try again.");
}

function publishPost() {
    return publishFormWithAttachments($(this), false, "Something went wrong while publishing post. Please try again.");
}

function changeThreadStatus(thread_id) {
	var url = buildUrl({'component': 'forum', 'page': 'change_thread_status_resolve'});
	$.ajax({
			url: url,
			type: "POST",
			data: {
				thread_id: thread_id
			},
			success: function(data) {
				try {
					var json = JSON.parse(data);
				} catch(err) {
					var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
					$('#messages').append(message);
					return;
				}
				if(json['error']) {
					var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
					$('#messages').append(message);
					return;
				}
				window.location.reload();
				var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Thread marked as resolved.</div>';
					$('#messages').append(message);
			},
			error: function() {
				window.alert('Something went wrong when trying to mark this thread as resolved. Please try again.');
			}
	});
}

function editPost(post_id, thread_id, shouldEditThread) {
    if(!checkAreYouSureForm()) return;
    var form = $("#thread_form");
    var url = buildUrl({'component': 'forum', 'page': 'get_edit_post_content'});
    $.ajax({
            url: url,
            type: "POST",
            data: {
                post_id: post_id,
                thread_id: thread_id
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var post_content = json.post;
                var lines = post_content.split(/\r|\r\n|\n/).length;
                var anon = json.anon;
                var change_anon = json.change_anon;
                var user_id = escapeSpecialChars(json.user);
                var time = Date.parse(json.post_time);
                if(!time) {
                    // Timezone suffix ":00" might be missing
                    time = Date.parse(json.post_time+":00");
                }
                time = new Date(time);
                var categories_ids = json.categories_ids;
                var date = time.toLocaleDateString();
                time = time.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
                var contentBox = form.find("[name=thread_post_content]")[0];
                contentBox.style.height = lines*14;
                var editUserPrompt = document.getElementById('edit_user_prompt');
                editUserPrompt.innerHTML = 'Editing a post by: ' + user_id + ' on ' + date + ' at ' + time;
                contentBox.value = post_content;
                document.getElementById('edit_post_id').value = post_id;
                document.getElementById('edit_thread_id').value = thread_id;
                if(change_anon) {
                    $('#thread_post_anon_edit').prop('checked', anon);
                } else {
                    $('label[for=Anon]').remove();
                    $('#thread_post_anon_edit').remove();
                }
                $('#edit-user-post').css('display', 'block');

                $(".cat-buttons input").prop('checked', false);
                // If first post of thread
                if(shouldEditThread) {
                    var thread_title = json.title;
                    var thread_status = json.thread_status;
                    $("#title").prop('disabled', false);
                    $(".edit_thread").show();
                    $("#title").val(thread_title);
                    $("#thread_status").val(thread_status);
                    // Categories
                    $(".cat-buttons").removeClass('cat-selected');
                    $.each(categories_ids, function(index, category_id) {
                        var cat_input = $(".cat-buttons input[value="+category_id+"]");
                        cat_input.prop('checked', true);
                        cat_input.parent().addClass('cat-selected');
                    });
                    $(".cat-buttons").trigger("eventChangeCatClass");
                    $("#thread_form").prop("ignore-cat",false);
                    $("#category-selection-container").show();
                    $("#thread_status").show();
                } else {
                    $("#title").prop('disabled', true);
                    $(".edit_thread").hide();
                    $("#thread_form").prop("ignore-cat",true);
                    $("#category-selection-container").hide();
                    $("#thread_status").hide();
                }
            },
            error: function(){
                window.alert("Something went wrong while trying to edit the post. Please try again.");
            }
        });
}

/**
 * Enables the use of TAB key to indent within a textarea control.
 *
 * VPAT requires that keyboard navigation through all controls is always available.
 * Since TAB is being redefined to indent code/text, ESC will be defined, in place
 * of TAB, to proceed to the next control element.  SHIFT+TAB  shall be preserved
 * with its default behavior of returning to the previous control element.
 *
 * @param string jQuerySelector
 */
function enableTabsInTextArea(jQuerySelector) {
    var t = $(jQuerySelector);
    t.on('input', function() {
        $(this).outerHeight(38).outerHeight(this.scrollHeight);
    });
    t.trigger('input');
    t.keydown(function(t) {
        if (t.which == 27) {  //ESC was pressed, proceed to next control element.
            // Next control element may not be a sibling, so .next().focus() is not guaranteed
            // to work.  There is also no guarantee that controls are properly wrapped within
            // a <form>.  Therefore, retrieve a master list of all visible controls and switch
            // focus to the next control in the list.
            var controls = $(":input").filter(":visible");
            controls.eq(controls.index(this) + 1).focus();
            return false;
        } else if (!t.shiftKey && t.keyCode == 9) { //TAB was pressed without SHIFT, text indent
            var text = this.value;
            var beforeCurse = this.selectionStart;
            var afterCurse = this.selectionEnd;
            this.value = text.substring(0, beforeCurse) + '\t' + text.substring(afterCurse);
            this.selectionStart = this.selectionEnd = beforeCurse+1;
            return false;
        }
        // No need to test for SHIFT+TAB as it is not being redefined.
    });
}


function changeDisplayOptions(option, thread_id){
    document.cookie = "forum_display_option=" + option + ";";
    window.location.replace(buildUrl({'component': 'forum', 'page': 'view_thread', 'option': option, 'thread_id': thread_id}));
}

function dynamicScrollLoadPage(element, atEnd) {
    var load_page = $(element).attr(atEnd?"next_page":"prev_page");
    if(load_page == 0) {
        return false;
    }
    if($(element).data("dynamic_lock_load")) {
        return null;
    }
    var load_page_callback;
    var load_page_fail_callback;
    var arrow_up = $(element).find(".fa-caret-up");
    var arrow_down = $(element).find(".fa-caret-down");
    var spinner_up = arrow_up.prev();
    var spinner_down = arrow_down.next();
    $(element).data("dynamic_lock_load", true);
    if(atEnd){
        arrow_down.hide();
        spinner_down.show();
        load_page_callback = function(content, count) {
            spinner_down.hide();
            arrow_down.before(content);
            if(count == 0) {
                // Stop further loads
                $(element).attr("next_page", 0);
            } else {
                $(element).attr("next_page", parseInt(load_page) + 1);
                arrow_down.show();
            }
            dynamicScrollLoadIfScrollVisible($(element));
        };
        load_page_fail_callback = function(content, count) {
            spinner_down.hide();
        };
    }
    else {
        arrow_up.hide();
        spinner_up.show();
        load_page_callback = function(content, count) {
            spinner_up.hide();
            arrow_up.after(content);
            if(count == 0) {
                // Stop further loads
                $(element).attr("prev_page", 0);
            } else {
                var prev_page = parseInt(load_page) - 1;
                $(element).attr("prev_page", prev_page);
                if(prev_page >= 1) {
                    arrow_up.show();
                }
            }
            dynamicScrollLoadIfScrollVisible($(element));
        };
        load_page_fail_callback = function(content, count) {
            spinner_up.hide();
        };
    }

    var urlPattern = $(element).data("urlPattern");
    var currentThreadId = $(element).data("currentThreadId",);
    var currentCategoriesId = $(element).data("currentCategoriesId",);
    var course = $(element).data("course",);

    var next_url = urlPattern.replace("{{#}}", load_page);

    var categories_value = $("#thread_category").val();
    var thread_status_value = $("#thread_status_select").val();
    var unread_select_value = $("#unread").is(':checked');
    categories_value = (categories_value == null)?"":categories_value.join("|");
    thread_status_value = (thread_status_value == null)?"":thread_status_value.join("|");
    $.ajax({
            url: next_url,
            type: "POST",
            data: {
                thread_categories: categories_value,
                thread_status: thread_status_value,
                unread_select: unread_select_value,
                currentThreadId: currentThreadId,
                currentCategoriesId: currentCategoriesId,
            },
            success: function(r){
                var x = JSON.parse(r);
                var content = x.html;
                var count = x.count;
                content = `${content}`;
                $(element).data("dynamic_lock_load", false);
                load_page_callback(content, count);
            },
            error: function(){
                $(element).data("dynamic_lock_load", false);
                load_page_fail_callback();
                window.alert("Something went wrong while trying to load more threads. Please try again.");
            }
    });
    return true;
}

function dynamicScrollLoadIfScrollVisible(jElement) {
    if(jElement[0].scrollHeight <= jElement[0].clientHeight) {
        if(dynamicScrollLoadPage(jElement[0], true) === false) {
            dynamicScrollLoadPage(jElement[0], false);
        }
    }
}

function dynamicScrollContentOnDemand(jElement, urlPattern, currentThreadId, currentCategoriesId, course) {
    jElement.data("urlPattern",urlPattern);
    jElement.data("currentThreadId", currentThreadId);
    jElement.data("currentCategoriesId", currentCategoriesId);
    jElement.data("course", course);

    dynamicScrollLoadIfScrollVisible(jElement);
    $(jElement).scroll(function(){
        var element = $(this)[0];
        var sensitivity = 2;
        var isTop = element.scrollTop < sensitivity;
        var isBottom = (element.scrollHeight - element.offsetHeight - element.scrollTop) < sensitivity;
        if(isTop) {
            element.scrollTop = sensitivity;
            dynamicScrollLoadPage(element,false);
        } else if(isBottom) {
            dynamicScrollLoadPage(element,true);
        }

    });
}

function resetScrollPosition(id){
    if(sessionStorage.getItem(id+"_scrollTop") != 0) {
        sessionStorage.setItem(id+"_scrollTop", 0);
    }
}

function saveScrollLocationOnRefresh(id){
    var element = document.getElementById(id);
    $(element).scroll(function() {
        sessionStorage.setItem(id+"_scrollTop", $(element).scrollTop());
    });
    $(document).ready(function() {
        if(sessionStorage.getItem(id+"_scrollTop") !== null){
            $(element).scrollTop(sessionStorage.getItem(id+"_scrollTop"));
        }
    });
}

function checkAreYouSureForm() {
    var elements = $('form');
    if(elements.hasClass('dirty')) {
        if(confirm("You have unsaved changes! Do you want to continue?")) {
            elements.trigger('reinitialize.areYouSure');
            return true;
        } else {
            return false;
        }
    }
    return true;
}

function alterShowDeletedStatus(newStatus) {
    if(!checkAreYouSureForm()) return;
    document.cookie = "show_deleted=" + newStatus + "; path=/;";
    location.reload();
}

function alterShowMergeThreadStatus(newStatus, course) {
    if(!checkAreYouSureForm()) return;
    document.cookie = course + "_show_merged_thread=" + newStatus + "; path=/;";
    location.reload();
}

function modifyThreadList(currentThreadId, currentCategoriesId, course, loadFirstPage, success_callback){
    var categories_value = $("#thread_category").val();
    var thread_status_value = $("#thread_status_select").val();
    var unread_select_value = $("#unread").is(':checked');
    categories_value = (categories_value == null)?"":categories_value.join("|");
    thread_status_value = (thread_status_value == null)?"":thread_status_value.join("|");
    document.cookie = course + "_forum_categories=" + categories_value + ";";
    document.cookie = "forum_thread_status=" + thread_status_value + ";";
    document.cookie = "unread_select_value=" + unread_select_value + ";";
    var url = buildUrl({'component': 'forum', 'page': 'get_threads', 'page_number': (loadFirstPage?'1':'-1')});
    $.ajax({
            url: url,
            type: "POST",
            data: {
                thread_categories: categories_value,
                thread_status: thread_status_value,
                unread_select: unread_select_value,
                currentThreadId: currentThreadId,
                currentCategoriesId: currentCategoriesId,
            },
            success: function(r){
               var x = JSON.parse(r);
               var page_number = parseInt(x.page_number);
               x = x.html;
               x = `${x}`;
               var jElement = $("#thread_list");
               jElement.children(":not(.fas)").remove();
               $("#thread_list .fa-caret-up").after(x);
               jElement.attr("prev_page", page_number - 1);
               jElement.attr("next_page", page_number + 1);
               jElement.data("dynamic_lock_load", false);
               $("#thread_list .fa-spinner").hide();
               if(loadFirstPage) {
                   $("#thread_list .fa-caret-up").hide();
                   $("#thread_list .fa-caret-down").show();
               } else {
                   $("#thread_list .fa-caret-up").show();
                   $("#thread_list .fa-caret-down").hide();
               }
               dynamicScrollLoadIfScrollVisible(jElement);
               if(success_callback != null) {
                  success_callback();
               }
            },
            error: function(){
               window.alert("Something went wrong when trying to filter. Please try again.");
               document.cookie = course + "_forum_categories=;";
               document.cookie = "forum_thread_status=;";
            }
    })
}

function replyPost(post_id){
    if ( $('#'+ post_id + '-reply').css('display') == 'block' ){
        $('#'+ post_id + '-reply').css("display","none");
    } else {
        hideReplies();
        $('#'+ post_id + '-reply').css('display', 'block');
    }
}

function generateCodeMirrorBlocks(container_element) {
    var codeSegments = container_element.querySelectorAll(".code");
    for (let element of codeSegments){
        var editor0 = CodeMirror.fromTextArea(element, {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });

    var lineCount = editor0.lineCount();
    if (lineCount == 1) {
        editor0.setSize("100%", (editor0.defaultTextHeight() * 2) + "px");
    } else {
        //Default height for CodeMirror is 300px... 500px looks good
        var h = (editor0.defaultTextHeight()) * lineCount + 15;
        editor0.setSize("100%", (h > 500 ? 500 : h) + "px");
    }

    editor0.setOption("theme", "eclipse");
    editor0.refresh();

    }
}

function showHistory(post_id) {
    var url = buildUrl({'component': 'forum', 'page': 'get_history'});
    $.ajax({
            url: url,
            type: "POST",
            data: {
                post_id: post_id
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                $("#popup-post-history").show();
                $("#popup-post-history .post_box.history_box").remove();
                $("#popup-post-history .form-body").css("padding", "5px");
                var dummy_box = $($("#popup-post-history .post_box")[0]);
                for(var i = json.length - 1 ; i >= 0 ; i -= 1) {
                    var post = json[i];
                    box = dummy_box.clone();
                    box.show();
                    box.addClass("history_box");
                    box.find(".post_content").html(post['content']);
                    if(post.is_staff_post) {
                        box.addClass("important");
                    }

                    var first_name = post['user_info']['first_name'].trim();
                    var last_name = post['user_info']['last_name'].trim();
                    var author_user_id = post['user'];
                    var visible_username = first_name + " " + ((last_name.length == 0) ? '' : (last_name.substr(0 , 1) + "."));
                    var info_name = first_name + " " + last_name + " (" + author_user_id + ")";
                    var visible_user_json = JSON.stringify(visible_username);
                    info_name = JSON.stringify(info_name);
                    var user_button_code = "<a style='margin-right:2px;display:inline-block; color:black;' onClick='changeName(this.parentNode, " + info_name + ", " + visible_user_json + ", false)' title='Show full user information'><i class='fas fa-eye' aria-hidden='true'></i></a>&nbsp;";
                    box.find("h7").html("<strong>"+visible_username+"</strong> "+post['post_time']);
                    box.find("h7").before(user_button_code);
                    $("#popup-post-history .form-body").prepend(box);
                }
                generateCodeMirrorBlocks($("#popup-post-history")[0]);
            },
            error: function(){
                window.alert("Something went wrong while trying to display post history. Please try again.");
            }
    });
}

function addNewCategory(){
    var newCategory = $("#new_category_text").val();
    var url = buildUrl({'component': 'forum', 'page': 'add_category'});
    $.ajax({
            url: url,
            type: "POST",
            data: {
                newCategory: newCategory
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully created category "'+ escapeSpecialChars(newCategory) +'".</div>';
                $('#messages').append(message);
                $('#new_category_text').val("");
                // Create new item in #ui-category-list using dummy category
                var category_id = json['new_id'];
                var category_color_code = "#000080";
                var category_desc = escapeSpecialChars(newCategory);
                newelement = $($('#ui-category-list li')[0]).clone(true);
                newelement.attr('id',"categorylistitem-"+category_id);
                newelement.css('color',category_color_code);
                newelement.find(".categorylistitem-desc span").text(category_desc);
                newelement.find(".category-color-picker").val(category_color_code);
                newelement.show();
                newelement.addClass("category-sortable");
                newcatcolorpicker = newelement.find(".category-color-picker");
                newcatcolorpicker.css("background-color",newcatcolorpicker.val());
                $('#ui-category-list').append(newelement);
                $(".category-list-no-element").hide();
                refreshCategories();
            },
            error: function(){
                window.alert("Something went wrong while trying to add a new category. Please try again.");
            }
    })
}

function deleteCategory(category_id, category_desc){
    var url = buildUrl({'component': 'forum', 'page': 'delete_category'});
    $.ajax({
            url: url,
            type: "POST",
            data: {
                deleteCategory: category_id
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully deleted category "'+ escapeSpecialChars(category_desc) +'"</div>';
                $('#messages').append(message);
                $('#categorylistitem-'+category_id).remove();
                refreshCategories();
            },
            error: function(){
                window.alert("Something went wrong while trying to add a new category. Please try again.");
            }
    })
}

function editCategory(category_id, category_desc, category_color) {
    if(category_desc === null && category_color === null) {
        return;
    }
    var data = {category_id: category_id};
    if(category_desc !== null) {
        data['category_desc'] = category_desc;
    }
    if(category_color !== null) {
        data['category_color'] = category_color;
    }
    var url = buildUrl({'component': 'forum', 'page': 'edit_category'});
    $.ajax({
            url: url,
            type: "POST",
            data: data,
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully updated!</div>';
                $('#messages').append(message);
                setTimeout(function() {removeMessagePopup('theid');}, 1000);
                if(category_color !== null) {
                    $("#categorylistitem-"+category_id).css("color",category_color);
                }
                if(category_desc !== null) {
                    $("#categorylistitem-"+category_id).find(".categorylistitem-desc span").text(category_desc);
                }
                refreshCategories();
            },
            error: function(){
                window.alert("Something went wrong while trying to add a new category. Please try again.");
            }
    });
}

function refreshCategories() {
   if($('#ui-category-list').length) {
        // Refresh cat-buttons from #ui-category-list

        var data = $('#ui-category-list').sortable('serialize');
        if(!data.trim()) {
            return;
        }
        data = data.split("&");
        var order = [];
        for(var i = 0; i<data.length; i+=1) {
            var category_id = parseInt(data[i].split('=')[1]);
            var category_desc = $("#categorylistitem-"+category_id+" .categorylistitem-desc span").text().trim();
            var category_color = $("#categorylistitem-"+category_id+" select").val();
            order.push([category_id, category_desc, category_color]);
        }

        // Obtain current selected category
        var selected_button = new Set();
        var category_pick_buttons = $('.cat-buttons');
        for(var i = 0; i<category_pick_buttons.length; i+=1) {
            var cat_button_checkbox = $(category_pick_buttons[i]).find("input");
            var category_id = parseInt(cat_button_checkbox.val());
            if(cat_button_checkbox.prop("checked")) {
                selected_button.add(category_id);
            }
        }

        // Refresh selected categories
        $('#categories-pick-list').empty();
        order.forEach(function(category) {
            var category_id = category[0];
            var category_desc = category[1];
            var category_color = category[2];
            var selection_class = "";
            if(selected_button.has(category_id)) {
                selection_class = "cat-selected";
            }
            var element = ' <a class="btn cat-buttons '+selection_class+'" cat-color="'+category_color+'">'+category_desc+'\
                                <input type="checkbox" name="cat[]" value="'+category_id+'">\
                            </a>';
            $('#categories-pick-list').append(element);
        });

        $(".cat-buttons input[type='checkbox']").each(function() {
            if($(this).parent().hasClass("cat-selected")) {
                $(this).prop("checked",true);
            }
        });
    }

    // Selectors for categories pick up
    // If JS enabled hide checkbox
    $("a.cat-buttons input").hide();

    $(".cat-buttons").click(function() {
        if($(this).hasClass("cat-selected")) {
            $(this).removeClass("cat-selected");
            $(this).find("input[type='checkbox']").prop("checked", false);
        } else {
            $(this).addClass("cat-selected");
            $(this).find("input[type='checkbox']").prop("checked", true);
        }
        $(this).trigger("eventChangeCatClass");
    });

    $(".cat-buttons").bind("eventChangeCatClass", function(){
        var cat_color = $(this).attr('cat-color');
        $(this).css("border-color",cat_color);
        if($(this).hasClass("cat-selected")) {
            $(this).css("background-color",cat_color);
            $(this).css("color","white");
        } else {
            $(this).css("background-color","white");
            $(this).css("color",cat_color);
        }
    });
    $(".cat-buttons").trigger("eventChangeCatClass");
}

function reorderCategories(){
    var data = $('#ui-category-list').sortable('serialize');
    var url = buildUrl({'component': 'forum', 'page': 'reorder_categories'});
    $.ajax({
            url: url,
            type: "POST",
            data: data,
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully reordered categories.';
                $('#messages').append(message);
                setTimeout(function() {removeMessagePopup('theid');}, 1000);
                refreshCategories();
            },
            error: function(){
                window.alert("Something went wrong while trying to reordering categories. Please try again.");
            }
    });
}

/*This function ensures that only one reply box is open at a time*/
function hideReplies(){
    var hide_replies = document.getElementsByClassName("reply-box");
    for(var i = 0; i < hide_replies.length; i++){
        hide_replies[i].style.display = "none";
    }
}

/*This function makes sure that only posts with children will have the collapse function*/
function addCollapsable(){
    var posts = $(".post_box").toArray();
    for(var i = 1; i < posts.length; i++){
        if(parseInt($(posts[i]).next().next().attr("reply-level")) > parseInt($(posts[i]).attr("reply-level"))){
            $(posts[i]).find(".expand")[0].innerHTML = "Hide Replies";
        } else {
            var button = $(posts[i]).find(".expand")[0];
            $(button).hide();
        }
    }
}

function hidePosts(text, id) {
    var currentLevel = parseInt($(text).parent().parent().attr("reply-level")); //The double parent is here because the button is in a span, which is a child of the main post.
    var selector = $(text).parent().parent().next().next();
    var counter = 0;
    var parent_status = "Hide Replies";``
    if (text.innerHTML != "Hide Replies") {
        text.innerHTML = "Hide Replies";
        while (selector.attr("reply-level") > currentLevel) {
            $(selector).show();
            if($(selector).find(".expand")[0].innerHTML != "Hide Replies"){
                var nextLvl = parseInt($(selector).next().next().attr("reply-level"));
                while(nextLvl > (currentLevel+1)){
                    selector = $(selector).next().next();
                    nextLvl = $(selector).next().next().attr("reply-level");
                }
            }
            selector = $(selector).next().next();
        }

    } else {
        while (selector.attr("reply-level") > currentLevel) {
            $(selector).hide();
            selector = $(selector).next().next();
            counter++;
        }
        if(counter != 0){
            text.innerHTML = "Show " + ((counter > 1) ? (counter + " Replies") : "Reply");
        } else {
            text.innerHTML = "Hide Replies";
        }
    }

}

function deletePostToggle(isDeletion, thread_id, post_id, author, time){
    if(!checkAreYouSureForm()) return;
    var page = (isDeletion?"delete_post":"undelete_post");
    var message = (isDeletion?"delete":"undelete");

    var confirm = window.confirm("Are you sure you would like to " + message + " this post?: \n\nWritten by:  " + author + "  @  " + time + "\n\nPlease note: The replies to this comment will also be " + message + "d. \n\nIf you are " + message + " the first post in a thread this will " + message + " the entire thread.");
    if(confirm){
        var url = buildUrl({'component': 'forum', 'page': page});
        $.ajax({
            url: url,
            type: "POST",
            data: {
                post_id: post_id,
                thread_id: thread_id
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var new_url = "";
                switch(json['type']){
                    case "thread":
                    default:
                        new_url = buildUrl({'component': 'forum', 'page': 'view_thread'});
                    break;

                    case "post":
                        new_url = buildUrl({'component': 'forum', 'page': 'view_thread', 'thread_id': thread_id});
                    break;
                }
                window.location.replace(new_url);
            },
            error: function(){
                window.alert("Something went wrong while trying to delete/undelete a post. Please try again.");
            }
        })
    }
}

function alterAnnouncement(thread_id, confirmString, url){
    var confirm = window.confirm(confirmString);
    if(confirm){
        var url = buildUrl({'component': 'forum', 'page': url});
        $.ajax({
            url: url,
            type: "POST",
            data: {
                thread_id: thread_id
            },
            success: function(data){
                window.location.replace(buildUrl({'component': 'forum', 'page': 'view_thread', 'thread_id': thread_id}));
            },
            error: function(){
                window.alert("Something went wrong while trying to remove announcement. Please try again.");
            }
        })
    }
}

function pinThread(thread_id, url){
    var url = buildUrl({'component': 'forum', 'page': url});
    $.ajax({
        url: url,
        type: "POST",
        data: {
            thread_id: thread_id
        },
        success: function(data){
            window.location.replace(buildUrl({'component': 'forum', 'page': 'view_thread', 'thread_id': thread_id}));
        },
        error: function(){
            window.alert("Something went wrong while trying on pin/unpin thread. Please try again.");
        }
    });
}

function updateHomeworkExtensions(data) {
    var fd = new FormData($('#excusedAbsenceForm').get(0));
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'update_extension'});
    $.ajax({
        url: url,
        type: "POST",
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        success: function(data) {
            try {
                var json = JSON.parse(data);
            } catch(err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['error']){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                $('#messages').append(message);
                return;
            }
            if(json['is_team']){
                extensionPopup(json);
                return;
            }
            var form = $("#load-homework-extensions");
            $('#my_table tr:gt(0)').remove();
            var title = '<div class="option-title" id="title">Current Extensions for ' + json['gradeable_id'] + '</div>';
            $('#title').replaceWith(title);
            if(json['users'].length === 0){
                $('#my_table').append('<tr><td colspan="4">There are no extensions for this homework</td></tr>');
            }
            json['users'].forEach(function(elem){
                var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_day_exceptions'] + '</td></tr>'];
                $('#my_table').append(bits.join('</td><td>'));
            });
            $('#user_id').val(this.defaultValue);
            $('#late_days').val(this.defaultValue);
            $('#csv_upload').val(this.defaultValue);
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Updated exceptions for ' + json['gradeable_id'] + '.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function loadHomeworkExtensions(g_id) {
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'get_extension_details', 'g_id': g_id});
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data);
            var form = $("#load-homework-extensions");
            $('#my_table tr:gt(0)').remove();
            var title = '<div class="option-title" id="title">Current Extensions for ' + json['gradeable_id'] + '</div>';
            $('#title').replaceWith(title);
            if(json['users'].length === 0){
                $('#my_table').append('<tr><td colspan="4">There are no extensions for this homework</td></tr>');
            }
            json['users'].forEach(function(elem){
                var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_day_exceptions'] + '</td></tr>'];
                $('#my_table').append(bits.join('</td><td>'));
            });
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    });
}

function addBBCode(type, divTitle){
    var cursor = $(divTitle).prop('selectionStart');
    var text = $(divTitle).val();
    var insert = "";
    if(type == 1) {
        insert = "[url=http://example.com]display text[/url]";
    } else if(type == 0){
        insert = "[code][/code]";
    }
    $(divTitle).val(text.substring(0, cursor) + insert + text.substring(cursor));
}

function refreshOnResponseLateDays(json) {
    $('#late_day_table tr:gt(0)').remove();
    if(json['users'].length === 0){
        $('#late_day_table').append('<tr><td colspan="6">No late days are currently entered.</td></tr>');
    }
    json['users'].forEach(function(elem){
        elem_delete = "<a onclick=\"deleteLateDays('"+elem['user_id']+"', '"+elem['datestamp']+"');\"><i class='fas fa-trash'></i></a>";
        var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_days'], elem['datestamp'], elem_delete + '</td></tr>'];
        $('#late_day_table').append(bits.join('</td><td>'));
    });
}

function updateLateDays(data) {
    var fd = new FormData($('#lateDayForm').get(0));
    var selected_csv_option = $("input:radio[name=csv_option]:checked").val();
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'update_late', 'csv_option': selected_csv_option});
    $.ajax({
        url: url,
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        success: function(data) {
            var json = JSON.parse(data);
            if(json['error']){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var form = $("#load-late-days");
            refreshOnResponseLateDays(json);
            //Reset all form elements
            $('#user_id').val(this.defaultValue);
            $('#datestamp').val(this.defaultValue);
            $('#late_days').val(this.defaultValue);
            $('#csv_upload').val(this.defaultValue);
            $('#csv_option_overwrite_all').prop('checked',true);
            //Display confirmation message
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Late days have been updated.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function deleteLateDays(user_id, datestamp) {
    // Convert 'MM/DD/YYYY HH:MM:SS A' to 'MM/DD/YYYY'
    datestamp_mmddyy = datestamp.split(" ")[0];
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'delete_late'});
    var confirm = window.confirm("Are you sure you would like to delete this entry?");
    if (confirm) {
        $.ajax({
            url: url,
            type: "POST",
            data: {
                csrf_token: csrfToken,
                user_id: user_id,
                datestamp: datestamp_mmddyy
            },
            success: function(data) {
                var json = JSON.parse(data);
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                refreshOnResponseLateDays(json);
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Late days entry removed.</div>';
                $('#messages').append(message);
            },
            error: function() {
                window.alert("Something went wrong. Please try again.");
            }
        })
    }
    return false;
}
function toggleRegradeRequests(){
    var element = document.getElementById("regradeBoxSection");
    if (element.style.display === 'block') {
        element.style.display = 'none';
    }
    else {
        element.style.display = 'block';
    }

}
function changeRegradeStatus(regradeId, gradeable_id, submitter_id, status) {
    var url = buildUrl({'component': 'student', 'gradeable_id': gradeable_id ,'submitter_id': submitter_id ,'regrade_id': regradeId, 'status': status, 'action': 'change_request_status'});
    $.ajax({
        url: url,
        success: function(data) {
            window.location.reload();
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    });
}
/**
  * Taken from: https://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
  */
function escapeSpecialChars(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function escapeHTML(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function changePermission(filename, checked) {
    // send to server to handle file permission change
    var url = buildUrl({'component': 'misc', 'page': 'modify_course_materials_file_permission', 'filename': encodeURIComponent(filename), 'checked': checked});

    $.ajax({
        url: url,
        success: function(data) {},
        error: function(e) {
            alert("Encounter saving the checkbox state.");
        }
    })
}

function changeNewDateTime(filename, newdatatime) {
    // send to server to handle file permission change
    var url = buildUrl({'component': 'misc', 'page': 'modify_course_materials_file_time_stamp', 'filename': encodeURIComponent(filename), 'newdatatime': encodeURIComponent(newdatatime)});

    $.ajax({
        url: url,
        success: function(data) {},
        error: function(e) {
            alert("Encounter saving the NewDateTime.");
        }
    })
}

// edited slightly from https://stackoverflow.com/a/40658647
// returns a boolean value indicating whether or not the element is entirely in the viewport
// i.e. returns false iff there is some part of the element outside the viewport
$.fn.isInViewport = function() {                                        // jQuery method: use as $(selector).isInViewPort()
    var elementTop = $(this).offset().top;                              // get top offset of element
    var elementBottom = elementTop + $(this).outerHeight();             // add height to top to get bottom

    var viewportTop = $(window).scrollTop();                            // get top of window
    var viewportBottom = viewportTop + $(window).height();              // add height to get bottom

    return elementTop > viewportTop && elementBottom < viewportBottom;
};

function checkSidebarCollapse() {
    var size = $(document.body).width();
    if (size < 1000) {
        $("#sidebar").toggleClass("collapsed", true);
    }
    else{
        $("#sidebar").toggleClass("collapsed", false);
    }
}

//Called from the DOM collapse button, toggle collapsed and save to localStorage
function toggleSidebar() {
    var sidebar = $("#sidebar");
    var shown = sidebar.hasClass("collapsed");

    sidebar.addClass("animate");

    localStorage.sidebar = !shown;
    sidebar.toggleClass("collapsed", !shown);
}

$(document).ready(function() {
    //Collapsed sidebar tooltips with content depending on state of sidebar
    $('[data-toggle="tooltip"]').tooltip({
        position: { my: "right+0 bottom+0" },
        content: function () {
            if($("#sidebar").hasClass("collapsed")) {
                if ($(this).attr("title") === "Collapse Sidebar") {
                    return "Expand Sidebar";
                }
                return $(this).attr("title")
            }
            else {
                return ""
            }
        }
    });

    //Remember sidebar preference
    if (localStorage.sidebar !== "") {
        //Apparently !!"false" === true and if you don't cast this to bool then it will animate??
        $("#sidebar").toggleClass("collapsed", localStorage.sidebar === "true");
    }

    //If they make their screen too small, collapse the sidebar to allow more horizontal space
    $(document.body).resize(function() {
        checkSidebarCollapse();
    });
    checkSidebarCollapse();
});

function checkQRProgress(gradeable_id){
    var url = buildUrl({'component': 'misc', 'page': 'check_qr_upload_progress'});
    $.ajax({
        url: url,
        data: {
            gradeable_id : gradeable_id
        },
        type: "POST",
        success: function(data) {
            data = JSON.parse(data);
            var result = {};
            updateQRProgress(data['job_data'], data['count']);
        },
        error: function(e) {
            console.log("Failed to check job queue");
        }
    })
}
// Credit to https://stackoverflow.com/a/24676492/2972004
//      Solution to autoexpand the height of a textarea
function auto_grow(element) {
    element.style.height = "5px";
    element.style.height = (element.scrollHeight + 5)+"px";
}

/**
 * Sets the 'noscroll' textareas to have the correct height
 */
function resizeNoScrollTextareas() {
    // Make sure textareas resize correctly
    $('textarea.noscroll').each(function() {
        auto_grow(this);
    })
}
