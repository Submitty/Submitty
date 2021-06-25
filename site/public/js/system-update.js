
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
    const latest = data[data.length-1];

    $.ajax({
        url: buildUrl(['markdown']),
        type: 'POST',
        data: {
            content: addPRLinks(latest["body"]),
            csrf_token: csrfToken
        },
        success: function(markdown_data){
            $('#release-notes').css('display', 'block');
            $('#release-notes').html(injectStyling(markdown_data));
            $('#tag').html(`Most Recent Version Available: <a href="${latest['html_url']}" target="_blank">${latest['tag_name']}</a>`);
            if (current_tag === latest['tag_name']) {
                $('#text').html('<i>Submitty is up to date!</i>');
            }
            else {
                $('#text').html(`<a href="${latest['html_url']}" target="_blank">A new version of Submitty is available</a>`);
            }
        },
        error: function() {
            displayErrorMessage('Something went wrong while trying to render markdown. Please try again.');
        }
    });
}

function addPRLinks(raw_str) {
    return raw_str.replace(/#(\d+)/g, '[#$1](https://github.com/Submitty/Submitty/pulls/$1)');
}

function injectStyling(markdown_data) {
    //add <hr> after each ul or <p><em> tag
    markdown_data = markdown_data.replace(/<\/ul>|<\/em><\/p>/g, '$&<hr>');
    console.log(markdown_data);
    //replace normal li contents with spans with classes
    //markdown_data = markdown_data.replace(/\[\w+:(\w+)\].+(?=<\/li>)|\[(\w+)\].+(?=<\/li>)/g, '<span class="update update-$1$2">$&</span>');
    markdown_data = markdown_data.replace(/\[\w+:(\w+)\].+(?=<\/li>)|\[([^\]]+)\].+(?=<\/li>)/g, '<span class="update update-$1$2">$&</span>');
    //special highlighting for SYSADMIN updates only if there are any
    if ((markdown_data.match(/\[SYSADMIN ACTION\]/g) || []).length) {
        markdown_data = markdown_data.replace(/<p>SYSADMIN[\s\S]+?(?:<hr>)/g, '<div class="update-SYSADMIN">$&</div>');
    }
    return markdown_data;
}
