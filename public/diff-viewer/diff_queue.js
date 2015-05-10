
// Difference queue to be populated with element tags that will be diff'd
// * Adding "Test1" to this means the following things have happened...
// diff_objects["Test1"] = {difference file}
// there are 2 elements, "Test1_student" and "Test1_instructor" that will
// contain a diff
var diff_queue = [];
var diff_objects = {};


// Go through diff queue and load elements
function loadDiffQueue(){
	for (var i = 0; i < diff_queue.length; i++){

		var title = diff_queue[i];

		var student_element_id = title+"_student";
		var instructor_element_id = title+"_instructor";

		var student_element = document.getElementById(student_element_id).getElementsByTagName( 'tt' )[0];
		var instructor_element = document.getElementById(instructor_element_id).getElementsByTagName( 'tt' )[0];


		diff.load($(student_element).text(),$(instructor_element).text());
		consoleLog("STUDENT_ELEMENT");
		consoleLog(student_element.innerHTML);
		consoleLog("INSTRUCTOR_ELEMENT");
		consoleLog(instructor_element.innerHTML);

		student_element.innerHTML = "";
		instructor_element.innerHTML = "";

		diff.evalDifferences(diff_objects[title]["differences"]);
		diff.display(student_element_id.split(' ').join('\\ '),instructor_element_id.split(' ').join('\\ '));
	}
}

function consoleLog(log) {
	if (DEBUG == true) {
		consoleLog(log);
	}
}
// .getElementsByTagName( 'tt' )[0];
