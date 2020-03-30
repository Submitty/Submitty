
function checkDockerInterfaceJobs(){
	return new Promise(function (resolve, reject) {
	   	$.ajax({
	        url: buildUrl(['admin', 'docker', 'check_jobs']),
	        success: function (response) {
	        	response = JSON.parse(response);
	        	resolve( response['data']['found'] );
	        },
	        error: function (response) {
	            console.error(response);
	            reject(false);
	        }
	    });
   	});
}

//sends a request to update the interface and starts polling for its status
function updateDockerData(){
	document.getElementById("docker-update-info").innerHTML = "Processing job, please wait";
	$.ajax({
        url: buildUrl(['admin', 'docker', 'update']),
        success: function (response) {
            let interval = setInterval(function(){
            	checkDockerInterfaceJobs().then( response => {
					console.log(response);
					if(response){
						document.getElementById("docker-update-info").innerHTML = "Processing job, please wait";
					}else{
						location.reload();
					}
				});
            },3000);
        },
        error: function (response) {
            console.error(response);
        }
    });
}

$(document).ready(function() {
	let interval = setInterval(function(){
		checkDockerInterfaceJobs().then( response => {
			if(response){
				document.getElementById("docker-update-info").innerHTML = "Processing job, please refresh the page";
			}
		});
    },3000);
});
