const benchmarks_with_input_fields = ['lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d'];

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
        percentage_span.css({"color":"var(--text-black)","font-weight":""});
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

/**
 * From the set of Display Benchmarks determine which ones are
 * selected that are part of the subset
 * ['lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d']
 *
 * @returns {[]}
 */
function getSelectedCurveBenchmarks()
{
    let all_selected_benchmarks = getDisplayBenchmark();
    let result_set = [];

    all_selected_benchmarks.forEach(function(elem) {
       if(benchmarks_with_input_fields.includes(elem)) {
           result_set.push(elem);
       }
    });

    return result_set;
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

                // Get gradeable release date
                gradeable.release_date = children[0].dataset.gradeReleaseDate;

                // Get gradeable id
                gradeable.id = $(children).find(".gradeable-id")[0].innerHTML;

                // Get per-gradeable curve data
                let curve_points_selected = getSelectedCurveBenchmarks()

                $(children).find(".gradeable-li-curve input").each(function() {

                    var benchmark = this.getAttribute('data-benchmark').toString();

                    if(curve_points_selected.includes(benchmark) && this.value)
                    {
                        if(!gradeable.hasOwnProperty('curve')) {
                            gradeable.curve = [];
                        }

                        gradeable.curve.push(parseFloat(this.value));
                    }
                });

                // Validate the set of per-gradeable curve values
                if(gradeable.hasOwnProperty('curve')) {

                    // Has correct number of values
                    if(gradeable.curve.length !== curve_points_selected.length) {
                        throw "To adjust the curve for gradeable " + gradeable.id + " you must enter a value in each box";
                    }

                    var previous = gradeable.max;
                    gradeable.curve.forEach(function(elem) {

                        elem = parseFloat(elem);

                        // All values are floats
                        if(isNaN(elem)) {
                            throw "All curve inputs for gradeable " + gradeable.id + " must be floating point values";
                        }

                        // Each value is greater than 0
                        if(elem < 0) {
                            throw "All curve inputs for gradeable " + gradeable.id + " must be greater than or equal to 0";
                        }

                        // Each value is less than the previous
                        if(elem > previous) {
                            throw "All curve inputs for gradeable " + gradeable.id + " must be less than or equal to the maximum points for the gradeable and also less than or equal to the previous input"
                        }

                        previous = elem;
                    })
                }

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

function getBenchmarkPercent()
{
    // Collect benchmark percents
    var benchmark_percent = {};
    let selected_benchmarks = getSelectedCurveBenchmarks();

    $('.benchmark_percent_input').each(function() {

        // Get data
        var benchmark = this.getAttribute('data-benchmark').toString();
        var percent = this.value;

        if(selected_benchmarks.includes(benchmark)) {

            // Verify percent is not empty
            if(percent === "")
            {
                throw "All benchmark percents must have a value before saving."
            }

            // Verify percent is a floating point number
            if(isNaN(parseFloat(percent)))
            {
                throw "Benchmark percent input must be a floating point number."
            }

            // Add to sections
            benchmark_percent[benchmark] = percent;

        }
    });

    return benchmark_percent;
}

// This function constructs a JSON representation of all the form input
function buildJSON(){

    // Build the overall json
    let ret = {
        'display_benchmark': getDisplayBenchmark(),
        'benchmark_percent': getBenchmarkPercent(),
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
        url: buildCourseUrl(['reports', 'rainbow_grades_status']),
        data: {csrf_token: csrfToken},
        success: function (response) {
            if (response.status === 'success') {

                $('#save_status').html('Rainbow grades successfully generated!');
                showLogButton(response.data);

            }
            else if (response.status === 'fail') {

                $('#save_status').html('A failure occurred generating rainbow grades');
                showLogButton(response.message);

            }
            else {

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

        var url = buildCourseUrl(['reports', 'rainbow_grades_customization']);

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
                }
                else if (response.status === 'fail') {
                    $('#save_status').html('A failure occurred saving customization data');
                    //errorCallback(response.message, response.data);
                }
                else {
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

/**
 * Sets the visibility for 'benchmark percent' input boxes and also per-gradeable curve input boxes
 * based upon boxes in 'display benchmark' being selected / un-selected
 *
 * @param elem The checkbox input element captured from 'display benchmark'
 */
function setInputsVisibility(elem)
{
    let benchmark = elem.value;
    let is_checked = elem.checked;

    // Only care about inputs which are part of the benchmarks_with_input_fields
    if(benchmarks_with_input_fields.includes(benchmark)) {
        if(is_checked) {
            $('.' + benchmark).show();
        } else {
            $('.' + benchmark).hide();
        }
    }

    // If all boxes are unchecked can hide benchmark percent box and all per-gradeable curve options
    if(getSelectedCurveBenchmarks().length === 0) {
        $('#benchmark_percents').hide();
        $('.fa-gradeable-curve').hide();
        $('.gradeable-li-curve').hide();
    } else {
        $('#benchmark_percents').show();
        $('.fa-gradeable-curve').show();
    }
}

$(document).ready(function () {

    // Setup click handlers to handle collapsing and expanding each item
    $('#display_benchmarks h2').click(function() {
        $('#display_benchmarks_collapse').toggle();
    });

    $('#benchmark_percents h2').click(function() {
        $('#benchmark_percents_collapse').toggle();
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

    // Make the per-gradeable curve inputs toggle when the icon is clicked
    $('.fa-gradeable-curve').click(function(event) {
        var id = jQuery(this).attr("id").split('-')[3];
        $('#gradeable-curve-div-' + id).toggle();
    });

    // By default, open the input fields for per-gradable curves which have been previously set
    $('.gradeable-li-curve').each(function() {

        let has_at_least_one_value = false;

        // Determine if any of the input boxes had a value pre-loaded into them
        $(this).children('input').each(function() {
           if(this.value) {
               has_at_least_one_value = true;
           }
        });

        // If so then open the per-gradeable curve input div
        if(has_at_least_one_value) {
            var id = jQuery(this).attr("id").split('-')[3];
            $('#gradeable-curve-div-' + id).toggle();
        }
    })

    /**
     * Configure visibility handlers for curve input boxes
     * Curve input boxes include the benchmark percent input boxes and also the per-gradeable curve input boxes
     * Visibility is controlled by which boxes are selected in the display benchmarks area
     */
    $('#display_benchmarks_collapse input').each(function() {

        // Set the initial visibility on load
        setInputsVisibility(this);

        // Register a click handler to adjust visibility when boxes are selected / un-selected
        $(this).change(function() {
           setInputsVisibility(this);
        });

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
    });

    // Hide the loading div and display the form once all form configuration is complete
    $(document).ready(function() {
        $('#rg_web_ui_loading').hide();
        $('#rg_web_ui').show();
    });
});
