var csrfToken = undefined;

window.addEventListener("load", function() {
  for (const elem in document.body.dataset) {
    window[elem] = document.body.dataset[elem];
  }
});

window.addEventListener("resize", checkSidebarCollapse);

/**
 * Acts in a similar fashion to Core->buildUrl() function within the PHP code
 *
 * @param {object} parts - Object representing URL parts to append to the URL
 * @returns {string} - Built up URL to use
 */
function buildUrl(parts = []) {
    return document.body.dataset.baseUrl + parts.join('/');
}

/**
 * Acts in a similar fashion to Core->buildCourseUrl() function within the PHP code
 * Course information is prepended to the URL constructed.
 *
 * @param {object} parts - Object representing URL parts to append to the URL
 * @returns {string} - Built up URL to use
 */
function buildCourseUrl(parts = []) {
    return document.body.dataset.courseUrl + '/' + parts.join('/');
}

function changeDiffView(div_name, gradeable_id, who_id, version, index, autocheck_cnt, helper_id){
    var actual_div_name = "#" + div_name + "_0";
    var expected_div_name = "#" + div_name + "_1";
    var actual_div = $(actual_div_name).children()[0];
    var expected_div = $(expected_div_name).children()[0];
    var list_white_spaces = {};
    $('#'+helper_id).empty();
    if($("#show_char_"+index+"_"+autocheck_cnt).text() == "Visualize whitespace characters"){
        $("#show_char_"+index+"_"+autocheck_cnt).removeClass('btn-default');
        $("#show_char_"+index+"_"+autocheck_cnt).addClass('btn-primary');
        $("#show_char_"+index+"_"+autocheck_cnt).html("Display whitespace/non-printing characters as escape sequences");
        list_white_spaces['newline'] = '&#9166;';
        var option = 'unicode'
    } else if($("#show_char_"+index+"_"+autocheck_cnt).text() == "Display whitespace/non-printing characters as escape sequences") {
        $("#show_char_"+index+"_"+autocheck_cnt).html("Original View");
        list_white_spaces['newline'] = '\\n';
        var option = 'escape'
    } else {
        $("#show_char_"+index+"_"+autocheck_cnt).removeClass('btn-primary');
        $("#show_char_"+index+"_"+autocheck_cnt).addClass('btn-default');
        $("#show_char_"+index+"_"+autocheck_cnt).html("Visualize whitespace characters");
        var option = 'original'
    }
    //Insert actual and expected one at a time
    var url = buildCourseUrl(['gradeable', gradeable_id, 'grading', 'student_output', 'remove']) +
        `?who_id=${who_id}&version=${version}&index=${index}&autocheck_cnt=${autocheck_cnt}&option=${option}&which=expected`;

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
            url = buildCourseUrl(['gradeable', gradeable_id, 'grading', 'student_output', 'remove']) +
                `?who_id=${who_id}&version=${version}&index=${index}&autocheck_cnt=${autocheck_cnt}&option=${option}&which=actual`;
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
        var url = buildCourseUrl(['gradeable', gradeable_id, 'grading', 'student_output']) + `?who_id=${who_id}&index=${index}&version=${version}`;

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
 * Displays edit registration sections form on button press
 */
function extensionPopup(json){
    $('.popup-form').css('display', 'none');
    var form = $('#more_extension_popup');
    form[0].outerHTML = json['data']['popup'];
    $('#more_extension_popup').css('display', 'block');
}

function newDeleteGradeableForm(form_action, gradeable_name) {
    $('.popup-form').css('display', 'none');
    var form = $("#delete-gradeable-form");
    $('[name="delete-gradeable-message"]', form).html('');
    $('[name="delete-gradeable-message"]', form).append('<b>'+gradeable_name+'</b>');
    $('[name="delete-confirmation"]', form).attr('action', form_action);
    form.css("display", "block");
}

function displayCloseSubmissionsWarning(form_action,gradeable_name) {
    $('.popup-form').css('display', 'none');
    var form = $("#close-submissions-form");
    $('[name="close-submissions-message"]', form).html('');
    $('[name="close-submissions-message"]', form).append('<b>'+gradeable_name+'</b>');
    $('[name="close-submissions-confirmation"]', form).attr('action', form_action);
    form.css("display", "block");
}

function newDeleteCourseMaterialForm(path, file_name) {
    let url = buildCourseUrl(["course_materials", "delete"]) + "?path=" + path;
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
    $('[name="delete-confirmation"]', form).attr('action', url);
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

function setFolderRelease(changeActionVariable,releaseDates,id,inDir){

    $('.popup-form').css('display', 'none');

    var form = $("#set-folder-release-form");

    form.css("display", "block");

    $('[id="release_title"]',form).attr('data-path',changeActionVariable);
    $('[name="release_date"]', form).val(releaseDates);
    $('[name="release_date"]',form).attr('data-fp',changeActionVariable);

    inDir = JSON.stringify(inDir);
    $('[name="submit"]',form).attr('data-iden',id);
    $('[name="submit"]',form).attr('data-inDir',inDir);

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

                var url = buildCourseUrl(['plagiarism', 'gradeable', gradeable_id, 'concat']) + `?user_id_1=${user_id_1}&version_user_1=${version_user_1}`;
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

                var url = buildCourseUrl(['plagiarism', 'gradeable', gradeable_id, 'match']) + `?user_id_1=${user_id_1}&version_user_1=${version_user_1}`;
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
                    var url = buildCourseUrl(['plagiarism', 'gradeable', gradeable_id, 'concat']) + `?user_id_1=${user_id_1}&version_user_1=${version_user_1}&user_id_2=&version_user_2=`;
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
                    var url = buildCourseUrl(['plagiarism', 'gradeable', gradeable_id, 'concat']) + `?user_id_1=${user_id_1}&version_user_1=${version_user_1}&user_id_2=${user_id_2}&version_user_2=${version_user_2}`;
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

    var url = buildCourseUrl(['plagiarism', 'gradeable', gradeable_id, 'clicked_match']) + `?user_id_1=${user_id_1}&version_user_1=${version_user_1}&start=${user_1_match_start.line}&end=${user_1_match_end.line}`;

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

    $("#admin-team-form-submit").prop('disabled',false);
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
            $('[name="user_id_'+i+'"]').autocomplete( "option", "appendTo", form );
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
                <input id="remove_member_'+i+'" class = "btn btn-danger" value="Remove" onclick="removeTeamMemberInput('+i+');" \
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
            $('[name="user_id_'+i+'"]').autocomplete( "option", "appendTo", form );
        }

        if (user_assignment_setting_json != false) {
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
    }
    
    $(":text",form).change(function() {
        var found = false;
        for (var i = 0; i < student_full.length; i++) {
            if (student_full[i]['value'] == $(this).val()) {
                found = true;
                break;
            }
        }
        if (found || $(this).val() == '') {
            $(this)[0].setCustomValidity('');
        }
        else {
            $(this)[0].setCustomValidity("Invalid user_id");
        }

        var invalid_entry = false;
        $(":text",form).each( function() {
            if (!this.checkValidity())  {
                invalid_entry = true;
            }
        });
        if (invalid_entry) {
            $("#admin-team-form-submit").prop('disabled',true);
        }
        else {
            $("#admin-team-form-submit").prop('disabled',false);
        }
    });
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


function randomizeRotatingGroupsButton() {
    $('.popup-form').css('display', 'none');
    var form = $("#randomize-button-warning");
    form.css("display", "block");
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
    $.get(url,
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
    $.get(url,
        function(data) {
            var last_data = localStorage.getItem("last_data");
            if (data == "REFRESH_ME") {
                last_data= "REFRESH_ME";
                localStorage.setItem("last_data", last_data);
                window.location.href = buildCourseUrl(['plagiarism']);
            }
            else if(data="NO_REFRESH" && last_data == "REFRESH_ME"){
                last_data= "NO_REFRESH";
                localStorage.setItem("last_data", last_data);
                window.location.href = buildCourseUrl(['plagiarism']);
            }
            else {
                checkRefreshLichenMainPage(url, semester, course);
            }
        }
    );
}

function downloadFile(path, dir) {
    window.location = buildCourseUrl(['download']) + `?dir=${dir}&path=${path}`;
}

function downloadSubmissionZip(grade_id, user_id, version = null, origin = null) {
    window.location = buildCourseUrl(['gradeable', grade_id, 'download_zip']) + `?dir=submissions&user_id=${user_id}&version=${version}&origin=${origin}`;
    return false;
}

function downloadCourseMaterialZip(dir_name, path) {
    window.location = buildCourseUrl(['course_materials', 'download_zip']) + '?dir_name=' + dir_name + '&path=' + path;
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
        return 'closed';
    }
    else {
        elem.show();
        elem.addClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');
        return 'open';
    }
    return false;
}

function openAllDivForCourseMaterials() {
    var elem = $("[id ^= 'div_viewer_']");
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder-open').addClass('fa-folder');
        return 'closed';
    }
    else {
        elem.show();
        elem.addClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');
        return 'open';
    }
    return false;
}
function closeDivForCourseMaterials(num) {
    var elem = $('#div_viewer_' + num);
    elem.hide();
    elem.removeClass('open');
    $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder-open').addClass('fa-folder');
    return 'closed';


}
function openAllDivForCourseMaterials() {
    var elem = $("[id ^= 'div_viewer_']");
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder-open').addClass('fa-folder');
        return 'closed';
    }
    else {
        elem.show();
        elem.addClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');
        return 'open';
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
        $('.alert-success').fadeOut();
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

function displayError(message){
    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + message + '</div>';
    $('#messages').append(message);
    $('#messages').fadeIn("slow");
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

function updateHomeworkExtensions(data) {
    var fd = new FormData($('#excusedAbsenceForm').get(0));
    var url = buildCourseUrl(['extensions', 'update']);
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
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            if(json['data']['is_team']){
                extensionPopup(json);
                return;
            }
            var form = $("#load-homework-extensions");
            $('#my_table tr:gt(0)').remove();
            var title = '<div class="option-title" id="title">Current Extensions for ' + json['data']['gradeable_id'] + '</div>';
            $('#title').replaceWith(title);
            if(json['data']['users'].length === 0){
                $('#my_table').append('<tr><td colspan="4">There are no extensions for this homework</td></tr>');
            }
            json['data']['users'].forEach(function(elem){
                var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_day_exceptions'] + '</td></tr>'];
                $('#my_table').append(bits.join('</td><td>'));
            });
            $('#user_id').val(this.defaultValue);
            $('#late_days').val(this.defaultValue);
            $('#csv_upload').val(this.defaultValue);
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Updated exceptions for ' + json['data']['gradeable_id'] + '.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function updateGradeOverride(data) {
    var fd = new FormData($('#gradeOverrideForm').get(0));
    var url = buildCourseUrl(['grade_override', $('#g_id').val(), 'update']);
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
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            refreshOnResponseOverriddenGrades(json);
            $('#user_id').val(this.defaultValue);
            $('#marks').val(this.defaultValue);
            $('#comment').val(this.defaultValue);
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Updated overridden Grades for ' + json['data']['gradeable_id'] + '.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function loadHomeworkExtensions(g_id, due_date) {
    var url = buildCourseUrl(['extensions', g_id]);
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data);
            var form = $("#load-homework-extensions");
            $('#my_table tr:gt(0)').remove();
            var title = '<div class="option-title" id="title">Current Extensions for ' + json['data']['gradeable_id'] + '</div>';
            $('#title').replaceWith(title);
            $('#due_date').text(due_date);
            if(json['data']['users'].length === 0){
                $('#my_table').append('<tr><td colspan="4">There are no extensions for this homework</td></tr>');
            }
            json['data']['users'].forEach(function(elem){
                var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_day_exceptions'] + '</td></tr>'];
                $('#my_table').append(bits.join('</td><td>'));
            });
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    });
}

function loadOverriddenGrades(g_id) {
    var url = buildCourseUrl(['grade_override', g_id]);
    $.ajax({
        url: url,
        success: function(data) {
            try {
                var json = JSON.parse(data);
            } catch(err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            refreshOnResponseOverriddenGrades(json);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    });
}

function refreshOnResponseOverriddenGrades(json) {
    var form = $("#load-overridden-grades");
    $('#my_table tr:gt(0)').remove();
    var title = '<div class="option-title" id="title">Overridden Grades for ' + json['data']['gradeable_id'] + '</div>';
    $('#title').replaceWith(title);
    if(json['data']['users'].length === 0){
        $('#my_table').append('<tr><td colspan="5">There are no overridden grades for this homework</td></tr>');
    } else {
        json['data']['users'].forEach(function(elem){
            var delete_button = "<a onclick=\"deleteOverriddenGrades('" + elem['user_id'] + "', '" + json['data']['gradeable_id'] + "');\"><i class='fas fa-trash'></i></a>"
            var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['marks'], elem['comment'], delete_button + '</td></tr>'];
            $('#my_table').append(bits.join('</td><td>'));
        });
    }
}

function updateLateDays(data) {
    var fd = new FormData($('#late-day-form').get(0));
    var selected_csv_option = $("input:radio[name=csv_option]:checked").val();
    var url = buildCourseUrl(['late_days', 'update']) + '?csv_option=' + selected_csv_option;
    $.ajax({
        url: url,
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        success: function() {
            window.location.reload();
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
    var url = buildCourseUrl(['late_days', 'delete']);
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
            success: function() {
                window.location.reload();
            },
            error: function() {
                window.alert("Something went wrong. Please try again.");
            }
        })
    }
}

function deleteOverriddenGrades(user_id, g_id) {
    var url = buildCourseUrl(['grade_override', g_id, 'delete']);
    var confirm = window.confirm("Are you sure you would like to delete this entry?");
    if (confirm) {
        $.ajax({
            url: url,
            type: "POST",
            data: {
                csrf_token: csrfToken,
                user_id: user_id
            },
            success: function(data) {
                var json = JSON.parse(data);
                if(json['status'] === 'fail'){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Overridden Grades deleted .</div>';
                $('#messages').append(message);
                refreshOnResponseOverriddenGrades(json);
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
    let url = buildCourseUrl(['course_materials', 'modify_permission']) + '?filenames=' + encodeURIComponent(filename) + '&checked=' + checked;

    $.ajax({
        type: "POST",
        url: url,
        data: {'fn':filename,csrf_token: csrfToken},
        success: function(data) {},
        error: function(e) {
            alert("Encounter saving the checkbox state.");
        }
    })
}

function changeFolderPermission(filenames, checked,handleData) {
    // send to server to handle file permission change
    let url = buildCourseUrl(['course_materials', 'modify_permission']) + '?filenames=' + encodeURIComponent(filenames[0]) + '&checked=' + checked;

    $.ajax({
        type: "POST",
        url: url,
        data: {'fn':filenames,csrf_token: csrfToken},
        success: function(data) {
            if(handleData){
                handleData(data);
            }
        },
        error: function(e) {
            alert("Encounter saving the checkbox state.");
        }
    })
}

function updateToServerTime(fp) {
    var url = buildUrl(['server_time']);

    $.get({
        url: url,
        success: function(data) {
            var time = JSON.parse(data)['data'];
            time = new Date(parseInt(time.year),
                            parseInt(time.month) - 1,
                            parseInt(time.day),
                            parseInt(time.hour),
                            parseInt(time.minute),
                            parseInt(time.second));
            fp.setDate(time,true);
        },
        error: function(e) {
            console.log("Error getting server time.");
        }
    });
}
function updateToTomorrowServerTime(fp) {
    var url = buildUrl(['server_time']);

    $.get({
        url: url,
        success: function(data) {
            var time = JSON.parse(data)['data'];
            time = new Date(parseInt(time.year),
                parseInt(time.month) - 1,
                parseInt(time.day),
                parseInt(time.hour),
                parseInt(time.minute),
                parseInt(time.second));
            nextDay = new Date(time);
            nextDay.setDate(time.getDate()+1);
            fp.setDate(nextDay,true);
        },
        error: function(e) {
            console.log("Error getting server time.");
        }
    });
}
function changeNewDateTime(filename, newdatatime,handleData) {
    // send to server to handle file date/time change
    let url = buildCourseUrl(['course_materials', 'modify_timestamp']) + '?filenames=' + encodeURIComponent(filename) + '&newdatatime=' + newdatatime;
    var tbr;
    tbr=false;
    $.ajax({
        type: "POST",
        url: url,
        data: {'fn':filename,csrf_token: csrfToken},
        success: function(data) {
            tbr=true;
            if(handleData){
                handleData(data);
            }
        },
        error: function(e) {
            alert("Encounter saving the NewDateTime.");

        }
    })
}

function changeFolderNewDateTime(filenames, newdatatime,handleData) {
    // send to server to handle folder date/time change
    let url = buildCourseUrl(['course_materials', 'modify_timestamp']) + '?filenames=' + encodeURIComponent(filenames[0]) + '&newdatatime=' + newdatatime;
    var tbr;
    tbr=false;
    $.ajax({
        type: "POST",
        url: url,
        data: {'fn':filenames,csrf_token: csrfToken},
        success: function(data) {
            tbr=true;
            if(handleData){
                handleData(data);
            }
        },
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
    if (size < 1150) {
        $("aside").toggleClass("collapsed", true);
    }
    else{
        $("aside").toggleClass("collapsed", false);
    }
}

//Called from the DOM collapse button, toggle collapsed and save to localStorage
function toggleSidebar() {
    var sidebar = $("aside");
    var shown = sidebar.hasClass("collapsed");

    localStorage.sidebar = !shown;
    sidebar.toggleClass("collapsed", !shown);
}

$(document).ready(function() {
    //Collapsed sidebar tooltips with content depending on state of sidebar
    $('[data-toggle="tooltip"]').tooltip({
        position: { my: "right+0 bottom+0" },
        content: function () {
            if($("aside").hasClass("collapsed")) {
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
        $("aside").toggleClass("collapsed", localStorage.sidebar === "true");
    }

    //If they make their screen too small, collapse the sidebar to allow more horizontal space
    $(document.body).resize(function() {
        checkSidebarCollapse();
    });
    checkSidebarCollapse();
});

function checkBulkProgress(gradeable_id){
    var url = buildCourseUrl(['gradeable', gradeable_id, 'bulk', 'progress']);
    $.ajax({
        url: url,
        data: null,
        type: "GET",
        success: function(data) {
            data = JSON.parse(data)['data'];
            var result = {};
            updateBulkProgress(data['job_data'], data['count']);
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
