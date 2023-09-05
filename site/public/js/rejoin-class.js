
async function rejoinCourse(readd_url) {
	console.log(readd_url);
	$.ajax({
    url: readd_url,
    type: "POST",
    success: function(data) {
    	console.log(data["Success"]);
    	return true;
    },
    error: function(e) {
      return false;
    }
  });
}