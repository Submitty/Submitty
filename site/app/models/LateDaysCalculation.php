<?php 

namespace app\models;

use app\libraries\Core;

class LateDaysCalculation extends AbstractModel {
    /*var Core */
    protected $core;
    /*var Array holding info need to calculate late days for a gradeable*/
    protected $submissions;
    /*var Array holding data necessary to calculate late day update data*/
    protected $latedays;
    /*var Array holding data necessary to calculate late day usage for a particular student*/
    protected $students;
    /* var Array: Late day usage for all students queried for all assignments queried. The
    *  outer array is indexed by user_id and the second array is indexed by gradeable id (g_id). 
    */
    protected $all_latedays;
    /* Holds grace period in seconds (300 seconds = 5 minutes)*/
    protected $SUBMISSION_GRACE_PERIOD = 300;
    
    function __construct(Core $main_core) {
        $this->core = $main_core;
        $this->submissions = $this->get_student_submissions();
        $this->latedays = $this->get_student_lateday_updates();
        $this->students = $this->parse_students($this->submissions, $this->latedays);
        //Calculate lateday usages for all students for all assignments queried
        $this->all_latedays = $this->calculate_student_lateday_usage($this->students);
    }
    
    private function get_student_submissions() {
        $params = array();

        $query = "SELECT
                      submissions.*
                      , coalesce(late_day_exceptions, 0) extensions
                      , greatest(0, ceil((extract(EPOCH FROM(coalesce(submission_time, eg_submission_due_date) - eg_submission_due_date)) - (?*60))/86400):: integer) as days_late
                    FROM
                      (
                        SELECT
                        base.g_id
                        , g_title
                        , base.assignment_allowed
                        , base.user_id
                        , eg_submission_due_date
                        , coalesce(active_version, -1) as active_version
                        , submission_time
                      FROM
                      (
                        --Begin BASE--
                        SELECT
                          g.g_id,
                          u.user_id,
                          g.g_title,
                          eg.eg_submission_due_date,
                          eg.eg_late_days AS assignment_allowed
                        FROM
                          users u
                          , gradeable g
                          , electronic_gradeable eg
                        WHERE
                          g.g_id = eg.g_id
                        --End Base--
                      ) as base
                    FULL JOIN
                    (
                      --Begin Details--
                      SELECT
                        g_id
                        , user_id
                        , active_version
                        , g_version
                        , submission_time
                      FROM
                        electronic_gradeable_version egv NATURAL JOIN electronic_gradeable_data egd
                      WHERE
                        egv.active_version = egd.g_version
                      --End Details--
                    ) as details
                    ON
                      base.user_id = details.user_id
                      AND base.g_id = details.g_id
                    ) 
                      AS submissions 
                      FULL OUTER JOIN 
                        late_day_exceptions AS lde 
                      ON submissions.g_id = lde.g_id 
                      AND submissions.user_id = lde.user_id";
        array_push($params, $SUBMISSION_GRACE_PERIOD);

        //Query database and return results.
        return $this->core->getDatabase()->query($query, $params);
    }
    
    private function get_student_lateday_updates() {
        return $this->core->getDatabase()->query("SELECT * FROM latedays;");
    }
    
    private function parse_students($submissions, $latedays) {
        $students = array();

        //For each submission ensure that an entry exists for that user and append the submission to their list of
        //submissions.
        for ($i = 0; $i < count($submissions); $i++) {

            $curr_student = $submissions[$i]['user_id'];

            if (array_key_exists($curr_student, $students)) {
                array_push($students[$curr_student]['submissions'], $submissions[$i]);
            } else {

                $submission = array();
                $submission['user_id'] = $curr_student;
                $submission['submissions'] = array();
                $submission['latedays'] = array();
                array_push($submission['submissions'], $submissions[$i]);
                $students[$curr_student] = $submission;
            }
        }

        //For each lateDayUpdate append the lateDayUpdate to the appropriate user.
        for ($i = 0; $i < count($latedays); $i++) {

            $curr_student = $latedays[$i]['user_id'];

            if (array_key_exists($curr_student, $students)) {
                array_push($students[$curr_student]['latedays'], $latedays[$i]);
            } else {
                //Else student got a late day exception but never turned in any assignments.
            }
        }
        return $students;
    }
    
    private function calculate_student_lateday_usage($students) {
        $all_latedays = array();

        //For each student for each submission calculate late day usage
        foreach ($students as $student) {

            //Base allowed late days and remaining late days
            $curr_allowed_term = $this->core->getConfig()->getDefaultStudentLateDays();
            $curr_remaining_late = $this->core->getConfig()->getDefaultStudentLateDays();
            $total_late_used = 0;
            $status = "Good";

            $submissions = $student['submissions'];

            //Sort submissions by due date before calculating late day usage.
            usort($submissions, array($this, "submission_sort"));

            $latedays = $student['latedays'];

            $late_day_usage = array();

            //Calculate per gradeable late day usage. Assumes submissions are in sorted order.
            for ($i = 0; $i < count($submissions); $i++) {
                $submission_latedays = array();

                //Sort latedays by since_timestamp before calculating late day usage.
                usort($latedays, array($this, "lateday_update_sort"));

                //Find all late day updates before this submission due date.
                foreach($latedays as $ld){
                    if($ld['since_timestamp'] < $submissions[$i]['eg_submission_due_date']){
                        $curr_allowed_term = $ld['allowed_late_days'];
                    }
                }

                $curr_bad_modifier = "";
                $curr_late_used = $submissions[$i]['days_late'];
                $curr_status = $status;
                $curr_late_charged = 0;

                $late_flag = false;

                //If late days used - extensions applied > 0 then status is "Late"
                if ($curr_late_used - $submissions[$i]['extensions'] > 0) {
                    $curr_status = "Late";
                    $late_flag = true;
                }
                //If late days used - extensions applied > allowed per assignment then status is "Bad..."
                if ($curr_late_used - $submissions[$i]['extensions'] > $submissions[$i]['assignment_allowed']) {
                    $curr_status = "Bad";
                    $curr_bad_modifier = " too many used for this assignment";
                    $late_flag = false;
                }
                //If late days used - extensions applied > allowed per term then status is "Bad..."
                if ($curr_late_used - $submissions[$i]['extensions'] >  $curr_allowed_term - $total_late_used) {
                    $curr_status = "Bad";
                    $curr_bad_modifier = " too many used this term";
                    $late_flag = false;
                }
                //A submission cannot be late and bad simultaneously. If it's late calculate late days charged. Cannot
                //be less than 0 in cases of excess extensions. Decrement remaining late days.
                if ($late_flag) {
                    $curr_late_charged = $curr_late_used - $submissions[$i]['extensions'];
                    $curr_late_charged = ($curr_late_charged < 0) ? 0 : $curr_late_charged;
                    $total_late_used += $curr_late_charged;
                }

                $curr_remaining_late = $curr_allowed_term - $total_late_used;
                $curr_remaining_late = ($curr_remaining_late < 0) ? 0 : $curr_remaining_late;

                $submission_latedays['user_id'] = $submissions[$i]['user_id'];
                $submission_latedays['g_title'] = $submissions[$i]['g_title'];
                $submission_latedays['allowed_per_term'] = $curr_allowed_term;
                $submission_latedays['allowed_per_assignment'] = $submissions[$i]['assignment_allowed'];
                $submission_latedays['late_days_used'] = $curr_late_used;
                $submission_latedays['extensions'] = $submissions[$i]['extensions'];
                $submission_latedays['status'] = $curr_status.$curr_bad_modifier;
                $submission_latedays['late_days_charged'] = $curr_late_charged;
                $submission_latedays['remaining_days'] = $curr_remaining_late;
                $submission_latedays['total_late_used'] = $total_late_used;
                $submission_latedays['eg_submission_due_date'] = $submissions[$i]['eg_submission_due_date'];

                $late_day_usage[$submissions[$i]['g_id']] = $submission_latedays;
            }

            $all_latedays[$student['user_id']] = $late_day_usage;

        }

        return $all_latedays;
    }
    
    public function get_gradeable($user_id, $g_id) {
        if(array_key_exists($user_id, $this->all_latedays) && array_key_exists($g_id, $this->all_latedays[$user_id])){
            return $this->all_latedays[$user_id][$g_id];
        }
        return array();
    }
    
    private function submission_sort($a, $b){
        return $a['eg_submission_due_date'] > $b['eg_submission_due_date'];
    }
    
    private function lateday_update_sort($a, $b){
        return $a['since_timestamp'] > $b['since_timestamp'];
    }
}


?>