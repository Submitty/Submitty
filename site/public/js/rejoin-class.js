
async function rejoinCourse(readd_url) {
	$.ajax({
    type: "POST",    
    url: readd_url,
    data: {
        'csrf_token': csrfToken
    },
    success: function(data) {
    	return true;
    },
    error: function(e) {
      return false;
    }
  });
}