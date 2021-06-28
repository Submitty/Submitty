
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
    current_tag = "v20.10.00";
    let updates = '';
    let i = 0;
    while (i < data.length && data[i].tag_name !== current_tag) {
        updates += (`# ${data[i].tag_name}\n${data[i].body}-END-\n`);
        i++;
    }

    console.log('updates', updates);

    $('.release-notes').remove();

    const latest = data[0];

    $.ajax({
        url: buildUrl(['markdown']),
        type: 'POST',
        data: {
            content: addPRLinks(updates),
            csrf_token: csrfToken
        },
        success: function(markdown_data){
            const release_notes = markdown_data.split('-END-');
            console.log('release_notes', release_notes);

            for(let release of release_notes) {
                //have to wrap the output in <div class="markdown"></div> since we are using 1 ajax call to process multiple payloads
                //that way they all come back at the same time
                $('.content').append(`<div class="box"><div class="markdown">${injectStyling(release)}</div></div>`);
            }

            //$('#release-notes').html(injectStyling(markdown_data));
            $('div.update').each( (index, div) => {
                if ($(div).text().includes('None')) {
                    $(div).addClass('no-content');
                }
            })
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
    return raw_str.replace(/#(\d+)/g, '[#$1](https://github.com/Submitty/Submitty/pull/$1)');
}

function injectStyling(markdown_data) {
    //add <hr> after each ul or <p><em> tag
    markdown_data = markdown_data.replace(/<\/ul>|<\/em><\/p>/g, '$&<hr>');
    //replace normal li contents with spans with classes
    markdown_data = markdown_data.replace(/\[\w+:(\w+)\].+(?=<\/li>)|\[([^\]]+)\].+(?=<\/li>)/g, '<span class="update update-$1$2">$&</span>');
    //add class to version headers
    markdown_data = markdown_data.replace(/<h1/g, '$& class="version-header"');
    //add content wrapper
    //markdown_data = markdown_data.replace(/<\/h1>([\s\S]+?)(?=<hr>)/g)
    
    // //special highlighting for SYSADMIN updates only if there are any
    // if ((markdown_data.match(/\[SYSADMIN ACTION\]/g) || []).length) {
    //     markdown_data = markdown_data.replace(/<p>SYSADMIN[\s\S]+?(?:<hr>)/g, '<div class="update-SYSADMIN">$&</div>');
    // }

    // if ((markdown_data.match(/\[SECURITY\]/g) || []).length) {
    //     markdown_data = markdown_data.replace(/<p>SECURITY[\s\S]+?(?:<hr>)/g, '<div class="update-SECURITY">$&</div>');
    // }
    // //special highlighting for SECURITY updates only if there are any
    // if ((markdown_data.match(/\[SECURITY\]/g) || []).length) {
    markdown_data = markdown_data.replace(/<p>([^<\n]+)[\s\S]+?(?:<hr>)/g, '<div class="update update-$1">$&</div>');
    //}
    return markdown_data;
}

function mergeReleaseNotes(accumulated, current) {
    let split = current.body.split('\r\n\r\n').slice(1);
    let last_section = split[0];
    for (let i = 0; i < split.length; i++) {
        if(!accumulated.hasOwnProperty(last_section)) {
            accumulated[last_section] = [];
        }
        while(isReleaseItem(split[i])) {
            if(!split[i].includes("None")){
                accumulated[last_section].push(split[i]);
            }
            i++;
        }
        last_section = split[i];
    }
    return accumulated;
}

function isReleaseItem(string) {
    return string && string[0] === '*';
}

function updatesToNotes(merged_updates) {
    let release_notes = '';
    release_notes += `${merged_updates["SYSADMIN ACTION / BREAKING CHANGE"]}\n\n${merged_updates["SYSADMIN ACTION / BREAKING CHANGE"].join('\n')}\n\n`;
    release_notes += `${merged_updates["SECURITY"]}\n\n${merged_updates["SECURITY"].join('\n')}\n\n`;
    for(let section in merged_updates) {
        release_notes += `${section}\n\n${merged_updates[section].join('\n')}\n\n`;
    }
    return release_notes;
}

function filterReleaseNotes(filter) {
    console.log('filter', filter)
    const sections = $('div.update');    
    sections.each( (index, section) => {
        $(section).hide();
        if($(section).text().toLowerCase().includes(filter.toLowerCase())){
            $(section).show();
            $(section).find('li').each( (index, list_item) => {
                //remove old filter highlighting
                const no_filter = $(list_item).html().replace(/<span class=\"release-filtered\">([\s\S]+?)<\/span>/g, '$1');
                $(list_item).html(no_filter);
                $(list_item).hide();
                if(!filter) {
                    $(list_item).show();
                }
                if(filter && $(list_item).text().toLowerCase().includes(filter.toLowerCase())){
                    $(list_item).show();
                    //replace all instances of the filter text that is not inside an html tag's attributes
                    //with a span wrapper for styling
                    const matches = $(list_item).html().replace(new RegExp(`${filter}(?=[^<>]+<)`, 'gi'), '<span class="release-filtered">$&</span>');
                    $(list_item).html(matches);
                }
            });
        }

    });
}