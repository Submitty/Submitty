const YELLOW = "#ffff00";
const ORANGE = "#ffa500";
const RED    = "#ff0000";
var LeftUserMatches = null;
var editor0 = null;
var editor1 = null;

function isColoredMarker(marker, color) {
    return marker.css.toLowerCase().indexOf(color) != -1;
}

function colorEditors(data) {
    for(var users_color in data.ci) {
    	var editor = users_color == 1 ? editor0 : editor1;
    	editor.getDoc().setValue((users_color === 1) ? data.display_code1 : data.display_code2);
        for(var pos in data.ci[users_color]) {
            var element = data.ci[users_color][pos];
            editor.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_prev_color": element[4], "data_start": element[7], "data_end": element[8]}, css: "background: " + element[4]});
        }
        editor.refresh();
    }
}

function updateCssForMark(mark, prevColor, toColor) {
    mark.css = "background:" + toColor;
    clickedMark.className = "red_plag";
    clickedMark.attributes = {"data_prev_color": prevColor};                    
}

function colorReset(allMarks, clickedMark, updateClickedCallback) {
    var attrTemp = {"data_prev_color": null, "data_current_color": null};
    allMarks.forEach(m => {
        if(currentColor === RED || currentColor == ORANGE) {
    		const currentColor = m.attributes["data_current_color"];
    		const prevColor    = m.attributes["data_prev_color"]; 
            attrTemp["data_current_color"] = prevColor;
            attrTemp["data_prev_color"] = 
        	m.attributes = attrTemp;
        	m.css = "background: " + m.attributes["data_prev_color"];
        } 
    });
    updateClickedCallback(clickedMark,);
}

function updatePanesOnOrangeClick(leftClickedMarker, editor0, editor1) {
    var marks_editor2 = editor1.getAllMarks();
    var setLeft = false;
    marks_editor2.forEach(mark => {
        var rightMarkerData = mark.find();

        if(mark.attributes.data_start == leftClickedMark.attributes.data_start && mark.attributes.data_end == leftClickedMark.attributes.data_end) {
        
            if (!setLeft) {
                clickedMark.css = "background:#FF0000";
                clickedMark.attributes = {"data_prev_color": ORANGE, "data_current_color": RED};
                editor0.refresh();
                setLeft = true;
            } 

            mark.css = "background: #FF0000";
            mark.className = 'red_plag';
            mark.attributes = {"data_color_prev": "#ffa500"};
            editor1.refresh();
        }
    });
}

function setUpLeftPane() {
    editor0.getWrapperElement().onmousedown = function(e) {
        var lineCh = editor0.coordsChar({ left: e.clientX, top: e.clientY });
        var markers = editor0.findMarksAt(lineCh);

        // Did not select a marker 
        if (markers.length === 0) { 
            return; 
        }

        // Only grab the first one if there is overlap...
        var lineData = markers[0].find();
        var clickedMark = markers[0];

        if(isColoredMarker(clickedMark, YELLOW)) {
            var allMarks = editor0.getAllMarks();
            colorReset(allMarks, clickedMark, updateClickedMarkToRed);
            editor0.refresh();

            //getMatchesForClickedMatch("{$gradeable_id}", event, lineData.from, lineData.to, "code_box_1", "orange", null, "", "");
        } else if(isColoredMarker(clickedMark, ORANGE)) {
            // In this case we want to update the right side as well...
            updatePanesOnOrangeClick(clickedMark, editor0, editor1);
        }

    }
}



function setUpPlagView(gradeable_id) {

	var form = $("#users_with_plagiarism");
    editor0 = CodeMirror.fromTextArea(document.getElementById('code_box_1'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });
    editor1 = CodeMirror.fromTextArea(document.getElementById('code_box_2'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });

    editor0.setSize("100%", "100%");
    editor1.setSize("100%", "100%");

    $('[name="user_id_1"]', form).change(function(){
        setUserSubmittedCode(gradeable_id,'user_id_1');
    });
    $('[name="version_user_1"]', form).change(function(){
        setUserSubmittedCode(gradeable_id, 'version_user_1');
    });
    $('[name="user_id_2"]', form).change(function(){
        setUserSubmittedCode(gradeable_id, 'user_id_2');
    });

    // $(document).click(function() {
    //     if($('#popup_to_show_matches_id').css('display') == 'block'){
    //         $('#popup_to_show_matches_id').css('display', 'none');
    //     }
    // });


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

                        colorEditors(data);
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
                            colorEditors(data);
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
                            for(var users_color in data.ci) {
                            for(var pos in data.ci[users_color]) {
                                var element = data.ci[users_color][pos];
                                $('.CodeMirror')[users_color-1].CodeMirror.markText({line:element[1],ch:element[0]}, {line:element[3],ch:element[2]}, {attributes: {"data_start": element[7], "data_end": element[8]}, css: "border: 1px solid black; border-right:1px solid red;background: " + element[4]});
                            }
                        }
                        	$('.CodeMirror')[0].CodeMirror.refresh();
                        	$('.CodeMirror')[1].CodeMirror.refresh();
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

