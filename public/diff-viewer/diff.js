
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
		style = [];
		assocs = [];
	}

	// Utility function for a replaceAll string function
	// returns string with the "find" regex replaced with "replace"
	function replaceAll(str, find, replace) {
		return str.replace(new RegExp(find, 'g'), replace);
	}

	// Load files to diff
	function load(file0, file1){
		init();

		// TODO escape all html characters
		file0 = replaceAll(file0, "&nbsp;", " ");
		file1 = replaceAll(file1, "&nbsp;", " ");

		f0 = file0.split("\n");
		f1 = file1.split("\n");

		for (var i = 0;i < f0.length;i++){
			if (f0[i] == "" && f1[i] == ""){
				f0.splice(0,1);
				f1.splice(0,1);
				break;
			}
		}
	}

	// Evaluate differences JSON object
	function evalDifferences(differences){
		for (var i = 0;i < differences.length;i++){

			var student = differences[i].student;
			var instructor = differences[i].instructor;

			assocs.push([]);
			markDifference(f0,ins0,student,i,"stu_");
			markDifference(f1,ins1,instructor,i,"ist_");

		}
	}

	// Change a line specified by difference object
	function markDifference(lines,ins, difference, changeID,id_prepend){
		assocs[changeID] = (assocs[changeID] || []);
		if (difference.line!== undefined){
			var changes = difference.line;
			for (var i = 0;i < changes.length;i++){
				var change = changes[i];
				var line_id = "#" + id_prepend + "line" + change.line_number;
				if (change.line_number !== undefined){
					if (!change.word_number){
						console.log(id_prepend,"Bad line at ",change.line_number);
						style.push([line_id, "bad-line"]);
						assocs[changeID].push(line_id);
					}else{
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
				}else{
					console.log("ERROR: NO LINE NUMBER IN DIFFERENCE");
				}
			}
		}else if (difference.start !== undefined){

// FIXME: We should be able to get rid of a bunch of the line index
//        "+1"s in this file when the newline is removed from
//        view/homework.php

			console.log(id_prepend,"Insert line at ",difference.start+1);
			// difference.start += ins.length;
			ins.push(difference.start+1);
			// console.log("#" + id_prepend + "ins" + difference.start);
			assocs[changeID].push("#" + id_prepend + "ins" + (difference.start+1));
		}else{
			console.log("Couldn't interpret difference : ",difference);
		}
	}

	// Display files on page
	function display(first_diff_tag, second_diff_tag){

		// Display lines from diff
		displayLines(f0,ins0,document.getElementById(first_diff_tag),"stu_");
		displayLines(f1,ins1,document.getElementById(second_diff_tag),"ist_");

		// Load generated css
		for (var i = 0;i < style.length;i++){
			console.log(style[i]);
			$('#' + first_diff_tag + ' > > ' + style[i][0]).addClass(style[i][1]);
			$('#' + second_diff_tag + ' > > ' + style[i][0]).addClass(style[i][1]);
		}

		// Create association events
		for (var i = 0;i < assocs.length;i++){
			var selectors = assocs[i];
			setup_line_hover(selectors, first_diff_tag, second_diff_tag);
		}
	}

	// Setup line selectors for hover event
	function setup_line_hover(selectors, first_diff_tag, second_diff_tag){
		// Function called when group is hovered over
		var event_function_hover_on = function(e){
			for (var u = 0;u<selectors.length;u++){
				$('#' + first_diff_tag + ' > > ' + selectors[u]).addClass("line-hover");
				$('#' + second_diff_tag + ' > > ' + selectors[u]).addClass("line-hover");
			}
		};
		// Function called when group is no longer hovered over
		var event_function_hover_off = function(e){
			for (var u = 0;u<selectors.length;u++){
				$('#' + first_diff_tag + ' > > ' + selectors[u]).removeClass("line-hover");
				$('#' + second_diff_tag + ' > > ' + selectors[u]).removeClass("line-hover");
			}
		};
		// Set each elements event
		for (var u = 0;u < selectors.length;u++){
			$('#' + first_diff_tag + ' > > ' + selectors[u]).hover(event_function_hover_on,
				event_function_hover_off);
			$('#' + second_diff_tag + ' > > ' + selectors[u]).hover(event_function_hover_on,
				event_function_hover_off);
		}
	}

	// Put lines in element on page
	function displayLines(lines,inserts, element, id_preprend){
		var html = "";
		var line_number = 0;
		for (var i = 0;i < lines.length;i++){
			if (inserts.indexOf(i) != -1){
				// html += "Insert<br/>";
				html += "<div class='line missing' id='"+id_preprend+"ins"+i+"'></div>";
			}


// FIXME: We should be able to get rid of a bunch of the line index
//        "+1"s in this file when the newline is removed from
//        view/homework.php


			html += "<div class='line' id='"+id_preprend+"line"+i+
				"'><span class='line_number "+(i%2 == 0 ? "" : "odd-line")+
				"'>"+(line_number+1)+"</span>" + lines[i] + "</div>";
			line_number ++;
		}
		element.innerHTML = "<pre>" + html + "</pre>";
	}

	return {
		load : load,
		evalDifferences : evalDifferences,
		display : display
	};
}();
