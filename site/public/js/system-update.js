
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
    //collect all of the release notes submitty is behind into one large string
    //release notes are separated with \n-END-\n to help with parsing later
    let updates = '';
    let releases_behind = 0;
    while (releases_behind < data.length && data[releases_behind].tag_name !== current_tag) {
        updates += `# ${data[releases_behind].tag_name}\n${data[releases_behind].body}\n\n-END-\n`;
        releases_behind++;
    }

    const latest = data[0];

    $.ajax({
        url: buildUrl(['markdown']),
        type: 'POST',
        data: {
            content: addPRLinks(updates),
            csrf_token: csrfToken
        },
        success: function(markdown_data) {
            //Now that we have the basic markdown, we have to do some extra work to add functionality into the HTML
            //since normally the markdown is just plain HTML. We can't make an extension off of the markdown engine
            //because these are highly specific changes needed for just this page (plus JQuery is very helpful).

            //split markdown payload by the custom marker we inserted to separate out releases from each other
            const release_notes = markdown_data.split('-END-');

            //due to the splitting of the single markdown payload, there will always be an extra empty release at the
            //end, so don't include it in the loop
            for(let i = 0; i < release_notes.length-1; i++) {
                //have to wrap the output in <div class="markdown"></div> since we are using 1 ajax call to process multiple payloads
                //that way they all come back at the same time & faster
                $('.content').append(`<div class="box release"><div class="markdown">${injectStyling(release_notes[i])}</div></div>`);
            }

            //mark release sections that have no items with a special class
            $('.section').each( (index, section) => {
                if (!$(section).find('li').length && $(section).text().includes('None')) {
                    $(section).addClass('no-content');
                }
            });

            //add collapse buttons to each release
            $('.version-header').append('<button class="btn btn-default btn-toggle-release" onclick="toggleRelease(this, event)">Collapse</button>');
            //collapse all releases
            $('#toggle-releases').addClass('collapsed');
            $('#toggle-releases').trigger('click');

            //initially expand the latest release if there is only 1
            if ($('.btn-toggle-release').length === 1) {
                $('.btn-toggle-release').eq(0).trigger('click');
            }

            //mark security & sysadmin releases with badges
            $('.release').each( (i, release) => {
                const security_notes = $(release).find('.update-security');
                const sysadmin_notes = $(release).find('.update-sysadmin');
                //highlight the collapsed release if it contains sysadmin or security notes
                if (security_notes.length && !security_notes.hasClass('no-content')) {
                    $('<span class="badge red-background">SECURITY</span>').insertAfter($(release).find('.version-header').find('h1'));
                }
                
                if (sysadmin_notes.length && !sysadmin_notes.hasClass('no-content')) {
                    $('<span class="badge red-background">SYSADMIN ACTION</span>').insertAfter($(release).find('.version-header').find('h1'));
                }
            });

            //set info about state of submitty
            $('#tag').html(`Most Recent Version Available: <a href="${latest['html_url']}" target="_blank">${latest['tag_name']}</a>`);
            if (current_tag === latest['tag_name']) {
                $('#text').html('<i>Submitty is up to date!</i>');
            }
            else {
                const important_message = $('.update-important').length > 0 ? `<strong class="important-text"><em>THERE ${$('.update-important').length === 1 ? "IS" : "ARE"} ${$('.update-important').length} SECURITY/SYSADMIN UPDATES</em></strong>` : '';
                $('#text').html(`<a href="${latest['html_url']}" target="_blank">A new version of Submitty is available</a><br>
                                Submitty is ${releases_behind} releases behind.<br>
                                ${important_message}`);
            }
            

            //hide loading text
            $('#loading-text').hide();
        },
        error: function() {
            displayErrorMessage('Something went wrong while trying to render markdown. Please try again.');
        }
    });
}

/**
 * Expands/Collapses all releases collectively. Ex. if the button is in expand mode, all releases
 * will be expanded, even those that are already expanded.
 * @param {HTMLElement} toggleAllButton 
 */
function toggleAllReleases(toggleAllButton) {
    //in this case, collapsed class controls what action the button should take
    //  if toggleAllButton has collapsed - will collapse all
    //  if toggleAllButton doesn't have collapsed - will expand all
    $('.btn-toggle-release').each( (index, button) => {
        if($(toggleAllButton).hasClass('collapsed') != $(button).hasClass('collapsed')) {
            $(button).trigger('click');
        }
    });

    //toggle collapsed class and switch button text accordingly
    $(toggleAllButton).toggleClass('collapsed');
    if($(toggleAllButton).hasClass('collapsed')) {
        $(toggleAllButton).html('Collapse All');
    }
    else {
        $(toggleAllButton).html('Expand All');
    }
}

/**
 * 
 * @param {HTMLElement} button HTMLElement of the button that was clicked to trigger this function
 * @param {Event} event Event context of the click event that triggered this function
 */
function toggleRelease(button, event) {
    const release = $(button).closest('.box');
    $(button).toggleClass('collapsed');

    //COLLAPSE
    if ($(button).hasClass('collapsed')) {
        //hide everything that is not in the header
        release.find(':not(div.markdown, div.version-header, div.version-header *)').hide();
        const security_notes = release.find('.update-security');
        const sysadmin_notes = release.find('.update-sysadmin');
        //highlight the collapsed release if it contains sysadmin or security notes
        if ((security_notes.length && !security_notes.hasClass('no-content')) || (sysadmin_notes.length && !sysadmin_notes.hasClass('no-content'))) {
            release.addClass('update-important');
            $(button).addClass('btn-primary');
            $(button).removeClass('btn-default');
        }
        $(button).html('Expand');
    }
    //EXPAND
    else {
        //show everything that was hidden (not in the header)
        release.find(':not(div.markdown, div.version-header, div.version-header *)').show();
        //important class is only helpful when release is collapsed
        release.removeClass('update-important');
        $(button).removeClass('btn-primary');
        $(button).addClass('btn-default');
        $(button).html('Collapse');
    }
    //stop bubbling of event 
    event.stopPropagation();
}

/**
 * finds all PR numbers in a string and adds markdown formatting so that they will be rendered as links when
 * converted into HTML markdown
 * @param {string} raw_str Pre-markdown processed string
 * @returns {string} String with link formatting in place.
 */
function addPRLinks(raw_str) {
    return raw_str.replace(/#(\d+)/g, '[#$1](https://github.com/Submitty/Submitty/pull/$1)');
}

/**
 * Converts parsed markdown release notes into a more useful form to assist with the other functionalities of the page
 * @param {string} markdown_data HTML string that has been processed and turned into markdown
 * @returns {string} HTML string with helpful wrappers/classes applied
 */
function injectStyling(markdown_data) {
    //add <hr> after each <ul> or <p><em> tag
    markdown_data = markdown_data.replace(/<\/ul>|<\/em><\/p>|<\/a><\/p>/g, '$&<hr>');
    //replace normal <li> contents with spans with classes
    markdown_data = markdown_data.replace(/<li>(\[\w+:(\w+)\].+)(?=<\/li>)|<li>(\[([^\]]+)\].+)(?=<\/li>)/g, (match, p1, p2, p3, p4) => `<li class="release-item"><span class="update-${`${p2}${p4}`.toLowerCase()}}">${p1}${p3}</span>`);
    //add class and wrapper to version headers
    markdown_data = markdown_data.replace(/<h1>.+?<\/h1>/g, `<div class="version-header" onclick="$(this).find('.btn-toggle-release').trigger('click')">$&</div>`);
    //wrap release sections in a <div>
    markdown_data = markdown_data.replace(/<p>([^<\n\s]+)[^<\n]*?[\s\S]+?(?:<hr>)/g, (match, p1) => `<div class="section update-${p1.toLowerCase()}">${match}</div>`);

    return markdown_data;
}

function removeFilterFromHTML(html) {
    return html.replace(/<span class=\"release-filtered\".*?>([\s\S]+?)<\/span>/g, '$1');
}

/**
 * Handles special filter case to save time. All releases/sections/items are shown, all releases are collapsed,
 * and all filter highlighting is removed.
 */
function clearFilter() {
    $('#release-notes-filter').val(''); 
    $('.release').show();
    $('.section').show();
    $('.release-item').show();

    //remove leftover highlighting from release items
    $('.release-item').each( (i, list_item) => {
        const no_filter = removeFilterFromHTML($(list_item).html());
        $(list_item).html(no_filter);
    });
    
    //collapse all
    $('#toggle-releases').addClass('collapsed');
    $('#toggle-releases').trigger('click');
}

/**
 * Searches through release notes and only shows releases/items that contain matches
 * to the given filter string
 * @param {string} filter The substring to filter by
 *
 */
function filterReleaseNotes(filter) {
    //handle special case of empty filter to save time
    if(filter === '') {
        clearFilter();
        return;
    }

    $('#no-filter-results').hide();

    let releases_shown = 0;
    const releases = $('.release');
    releases.each( (i, release) => {
        //get all sections on this release
        const sections = $(release).find('.section');    
        let found_at_least_one = false;
        sections.each( (j, section) => {
            //initially hide the section
            $(section).hide();
            //skip to next section early if there is no content in this one
            if ($(section).hasClass('no-content')) {
                return;
            }
            //if the filter exists in the section's text somewhere
            if($(section).text().toLowerCase().includes(filter.toLowerCase())){
                //show the section and make sure the release that the section is in is shown
                $(section).show();
                $(release).show();

            
                //loop through all release items in section
                $(section).find('.release-item').each( (k, release_item) => {
                    //remove old filter highlighting
                    const no_filter = removeFilterFromHTML($(release_item).html());
                    $(release_item).html(no_filter);

                    //initially hide release item
                    $(release_item).hide();

                    //if the filter text can be found in this release item
                    if($(release_item).text().toLowerCase().includes(filter.toLowerCase())){
                        found_at_least_one = true;
                        releases_shown++;
                        //show the release item
                        $(release_item).show();
                        //expand the release this release item belongs to if it isn't already expanded
                        const button = $(release).find('.btn-toggle-release');
                        if(button.hasClass('collapsed')) {
                            button.trigger('click');
                        }
                        //replace all instances of the filter text that is not inside an html tag's attributes
                        //with a span wrapper for styling
                        const matches = $(release_item).html().replace(new RegExp(`${filter}(?=[^<>]+<)`, 'gi'), '<span class="release-filtered">$&</span>');
                        $(release_item).html(matches);
                    }
                });
            }
        });
        
        //if no release items matched with the filter in this release, hide it
        if(!found_at_least_one) {
            $(release).hide();
        }
    });

    if (releases_shown === 0) {
        $('#no-filter-results').show();
    }
}