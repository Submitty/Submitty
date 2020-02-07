function isYellowMarker(marker) {
    return marker.css.toLowerCase().indexOf("#ffff00") != -1;
}

function isOrangeMarker(marker) {
    return marker.css.toLowerCase().indexOf("#ffa500") != -1;
}

function updateClickedMarkToRed(clickedMark, prevColor = "") {
    clickedMark.css = "border: 1px solid black; background:#FF0000";
    clickedMark.className = "red_plag";
    clickedMark.attributes = {"data_prev_color": prevColor};                    
}

function colorReset(allMarks, clickedMark, updateClickedCallback) {
    allMarks.forEach(m => {
        if(m.className === "red_plag") {
            m.css = "border: 1px solid black; background: " + m.attributes["data_prev_color"];
            m.className = "";
        }
    });

    updateClickedCallback(clickedMark);
}

function setUpLeftPane(editor0) {
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

        if(isYellowMarker(clickedMark)) {
            var allMarks = editor0.getAllMarks();
            colorReset(allMarks, clickedMark, updateClickedMarkToRed);
            editor0.refresh();

            //getMatchesForClickedMatch("{$gradeable_id}", event, lineData.from, lineData.to, "code_box_1", "orange", null, "", "");
        } else if(isOrangeMarker(clickedMark)) {

        }

    }
}

function setUpPlagView() {
    var editor0 = $('.CodeMirror')[0].CodeMirror;
    var editor1 = $('.CodeMirror')[1].CodeMirror;


}


                    if(markers[0].css.toLowerCase().indexOf("#ffff00") != -1) { //Can be used to determine click

                    if(markers[0].css.toLowerCase().indexOf("#ffa500") != -1) { //Can be used to determine click


                        var redSegments = document.getElementsByClassName("red_plag");

                        var marks_editor2 = editor1.getAllMarks();
                        marks_editor2.forEach(mark => {
                            if(mark.attributes.data_start == markers[0].attributes.data_start && mark.attributes.data_end == markers[0].attributes.data_end) {
                                var marker_linedata = mark.find();

                                var allMarks = editor0.getAllMarks();

                                clickedMark.css = "border: 1px solid black; background:#FF0000";
                                clickedMark.className = "red_plag";
                                clickedMark.attributes = {"data_prev_color": "#ffff00"};
                                editor0.refresh();

                                mark.css = "border: 1px solid black; background: #FF0000";
                                mark.className = 'red_plag';
                                mark.attributes = {"data_color_prev": "#ffa500"};
                                editor1.refresh();

                                editor1.scrollIntoView(marker_linedata.to);

                            }
                        });
                    }
                }