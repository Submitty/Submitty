// Show console.log lines
var DEBUG = false;

// Requires highlight.js

// Used to display the difference between two strings given a json difference
// object and the div ids to display the diff

// Flow:
// diff.load(STRING,STRING)
// diff.evalDifferences(JSON_DIFF)
// diff.display(DIVID1,DIVID2)

var diff = function(){

	// File lines (modified as program goes on)
	var f0,f1;

	// Insertions for files f0 and f1.
	// Contains a line number (after which a line insertion indicator
	// is placed)
	var ins0,ins1;

	// List of [line id, css class] to apply line styles
	var style;

	// List of associated ids or classes
	var assocs;

	// Initialization
	function init(){
		ins0 = [];
		ins1 = [];
		style = {};
		assocs = [];
	}

	// Utility function for a replaceAll string function
	// returns string with the "find" regex replaced with "replace"
	function replaceAll(str, find, replace) {
		consoleLog(str.replace(new RegExp(find, 'g'), replace), ":",new RegExp(find, 'g') , ":",find, ":",replace, new RegExp(find, 'g'));
		return str.replace(new RegExp(find, 'g'), replace);
	}

	// Load files to diff
	function load(file0, file1){
		init();

		f0 = file0.split("\n");
		f1 = file1.split("\n");
		consoleLog("STU_",f0);
		consoleLog("INST_",f1);

		// for (var i = f0.length-1;i >= 0;i--){
		// 	if (f0[i] == "" && f1[i] == ""){
		// 		f0.splice(i,1);
		// 		f1.splice(i,1);
		// 	}
		// 	else{
		// 		break;
		// 	}
		// }
	}

	// Evaluate differences JSON object
	function evalDifferences(differences){
		for (var i = 0;i < differences.length;i++){

			var student = differences[i].student;
			var instructor = differences[i].instructor;

			assocs.push([]);
			markDifference(f0,ins0,student, instructor,i,"stu_");
			markDifference(f1,ins1,instructor, student,i,"ist_");

		}
	}

	// Change a line specified by difference object
	function markDifference(lines,ins, difference, other_diff, changeID,id_prepend){
		assocs[changeID] = (assocs[changeID] || []);
		if (difference.line!== undefined){
			var changes = difference.line;
			var last_line = difference.start;
			for (var i=0;i < changes.length;i++){
				var change = changes[i];
				var line_id = "#" + id_prepend + "line" + change.line_number;
				last_line=change.line_number;
				if (change.line_number !== undefined){
						// consoleLog(id_prepend,"Bad line at ",change.line_number);
						style[line_id] = "bad-line";
						assocs[changeID].push(line_id);
					if (change.word_number || change.char_number){
						if (change.word_number){
							// TODO Automate all these steps inside highlight.js
							var line = change.line_number;
							// Convert word range to multiple connected word ranges
							var subranges = highlight.enrange(change.word_number);
							// will contain all tags
							var surround = [];

							// Convert subranges to character ranges and add
							// surround tags
							for (var k = 0;k < subranges.length;k++){
								subranges[k] = highlight.word_to_character_range(lines[line], subranges[k]);
								surround.push(["<span class='bad-seg bad-seg-"+line+"'>","</span>"]);
							}

							// tag all strings in string with desired elements
							lines[line] = highlight.tagString(lines[line], subranges, surround);

							assocs[changeID].push(".bad-seg-" + line);
						}
						else{
							// TODO Automate all these steps inside highlight.js
							var line = change.line_number;
							// Convert word range to multiple connected word ranges
							var subranges = highlight.enrange_char(change.char_number);
							// will contain all tags
							var surround = [];

							// Convert subranges to character ranges and add
							// surround tags
							for (var k = 0;k < subranges.length;k++){
								surround.push(["<span class='bad-seg bad-seg-"+line+"'>","</span>"]);
							}

							// tag all strings in string with desired elements
							lines[line] = highlight.tagString(lines[line], subranges, surround);

							assocs[changeID].push(".bad-seg-" + line);
						}
					}
				}
				else{
					consoleLog("ERROR: NO LINE NUMBER IN DIFFERENCE");
				}
			}
			if (difference.start !== undefined && other_diff.line !== undefined){
				var lines_inserted =  other_diff.line.length-difference.line.length;
				consoleLog("Insert lines: ",lines_inserted );
				for (var a = 0; a<lines_inserted; a++){
					consoleLog(id_prepend,"Insert line at ",last_line+1);
					// consoleLog(lines,ins, difference,  other_diff, changeID,id_prepend);
					// difference.start += ins.length;
					ins.push(last_line+1);
					// consoleLog("#" + id_prepend + "ins" + difference.start);
					assocs[changeID].push("#" + id_prepend + "ins" + (last_line+1));
				}
			}
		}
		else if (difference.start !== undefined && other_diff.line !== undefined){

			// FIXME: We should be able to get rid of a bunch of the line index
			//        "+1"s in this file when the newline is removed from
			//        view/homework.php
			for (var a = 0; a< other_diff.line.length; a++){
				consoleLog(id_prepend,"Insert line at ",difference.start+1);
				// consoleLog(lines,ins, difference,  other_diff, changeID,id_prepend);
				// difference.start += ins.length;
				ins.push(difference.start+1);
				// consoleLog("#" + id_prepend + "ins" + difference.start);
				assocs[changeID].push("#" + id_prepend + "ins" + (difference.start+1));
			}
		}
		else{
			consoleLog("Couldn't interpret difference : ",difference);
		}
	}

	// Display files on page
	function display(first_diff_tag, second_diff_tag){

		// Display lines from diff
		displayLines(f0,ins0,document.getElementById(first_diff_tag),"stu_");
		displayLines(f1,ins1,document.getElementById(second_diff_tag),"ist_");

		// Create association events
		for (var i = 0;i < assocs.length;i++){
			var selectors = assocs[i];
			setup_line_hover(selectors, first_diff_tag, second_diff_tag);
		}
	}

	// Setup line selectors for hover event
	function setup_line_hover(selectors, first_diff_tag, second_diff_tag){
		// consoleLog("HOVER-setup",selectors, first_diff_tag, second_diff_tag)
		// Function called when group is hovered over
		var event_function_hover_on = function(e){
			consoleLog("HOVER",selectors, first_diff_tag, second_diff_tag)
			for (var u = 0;u<selectors.length;u++){
				// $('#' + first_diff_tag + ' > > > ' + selectors[u]).addClass("line-hover");
				// $('#' + second_diff_tag + ' > > > ' + selectors[u]).addClass("line-hover");
				// $('#' + first_diff_tag + ' > > ' + selectors[u]).addClass("line-hover");
				// $('#' + second_diff_tag + ' > > ' + selectors[u]).addClass("line-hover");
				$('#' + first_diff_tag + ' > ' + selectors[u]).addClass("line-hover");
				$('#' + second_diff_tag + ' > ' + selectors[u]).addClass("line-hover");
			}
		};
		// Function called when group is no longer hovered over
		var event_function_hover_off = function(e){
			for (var u = 0;u<selectors.length;u++){
				// $('#' + first_diff_tag + ' > > > ' + selectors[u]).removeClass("line-hover");
				// $('#' + second_diff_tag + ' > > > ' + selectors[u]).removeClass("line-hover");
				// $('#' + first_diff_tag + ' > > ' + selectors[u]).removeClass("line-hover");
				// $('#' + second_diff_tag + ' > > ' + selectors[u]).removeClass("line-hover");
				$('#' + first_diff_tag + ' > ' + selectors[u]).removeClass("line-hover");
				$('#' + second_diff_tag + ' > ' + selectors[u]).removeClass("line-hover");

			}
		};
		// Set each elements event
		for (var u = 0;u < selectors.length;u++){
			// $('#' + first_diff_tag + ' > > > ' + selectors[u]).hover(event_function_hover_on, event_function_hover_off);
			// $('#' + second_diff_tag + ' > > > ' + selectors[u]).hover(event_function_hover_on, event_function_hover_off);
			// $('#' + first_diff_tag + ' > > ' + selectors[u]).hover(event_function_hover_on, event_function_hover_off);
			// $('#' + second_diff_tag + ' > > ' + selectors[u]).hover(event_function_hover_on, event_function_hover_off);
			$('#' + first_diff_tag + ' > ' + selectors[u]).hover(event_function_hover_on, event_function_hover_off);
			$('#' + second_diff_tag + ' > ' + selectors[u]).hover(event_function_hover_on, event_function_hover_off);
		}
	}

	// Put lines in element on page
	function displayLines(lines,inserts, element, id_preprend){
		var html = "";
		var line_number = 0;
		for (var i = 0;i < lines.length;i++){
			// consoleLog(inserts, i, inserts.indexOf(i));
			if (inserts.indexOf(i) != -1){
				for (var a = inserts.indexOf(i); inserts[a] == i ; a++){
					// html += "Insert<br/>";
					html += "<div class='line missing' id='"+id_preprend+"ins"+i+"'>"+'<tt class="mono"></tt>'+"</div>";
				}

			}


			// FIXME: We should be able to get rid of a bunch of the line index
			//        "+1"s in this file when the newline is removed from
			//        view/homework.php

			var style_id = "#"+id_preprend+"line"+i;
			html += "<div class='line "+(style[style_id] != undefined ? style[style_id] : "")+"' id='"+id_preprend+"line"+i+
				"'><span class='line_number "+(i%2 == 0 ? "" : "odd-line")+
				"'>"+(line_number+1)+"</span>" + '<tt class="mono">';
			if (style[style_id] != undefined) {
				html += lines[i];
			}
			else {
				html += highlight.htmlEntities(lines[i]);
			}
			html += '</tt>' + "</div>";
			line_number ++;
		}
		// element.innerHTML = "<div>" + html + "</div>";
		// element.innerHTML = "<span>"+ html + "</span>";
		element.innerHTML =  html;
	}

	function consoleLog(log) {
		if (DEBUG == true) {
			consoleLog(log);
		}
	}

	return {
		load : load,
		evalDifferences : evalDifferences,
		display : display
	};
}();
