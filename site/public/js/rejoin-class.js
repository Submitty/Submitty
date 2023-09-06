
async function rejoinCourse(readd_url) {
    console.log(readd_url);
    console.log(csrfToken);
	$.ajax({
    type: "POST",    
    url: readd_url,
    data: {
        'csrf_token': csrfToken
    },
    success: function(data) {
        console.log("We're in the success block.");
        console.log(data["message"]);
    	return true;
    },
    error: function(e) {
      return false;
    }
  });
}