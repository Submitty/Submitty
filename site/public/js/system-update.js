
/**
* get information about Submitty's releases from the GitHub API
*
* @param {String} current_tag - tag Submitty is currently running on
*/
async function getReleases(current_tag) {

    const text = document.getElementById("text");

    try{
        const response = await fetch("https://api.github.com/repos/Submitty/Submitty/releases");

        if(response.status !== 200){
            displayMessage(" Failed to get latest version info." + 
                " (Status Code : " + response.status.toString() + ")<br>" +
                "Message : " + response.statusText , "error"
            );
            console.error("Got bad response:", response);
        }else{
            response.json().then(data => {
                updateReleaseNotes(data, current_tag)
            });
        }
    }catch(error){
        console.error(error);
        displayMessage(error.toString(), "error");
    }
}


/**
* Update the page with latest available release and its release notes
*
* @param {Array} data - response from GitHub API, array of last few releases 
* @param {String} current_tag - tag Submitty is currently running on
*/
function updateReleaseNotes(data, current_tag){
    const latest = data[0];
    let tag = document.getElementById("tag");
    let container = document.getElementById("update_status");
    container.style.display = "block";
    tag.innerHTML = "Most Recent Version Available: " + latest["tag_name"];

    let text = document.getElementById("text");
    if(current_tag === latest["tag_name"]){
        text.innerHTML = "<i>Submitty is up to date!</i>";
    }else{
        text.innerHTML = "<a href = \""+ latest["html_url"] + "\"" +
            "target = \"_blank\"" +  
            "A new version of Submitty is available<a/>" ;
    }

    let notes = document.getElementById("release-notes");
    notes.style.display = "block";
    notes.innerHTML = parseReleaseNotes(latest["body"]);
}


/**
* Helper function to clean up some raw markdown before getting displayed
*
* @param {String} raw_str - raw markdown text 
* @returns {String}
*/
function parseReleaseNotes(raw_str) {
    return raw_str.replace(/\n/g, "<br />").replace(/\r/g, "").replace("*", "").replace(RegExp("\[(.*?)\]"), "");
}