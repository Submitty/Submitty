function ExtractBuckets(){
    var x = [];
    var bucket_list = $("#buckets_used_list").find("li");
    bucket_list.each(function(idx,li){
        x.push($(li).text());
    });

    $("#generate_json").val(JSON.stringify(x));
    $("#custom_form").submit();
}

//Forces element's value to be non-negative
function ClampPoints(el){
    el.value = Math.max(0.0,el.value);
}

function ExtractBucketName(s,offset){
    var tmp = s.split("-");
    var bucket = "";
    var i;
    for(i=offset;i<tmp.length; i++){
        if(i>offset){
            bucket += "-";
        }
        bucket += tmp[i];
    }
    return bucket;
}

//Forces element's value to be in range [0.0,100.0]
function ClampPercent(el){
    el.value = Math.min(Math.max(el.value,0.0),100.0);
    UpdateUsedPercentage();
    $("#config-percent-"+ExtractBucketName(el.id,1)).text(el.value + "%");
}

//Updates the sum of percentage points accounted for by the buckets being used
function UpdateUsedPercentage(){
    var val = 0.0;
    $("input[id^='percent']").filter(function(){
        return $(this).parent().css("display") !== "none";
    }).each(function(){
        val += parseFloat($(this).val());
    });
    var percentage_span = $("#used_percentage");
    percentage_span.text(val.toString() + "%");
    if(val>100.0){
        percentage_span.css({"color":"red","font-weight":"bold"});
    }
    else{
        percentage_span.css({"color":"black","font-weight":""});
    }
}

//Updates which buckets have full configuration shown (inc. each gradeable), and the ordering
function UpdateVisibilityBuckets(){
    //For each bucket that isn't being used, hide it
    $("#buckets_available_list").find("input").each(function() {
        //Extract the bucket name
        var bucket = ExtractBucketName($(this).attr("id"),1);
        $("#config-"+bucket).css("display","none");
    });

    //For each bucket that IS being used, show it
    var used_buckets = $("#buckets_used_list").find("input");
    if(used_buckets.length === 0){
        return;
    }
    var prev_bucket = ExtractBucketName(used_buckets.first().attr("id"),1);
    $("#config-"+prev_bucket).prependTo("#config-wrapper").css("display","block");

    used_buckets.each(function() {
        //Extract the bucket name
        var bucket = ExtractBucketName($(this).attr("id"),1);
        console.log("prev_bucket: " + prev_bucket + " bucket: " + bucket);
        if(bucket !== prev_bucket) {
            $("#config-" + bucket).css("display", "block");
            $("#config-" + prev_bucket).after($("#config-"+bucket));
            prev_bucket = bucket;
        }
    });
}

function getSection()
{
    // Collect sections and labels
    var sections = {};

    $.each($("input[class='sections_and_labels']"), function(){

        // Get data
        var section = this.getAttribute('data-section').toString();
        var label = this.value;

        if(label === "")
        {
            throw "All sections MUST have a label before saving"
        }

        // Add to sections
        sections[section] = label;
    });

    return sections;
}

function getDisplayBenchmark()
{
    // Collect display benchmarks
    var display_benchmarks = [];

    $.each($("input[name='display_benchmarks']:checked"), function(){
        display_benchmarks.push($(this).val());
    });

    return display_benchmarks;
}

function getGradeableBuckets()
{
    // Collect gradeable buckets
    var gradeables = [];
    $('.bucket_detail_div').each(function() {

        // Only use buckets which have display block
        // This works even if outer container is collapsed
        if($(this).css("display")=="block"){
            var bucket = {};

            // Extract bucket-type
            var type = $('#' + this.id + ' h3');
            type = type[0].innerHTML.toLowerCase();
            bucket.type = type;

            // Extract count
            var count = $('#config-count-' + type).val();
            bucket.count = parseInt(count);

            // Extract percent
            var percent = $('#percent-' + type).val();
            percent = percent / 100;
            bucket.percent = percent;

            // Extract each independent gradeable in the bucket
            var ids = [];
            var selector = '#gradeables-list-' + type + ' li';
            $(selector).each(function() {

                var gradeable = {};

                var children = $(this).children();

                // Get max points
                gradeable.max = parseFloat(children[0].value);

                // Get gradeable id
                gradeable.id = children[1].innerHTML;

                ids.push(gradeable);
            });

            // Add gradeable buckets to gradeables array
            bucket.ids = ids;

            // Add to the gradeables array
            gradeables.push(bucket);
        }
    });

    return gradeables
}

function getMessages()
{
    var messages = [];

    var message = $('#cust_messages_textarea').val();

    if(message)
    {
        messages.push(message);
    }

    return messages;
}

// This function constructs a JSON representation of all the form input
function buildJSON(){

    // Build the overall json
    let ret = {
        'display_benchmark': getDisplayBenchmark(),
        'section' : getSection(),
        'gradeables' : getGradeableBuckets(),
        'messages' : getMessages()
    };

    ret = JSON.stringify(ret);

    return ret;
}

function showLogButton(responseData)
{
    $('#show_log_button').show();
    $('#save_status_log').empty();
    $('#save_status_log').append('<pre>' + responseData + '</pre>');
}

function checkAutoRGStatus()
{
    // Send request
    $.getJSON({
        type: "POST",
        url: buildNewCourseUrl(['auto_rg_status']),
        data: {csrf_token: csrfToken},
        success: function (response) {
            if (response.status === 'success') {

                $('#save_status').html('Rainbow grades successfully generated!');
                showLogButton(response.data);

            } else if (response.status === 'fail') {

                $('#save_status').html('A failure occurred generating rainbow grades');
                showLogButton(response.message);

            } else {

                $('#save_status').html('Internal Server Error');
                console.log(response);

            }
        },
        error: function (response) {
            console.error('Failed to parse response from server: ' + response);
        }
    });
}

//This function attempts to create a new customization.json server-side based on form input
function ajaxUpdateJSON(successCallback, errorCallback) {

    try
    {
        $('#save_status').html('Saving...');

        var url = buildNewCourseUrl(['rainbow_grades_customization']);

        $.getJSON({
            type: "POST",
            url: url,
            data: {json_string: buildJSON(), csrf_token: csrfToken},
            success: function (response) {
                if (response.status === 'success') {
                    $('#save_status').html('Generating rainbow grades, please wait...');

                    // Call the server to see if auto_rainbow_grades has completed
                    checkAutoRGStatus();
                    //successCallback(response.data);
                } else if (response.status === 'fail') {
                    $('#save_status').html('A failure occurred saving customization data');
                    //errorCallback(response.message, response.data);
                } else {
                    $('#save_status').html('Internal Server Error');
                    console.error(response.message);
                }
            },
            error: function (response) {
                console.error('Failed to parse response from server: ' + response);
            }
        });
    }
    catch(err)
    {
        $('#save_status').html(err);
    }

}

function displayChangeDetectedMessage()
{
    $('#save_status').html('Changes detected, press "Save Changes" to save them.');
}

$(document).ready(function () {

    // Setup click handlers to handle collapsing and expanding each item
    $('#display_benchmarks h2').click(function() {
        $('#display_benchmarks_collapse').toggle();
    });

    $('#section_labels h2').click(function() {
        $('#section_labels_collapse').toggle();
    });

    $('#gradeables h2').click(function() {
        $('#gradeables_collapse').toggle();
    });

    $('#cust_messages h2').click(function() {
        $('#cust_messages_collapse').toggle();
    });

    // Register change handlers to update the status message when form inputs change
    $("input[name*='display_benchmarks']").change(function() {
       displayChangeDetectedMessage();
    });

    $('#cust_messages_textarea').on("change keyup paste", function() {
        displayChangeDetectedMessage();
    });

    $('.sections_and_labels').on("change keyup paste", function() {
        displayChangeDetectedMessage();
    });

    // https://stackoverflow.com/questions/15657686/jquery-event-detect-changes-to-the-html-text-of-a-div
    // More Details https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver
    // select the target node
    var target = document.querySelector('#buckets_used_list');
    // create an observer instance
    var observer = new MutationObserver(function(mutations) {
        displayChangeDetectedMessage();
    });
    // configuration of the observer:
    var config = { attributes: true, childList: true, characterData: true };
    // pass in the target node, as well as the observer options
    observer.observe(target, config);

    // Display auto rainbow grades log on button click
    $('#show_log_button').click(function() {
        $('#save_status_log').toggle();
    })
});
