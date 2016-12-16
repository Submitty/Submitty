<?php

use \lib\Database;
class LateDaysCalculation
{
    /**
     * @var String of format('Y-m-d H:i:s'). Represents the cutoff date for fetching grades.
     */
    private $endDate;
    /**
     * @var Array([String]). The user ids so query for. An empty Array will fetch all users.
     */
    private $userIds;

    /**Submission
     * @psuedo_struct Array([keys]). An array containing the data required to calculate late days for a given
     * gradeable. Contains the keys:
     *  user_id => String. They id of the user who made this submission.
     *  g_title => String. The name of the gradeable.
     *  g_id => String. The gradeable id.
     *  assignment_allowed => int. The number of late days allowed for this gradeable.
     *  days_late => int. The number of days late an gradeable is. Calculated in the query as
     *      greatest(0, ceil(extract(EPOCH FROM(egd.submission_time - eg.eg_submission_due_date))/86400):: integer)
     *  eg_submission_due_date => String of format('Y-m-d H:i:s'). Represents the cutoff date for on time submission.
     *  submission_time => String of format('Y-m-d H:i:s'). Represents the time that the submission was made.
     *  //TODO: Currently does not handle negative extensions. Unknown whether or not they are handled before/on insertion.
     *  extensions => int. Additional late days to be applied to current g_id only.
     *  active_version => int. Which submission number is the user's active submission.
     */

    /**
     * @var Array([Submission])
     */
    private $submissions;

    /**LatedayUpdate
     * @psuedo_struct Array([keys]). An array containing the data required to calculate late day updates for a given
     * student. Contains the keys:
     *  user_id => String. They id of the user whose late day allotment will be updated.
     *  allowed_late_days => int. The number of additional late days to apply to a user's allotment.
     *  submission_time => String of format('Y-m-d H:i:s'). Represents the earliest time at which this update can be
     *      applied.
     */

    /**
     * @var Array([LatedayUpdate])
     */
    private $latedays;

    /**Student
     * @psuedo_struct Array([keys]). An array containing the data required to calculate late day usage for a given
     * student. Contains the keys:
     *  user_id => String. They id of the user whose late days will be calculated.
     *  submissions => Array([Submission]). The submissions to be used in the calculation of a student's late day usage.
     *  latedays => Array([LatedayUpdate]). The lateday updates to be used in the calculation of a students late day
     *      usage.
     */

    /**
     * @var Array([Student]). An array of students.
     */
    private $students;

    /**
     * @var Array(Array([LateDayUsage])). Late day usage for all students queried for all assignments queried. The
     * outer array is indexed by user_id and the second array is indexed by gradeable id (g_id). Innermost array
     * contains the keys:
     *  user_id => String. They id of the user whose late day usage is represented.
     *  g_title => String. The name of the gradeable.
     *  allowed_per_term => int. The number of latedays allowed per term after updates.
     *  allowed_per_assignment => int. The number of latedays allowed for this assignment.
     *  late_days_used => int. Number of late days used on this assignment before extensions.
     *  extensions => int. Number of additional late days to apply to the current submission.
     *  status => String. The status of the assignment. Can be "Good", "Bad", "Late", "Cancelled", or "No Submission"
     *  late_days_charged => int. The number of late days the student was charged for this submission.
     *  remaining_days => int. The number of late days the student has remaining for the term.
     *
     *  Example: $all_latedays['smithj']['cpp_cats'] => ['smithj', 'CPP Cats', 2, 2, 1, 2, 'Good', 0, 2]
     */
    private $all_latedays;

    /**
     * LateDaysCalculation constructor. Given a cutoff date ($endDate) and a list of user ids fetch all data from the
     * database to calculate late day usage.
     * @param $endDate String of format('Y-m-d H:i:s'). Represents the cutoff date for fetching grades.
     * @param $userIds Array([String]). The user ids so query for. An empty Array will fetch all users.
     */
    function __construct($endDate, $userIds)
    {
        //Set class properties.
        $this -> endDate = $endDate;
        $this -> userIds = $userIds;
        //Query database and parse queries
        $this -> submissions = $this -> get_student_submissions($endDate, $userIds);
        $this -> latedays = $this -> get_student_lateday_updates($endDate, $userIds);
        $this -> students = $this -> parse_students($this -> submissions, $this -> latedays);
        //Calculate lateday usages for all students for all assignments queried
        $this -> all_latedays = $this -> calculate_student_lateday_usage($this -> students);
    }

    /**
     * Gets all submissions by all requested students from the beginning of the term to the $endDate.
     * @param $endDate @var String of format('Y-m-d H:i:s'). Represents the cutoff date for fetching grades.
     * @param $userIds Array([String]). The user ids so query for. An empty Array will fetch all users.
     * @return Array([Submission])
     */
    private function get_student_submissions($endDate, $userIds)
    {
        $params = array();

        //Joint gradeable, electronic_gradeable, electronic_gradeable_version, electronic_gradeable_data,
        //and late_day_exceptions. Full join on electronic_gradeable_data and late_day_exceptions for fields user_id and
        //g_id. Needed to get sparse late_day_exceptions data.
        $query = "SELECT
              eg.g_id
              , eg.eg_late_days as assignment_allowed
              , greatest(0, ceil(extract(EPOCH FROM(egd.submission_time - eg.eg_submission_due_date))/86400):: integer) as days_late
              , eg.eg_submission_due_date
              , egd.submission_time
              , coalesce(lde.late_day_exceptions, 0) as extensions
              , g.g_title
              , coalesce(egv.active_version, -1) as active_version
              , egv.user_id
            FROM
              electronic_gradeable eg
              , electronic_gradeable_version egv
              , electronic_gradeable_data egd FULL OUTER JOIN late_day_exceptions lde ON lde.user_id = egd.user_id AND lde.g_id = egd.g_id
              , gradeable g
            WHERE
              eg.g_id = egv.g_id
              AND egv.g_id = egd.g_id
              AND egv.user_id = egd.user_id
              AND eg.g_id = g.g_id
              AND egv.active_version = egd.g_version ";

        //For every user_id in user_ids add another condition to the query and another user id to the parameters list.
        if (is_array($userIds) && count($userIds) > 0) {
            $query .= " AND (";
            for ($i = 0; $i < count($userIds); $i++) {
                if ($i > 0) {
                    $query .= " OR ";
                }
                $query .= "egv.user_id = ?";
                array_push($params, $userIds[$i]);
            }
            $query .= ")";
        }

        //Add due date to query and parameters list.
        $query .= "
            AND eg.eg_submission_due_date <= ? ";

        //Add order by condition so that results are received in chronological order by due date.
        $query .= "ORDER BY
          eg.eg_submission_due_date
          , g.g_title
          , egv.user_id
          ;";

        array_push($params, $endDate);

        //Query database and return results.
        Database::query($query, $params);
        return Database::rows();
    }

    /**
     * Get all late day updates to be applied for all requested users from the beginning of the term to the $endDate
     * @param $endDate @var String of format('Y-m-d H:i:s'). Represents the cutoff date for fetching grades.
     * @param $userIds Array([String]). The user ids so query for. An empty Array will fetch all users.
     * @return Array([LateDayUpdate])
     */
    private function get_student_lateday_updates($endDate, $userIds)
    {
        $params = array();
        //Select from late_days where user_id in $userIds and since_timestamp < $endDate.
        $query = "SELECT
          *
        FROM
          late_days l
        WHERE
          l.since_timestamp <=? ";

        array_push($params, $endDate);

        //Add all user_ids
        if (is_array($userIds) && count($userIds) > 0) {
            $query .= "AND (";
            for ($i = 0; $i < count($userIds); $i++) {
                if ($i > 0) {
                    $query .= " OR ";
                }
                $query .= "l.user_id = ?";
                array_push($params, $userIds[$i]);
            }
            $query .= ")";
        }

        //Append the order by condition.
        $query .= "ORDER BY
            l.user_id
            , l.since_timestamp;";

        //Query database and return results.
        Database::query($query, $params);
        return Database::rows();
    }

    /**
     * Takes the queried submission data and lateDayUpdate data and parses it into an list indexed by user_ids which
     * contains a list of Students
     * @param $submissions Array([Submission])
     * @param $latedays Array([LateDayUpdates])
     * @return Array([Students])
     */
    private function parse_students($submissions, $latedays)
    {
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

    /**
     * Calculates late day usage for each submission for each student in $students.
     * @param $students Array([Student]). The students whose late day usage to calculate
     * @return Array(Array([LateDayUsage])). Late day usage for all students queried for all assignments queried.
     */
    private function calculate_student_lateday_usage($students)
    {
        $all_latedays = array();

        //For each student for each submission calculate late day usage
        foreach ($students as $student) {

            //Base allowed late days and remaining late days
            $curr_allowed_term = __DEFAULT_HW_LATE_DAYS__;
            $curr_remaining_late = __DEFAULT_HW_LATE_DAYS__;
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

                $curr_remaining_increment = 0;

                //For each late day update check if it's timestamp is earlier than the due date of the gradeable. If so
                //apply the update to the remaining late days
                $items_to_remove = array();

                $late_day_updates_total = 0;
                $late_day_updates = Array();

                //Find all late day updates before this submission due date. Append the update to a list of updates
                //applied to this submission. Also append to a list of items to be removed from the master list of late
                //day updates. Attempting to ensure that late day updates are not applied twice.
                foreach($latedays as $ld){
                    if($ld['since_timestamp'] < $submissions[$i]['eg_submission_due_date']){
                        $late_day_updates_total += $ld['allowed_late_days'];
                        array_push($late_day_updates, $ld);
                        array_push($items_to_remove, $ld);
                    }
                }

                //Remove all of the newly applied late day updates.
                foreach($items_to_remove as $item){
                    if(($key = array_search($item, $latedays)) !== false){
                        unset($latedays[$key]);
                    }
                }

                //Update the number of remaining late days the user has remaining.
                $curr_remaining_late += $late_day_updates_total;

                $curr_bad_modifier = "";
                $curr_late_used = $submissions[$i]['days_late'];
                $curr_status = $status;
                $curr_late_charged = 0;

                $late_flag = true;

                //If late days used - extensions applied > 0 then status is "Late"
                if ($curr_late_used - $submissions[$i]['extensions'] > 0) {
                    $curr_status = "Late";
                }
                //If late days used - extensions applied > allowed per assignment then status is "Bad..."
                if ($curr_late_used - $submissions[$i]['extensions'] > $submissions[$i]['assignment_allowed']) {
                    $curr_status = "Bad";
                    $curr_bad_modifier = " too many used for this assignment";
                    $late_flag = false;
                }
                //If late days used - extensions applied > allowed per term then status is "Bad..."
                if ($curr_late_used - $submissions[$i]['extensions'] >  $curr_allowed_term) {
                    $curr_status = "Bad";
                    $curr_bad_modifier = " too many used this term";
                    $late_flag = false;
                }
                //A submission cannot be late and bad simultaneously. If it's late calculate late days charged. Cannot
                //be less than 0 in cases of excess extensions. Decrement remaining late days.
                if ($late_flag) {
                    $curr_late_charged = $curr_late_used - $submissions[$i]['extensions'];
                    $curr_late_charged = ($curr_late_charged < 0) ? 0 : $curr_late_charged;
                    $curr_remaining_late -= $curr_late_charged;
                    $total_late_used += $curr_late_charged;
                }

                $submission_latedays['user_id'] = $submissions[$i]['user_id'];
                $submission_latedays['g_title'] = $submissions[$i]['g_title'];
                $submission_latedays['allowed_per_term'] = $curr_allowed_term;
                $submission_latedays['allowed_per_assignment'] = $submissions[$i]['assignment_allowed'];
                $submission_latedays['late_days_used'] = $curr_late_used;
                $submission_latedays['extensions'] = $submissions[$i]['extensions'];
                $submission_latedays['status'] = $curr_status.$curr_bad_modifier;
                $submission_latedays['late_days_charged'] = $curr_late_charged;
                $submission_latedays['remaining_days'] = $curr_remaining_late;
                $submission_latedays['lateday_updates'] = $late_day_updates_total;
                $submission_latedays['total_late_used'] = $total_late_used;

                $late_day_usage[$submissions[$i]['g_id']] = $submission_latedays;

                $curr_allowed_term = $curr_remaining_late;
            }

            $all_latedays[$student['user_id']] = $late_day_usage;

        }

        return $all_latedays;
    }

    /**
     * For the given user id generate the late day usage HTML table.
     * @param $user_id String. The user id of the user whose table you want.
     * @return string. The string representation of the HTML table.
     */
    public function generate_table_for_user($user_id){
        //table header row.
        $table = <<<HTML
                <h4>Overall Late Day Usage</h4>
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th style="border:thin solid black">Allowed per term</th>
                            <th style="border:thin solid black">Allowed per assignment</th>
                            <th style="border:thin solid black">Late days used</th>
                            <th style="border:thin solid black">Extensions</th>
                            <th style="border:thin solid black">Late Day Updates</th>
                            <th style="border:thin solid black">Status</th>
                            <th style="border:thin solid black">Late Days Charged</th>
                            <th style="border:thin solid black">Remaining Days</th>
                        </tr>
                    </thead>
                    <tbody>
HTML;

        //If user exists in list build their table. If user does not exist empty table is returned.
        if(array_key_exists($user_id, $this ->students)) {

            $student = $this->all_latedays[$user_id];

            //For each submission build a table row.
            foreach ($student as $submission) {
                $table .= <<<HTML
                <tr>
                    <th style="border:thin solid black">{$submission['g_title']}</th>
                    <td align="center" style="border:thin solid black">{$submission['allowed_per_term']}</td>
                    <td align="center" style="border:thin solid black">{$submission['allowed_per_assignment']}</td>
                    <td align="center" style="border:thin solid black">{$submission['late_days_used']}</td>
                    <td align="center" style="border:thin solid black">{$submission['extensions']}</td>
                    <td align="center" style="border:thin solid black">{$submission['lateday_updates']}</td>
                    <td align="center" style="border:thin solid black">{$submission['status']}</td>
                    <td align="center" style="border:thin solid black">{$submission['late_days_charged']}</td>
                    <td align="center" style="border:thin solid black">{$submission['remaining_days']}</td>
                </tr>
HTML;
            }
        }

        //Close HTML tags for table.
        $table .= <<<HTML
                </tbody>
            </table>
HTML;

        return $table;
    }

    /**
     * Get the status String for a give user for a given gradeable. Assumes gradeable was due before the $endDate
     * provided at instantiation.
     * @param $user_id String. The user to query for.
     * @param $g_id String. The gradeable id to query for.
     * @return string. The status string.
     */
    public function get_gradeable_status($user_id, $g_id){
        if(array_key_exists($user_id, $this->all_latedays) && array_key_exists($g_id, $this->all_latedays[$user_id])){
            return $this->all_latedays[$user_id][$g_id]['status'];
        }
        return 'Not Submitted';
    }

    /**
     * Get the lateday usage table for a given user for a given gradeable. Assumes gradeable was due before the $endDate
     * provided at instantiation.
     * @param $user_id String. The user to query for.
     * @param $g_id String. The gradeable id to query for.
     * @return string. The status string.
     */
    public function get_gradeable($user_id, $g_id){
        if(array_key_exists($user_id, $this->all_latedays) && array_key_exists($g_id, $this->all_latedays[$user_id])){
            return $this->all_latedays[$user_id][$g_id];
        }
        return array();
    }

    private function submission_sort($a, $b){
        return $a['eg_submission_due_date'] > $b['eg_submission_due_date'];
    }
}