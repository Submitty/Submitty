<?php
use \models\User;
use \lib\Database;

include "../header.php";

$account_subpages_unlock = true;

function getGrades($g_id)
{
    $query = "SELECT
  youser.user_id,
  user_firstname,
  user_preferred_firstname,
  user_lastname,
  section,
  manual_registration,
  youser.g_id,
  g_title,
  g_grade_by_registration,
  g_syllabus_bucket,
  coalesce(sum(gc_max_value), 0) AS max_score,
  coalesce(sum(gcd_score), 0)    AS ta_points,
  coalesce(active_version, -1)   AS active_version,
  coalesce(gd_grader_id, '')     AS gd_grader_id --Coalescing to '' so I can detect unviewed gradeables
FROM
  (SELECT
     user_id,
     user_firstname,
     user_preferred_firstname,
     user_lastname,
     CASE WHEN g_grade_by_registration
       THEN registration_section
     ELSE rotating_section END AS section,
     manual_registration,
     g.g_id,
     g_title,
     g_grade_by_registration,
     g_syllabus_bucket,
     gc_max_value,
     gc_is_extra_credit,
     gc_id
   FROM
     gradeable g
     FULL JOIN gradeable_component gc ON gc.g_id = g.g_id
     , users
   WHERE g.g_id = ?
   GROUP BY
     user_id,
     user_firstname,
     user_preferred_firstname,
     user_lastname,
     g_grade_by_registration,
     registration_section,
     rotating_section,
     manual_registration,
     g.g_id,
     g_title,
     g_grade_by_registration,
     g_syllabus_bucket,
     gc_max_value,
     gc_is_extra_credit,
     gc_id
  ) AS youser
  FULL JOIN
  electronic_gradeable_version egv ON egv.g_id = youser.g_id AND egv.user_id = youser.user_id
  FULL JOIN
  (SELECT
     gd_user_id,
     g.g_id,
     gcd_score,
     gd_grader_id,
     gc_id
   FROM
     gradeable g
     FULL JOIN
     gradeable_data gd ON g.g_id = gd.g_id
     FULL JOIN gradeable_component_data gcd ON gd.gd_id = gcd.gd_id) AS grdbl
    ON youser.g_id = grdbl.g_id AND youser.user_id = grdbl.gd_user_id AND youser.gc_id = grdbl.gc_id
WHERE (NOT gc_is_extra_credit OR gc_is_extra_credit IS NULL)
      AND youser.user_id IS NOT NULL
GROUP BY
  youser.user_id,
  user_firstname,
  user_preferred_firstname,
  user_lastname,
  manual_registration,
  youser.g_id,
  g_title,
  g_grade_by_registration,
  g_syllabus_bucket,
  section,
  active_version,
  gd_grader_id
ORDER BY
  section
  , youser.user_id
  , user_lastname
  , user_firstname;
";

    Database::query($query, array($g_id));
    return Database::rows();
}

function getAutogradingMax($g_id)
{
    $total = 0;
    $build_file = __SUBMISSION_SERVER__ . "/config/build/build_" . $g_id . ".json";
    if (file_exists($build_file)) {
        $build_file_contents = file_get_contents($build_file);
        $results = json_decode($build_file_contents, true);
        if (isset($results['testcases']) && count($results['testcases']) > 0) {
            foreach ($results['testcases'] as $testcase) {
                $testcase_value = floatval($testcase['points']);
                if ($testcase_value > 0 && !$testcase['extra_credit']) {
                    $total += $testcase_value;
                }
            }
        }
    }

    return $total;
}

function getAutogradingPoints($g_id, $user_id, $active_version)
{
    $results_file = implode("/", array(__SUBMISSION_SERVER__, "results",
        $g_id, $user_id, $active_version, "results.json"));

    if (!file_exists($results_file)) {
        return 0;
    }

    $details = json_decode(file_get_contents($results_file), true);

    $autograding_points = 0;
    if (isset($details['testcases'])) {
        foreach ($details['testcases'] as $testcase) {
            //FIXME this won't work for extra credit auto-grading
            if (isset($testcase['points_awarded'])) {
                $autograding_points += $testcase['points_awarded'];
            }
        }
    }

    return $autograding_points;
}

function getSectionsRegistration($user_id)
{
    $query = "SELECT sections_registration_id FROM grading_registration WHERE user_id=? ORDER BY sections_registration_id;";

    Database::query($query, array($user_id));
    return Database::rows();
}

function getSectionsRotating($user_id, $g_id)
{
    $query = "SELECT sections_rotating_id FROM grading_rotating WHERE user_id=? AND g_id=? ORDER BY sections_rotating_id";

    Database::query($query, array($user_id, $g_id));
    return Database::rows();

}

function parseSections($grades, $g_id, $autograding_max)
{
    $sections = array();

    foreach ($grades as $grade) {
        $section = $grade['section'];

        //Assign a name of 'NULL' to null section
        if (empty($section) || is_null($section)) {
            $section = 'NULL';
        }

        if (!array_key_exists($section, $sections)) {
            $sections[$section] = array();
            $sections[$section]['students'] = array();
            $sections[$section]['ta_max'] = $grade['max_score'];
            $sections[$section]['is_registration'] = $grade['g_grade_by_registration'];
            $sections[$section]['autograding_max'] = $autograding_max;
        }

        $grade['autograding_points'] = getAutogradingPoints($g_id, $grade['user_id'], $grade['active_version']);

        array_push($sections[$section]['students'], $grade);
    }

    foreach ($sections as $section) {

        usort($section['students'], "section_sort");
    }

    return $sections;
}

function section_sort($a, $b)
{
    return $a['user_id'] > $b['user_id'];
}

function getSectionHTML($is_registration, $section_number)
{
    //Build the header row text
    $s = "Students ";
    if ($is_registration) {
        $s .= "enrolled in Registration Section " . $section_number;
    } else {
        $s .= "in Rotating Section " . $section_number;
    }

    //Dynamically build tbody id so that I can hook body html later
    return <<<HTML
                <th id="th_{$section_number}" class="info" colspan="5" style="text-align:center; background-color: #f9f9f9">{$s}</th>
                <tbody id="body_{$section_number}"></tbody>                
HTML;
}

function getSummaryHTML($grades, $sections)
{
    //String to collect html
    $html = "";

    //Base url for site
    global $BASE_URL;

    //I guess this is supposed to be scrolling to a specific section. I didn't test it, just copied it as is.
    $html .= <<<HTML
    <style type="text/css">
        body {
            overflow: scroll;
        }

        #container-rubric
        {
            width:700px;
            margin: 70px auto 100px;
            background-color: #fff;
            border: 1px solid #999;
            border: 1px solid rgba(0,0,0,0.3);
            -webkit-border-radius: 6px;
            -moz-border-radius: 6px;
            border-radius: 6px;outline: 0;
            -webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
            -moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
            box-shadow: 0 3px 7px rgba(0,0,0,0.3);
            -webkit-background-clip: padding-box;
            -moz-background-clip: padding-box;
            background-clip: padding-box;
        }
    </style>
HTML;

    //If there are no results then the gradeable does not exist.


    $g_title = $grades[0]['g_title'];

    //Change button text depending on user and url parameters
    if (!User::$is_administrator) {
        if (isset($_GET['all']) && $_GET['all'] == "true") {
            $button = "<div class='btn all_sections'>View Your Sections</div>";
        } else {
            $button = "<div class='btn all_sections'>View All Sections</div>";
        }
    } else {
        $button = "";
    }

    //Build table header
    $html .= <<<HTML
    <div id="container-rubric">
        <div class="modal-header">
            <h3 id="myModalLabel" style="width: 75%; display: inline-block">{$g_title} Summary</h3>
            {$button}
        </div>

        <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
            <table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
                <thead style="background: #E1E1E1;">
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Autograding</th>
                        <th>TA Grading</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody style="background: #f9f9f9;">
HTML;

    //For each section get html skeleton to hook onto
    foreach (array_keys($sections) as $section) {
        $html .= getSectionHTML($sections[$section]['is_registration'], $section);
    }

    //Close html table
    $html .= <<<HTML
                </tbody>
            </table>
        </div>

        <div class="modal-footer">
            <!--<a class="btn" href="{$BASE_URL}/account/index.php?course={$_GET['course']}&semester={$_GET['semester']}">Select Different Homework</a>-->
            <a class="btn" href="{$BASE_URL}/account/index.php?g_id={$_GET['g_id']}&course={$_GET['course']}&semester={$_GET['semester']}">Grade Next Student</a>
        </div>
    </div>
HTML;

    return $html;
}

function getSummaryJS($sections)
{
    //The sections encoded for javascript
    $js_sections = json_encode($sections['not_assigned']);
    $js_ta_sections = json_encode($sections['assigned']);

    $js = "";

    //Add javascript that was originally here
    $js .= <<<HTML
    <script type="text/javascript">
    //I guess this is scrolling to a specific area
    if (window.location.hash != "") {
        window.scrollTo(0, 0);
        setTimeout(function() {
            window.scrollTo(0, 0);
        }, 1);
    }
    $(function() {
        if (window.location.hash != "") {
            if ($(window.location.hash).offset().top > 0) {
                $("html, body").animate({scrollTop: ($(window.location.hash).offset().top - 40)}, 800);
            }
        }
    });
    
    //Not sure what this cookie is for
    createCookie('backup',0,1000);

HTML;

    //If the user is not an administrator create 'notassigned_section' class for hiding sections
    if (User::$is_administrator) {
        $js .= "var class_name = '';";
    } else {
        $js .= <<<JS
        //Function for clicking on view all button
        $('.all_sections').click(function(){
            $('.notassigned_section').toggle();
        });

        var class_name = 'notassigned_section';
JS;
    }

    //Bring in the json encoded arrays to javascript
    $js .= "var sections = " . $js_sections . ";";
    $js .= "var ta_sections = " . $js_ta_sections . ";";
    global $BASE_URL;
    $js .= "var url_string = '" . $BASE_URL . "/account/index.php';";

    $js .= <<<JS
    //For each section that the TA is assigned fetch them first and remove them from the sections array
    Object.keys(ta_sections).forEach(function(element){
        
        var subsec1 = new Object();
        subsec1['students'] = new Object();
        subsec1['autograding_max'] = ta_sections[element]['autograding_max'];
        subsec1['ta_max'] = ta_sections[element]['ta_max'];
        var subsec2 = new Object();
        subsec2['students'] = new Object();
        subsec2['autograding_max'] = ta_sections[element]['autograding_max'];
        subsec2['ta_max'] = ta_sections[element]['ta_max'];
        
        var section_keys = Object.keys(ta_sections[element]['students']);
        
        for(i = 0; i < section_keys.length/2; i++){
            subsec1['students'][i] = ta_sections[element]['students'][i];
        }
        
        for(i = section_keys.length/2; i < section_keys.length; i++){
            subsec2['students'][i] = ta_sections[element]['students'][i];
        }
        
        $.post("ajax/account-summary.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}", 
            {section_data: subsec1, url_string: url_string})
             .done(function(data){
                //Build the dynamic id for this section and append the section html
                section_body_string = "body_" + element;
                $('#' + section_body_string ).html(data);
            }).fail(function(){
                console.log("failure: ta section " + element);
            });
        
        $.post("ajax/account-summary.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}", 
            {section_data: subsec2, url_string: url_string})
             .done(function(data){
                //Build the dynamic id for this section and append the section html
                section_body_string = "body_" + element;
                $('#' + section_body_string ).append(data);
            }).fail(function(){
                console.log("failure: ta section " + element);
            });
        }
    );
    
    Object.keys(sections).forEach(function(element){
        $.post("ajax/account-summary.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$_GET['g_id']}", 
            {section_data: sections[element], url_string: url_string})
            .done(function(data){
                //Build the dynamic id for this section and append the class and section html
                section_body_string = "body_" + element;
                section_th_string = "th_" + element;
                $('#' + section_th_string).addClass(class_name);
                $('#' + section_body_string).addClass(class_name);
                $('#' + section_body_string).html(data);
                $('#' + section_th_string).toggle();
                $('#' + section_body_string).toggle();
            }).fail(function(){
                console.log("failure: section " + element);
            });
        }
    );
    
JS;

    //Close up script tag
    $js .= <<<HTML
    </script>
HTML;

    return $js;
}

function separateSections($all_sections, $ta_sections)
{
    $sections = array();
    $sections['assigned'] = array();
    $sections['not_assigned'] = array();

    foreach ($ta_sections as $i) {
        $s = (string)$i[0];

        $sections['assigned'][$s] = $all_sections[$s];

        unset($all_sections[$s]);
    }

    foreach (array_keys($all_sections) as $s) {
        if (User::$is_administrator) {
            $sections['assigned'][$s] = $all_sections[$s];
        } else {
            $sections['not_assigned'][$s] = $all_sections[$s];
        }
    }

    return $sections;
}

function getSummary()
{
    //Id of the gradeable
    $g_id = $_GET['g_id'];

    //For every user for this gradeable for every gradeable component get user names and ta grading totals
    $grades = getGrades($g_id);

    if (empty($grades)) {
        global $BASE_URL;
        return <<<HTML
    <div id="container-rubric">
        <div class="modal-header">
            <h3 id="myModalLabel">Invalid Gradeable</h3>
        </div>

        <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
            ERROR:  Invalid Gradeable ID or No TA grading rubric items for this Gradeable ID.  <br> 
Check the <tt>g_id</tt> in the url, and ensure that this Gradeable has at least one TA Rubric item with points &gt; 0.<br /><br />
            <a class="btn" href="{$BASE_URL}/account/index.php?course={$_GET['course']}&semester={$_GET['semester']}">Select Different Gradeable</a>
        </div>
    </div>
HTML;

    }
    $autograding_max = getAutogradingMax($g_id);

    //Parse the gradeable data out into individual sections and get autograding totals
    $all_sections = parseSections($grades, $g_id, $autograding_max);

    //Get the TA's assigned sections
    $ta_sections = ($grades[0]['g_grade_by_registration']) ?
        getSectionsRegistration(User::$user_id) : getSectionsRotating(User::$user_id, $g_id);

    //Separate sections into a list of assigned sections and a list of unassigned sections
    $sections = separateSections($all_sections, $ta_sections);

    //Get the html
    $html = getSummaryHTML($grades, $all_sections);

    //Get the javascript
    $js = getSummaryJS($sections);

    return $html . $js;
}

echo getSummary();

include "../footer.php";