
function init(){
	animateAssignmentBars();
}

function animateAssignmentBars(){

	var total = 25;
	var score = 18;
	// var taleft = 25;
	var lost = 6;
	var total_size = 100;

	var r = d3.select("#assignment-graph-red");
	var g = d3.select("#assignment-graph-green");
	var y = d3.select("#assignment-graph-yellow");

	g.transition().style("width",score / total * total_size + "%");
	r.transition().style("width", lost / total * total_size + "%");
	// y.transition().style("width", taleft / total * total_size + "%");
}





window.onload = init;