<?php

require_once "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf']) {
    die("invalid csrf token");
}

$type = $_POST['type'];
$complete_update = 0;
if ($type == 'section') {
    \lib\Database::query("SELECT * FROM sections");
    foreach (\lib\Database::rows() as $section) {
        $grading_section = intval($_POST["section_{$section['section_id']}"]);
        \lib\Database::query("UPDATE students SET student_grading_id=? WHERE student_section_id=?", array($grading_section, $section['section_id']));
    }
    $complete_update = 1;
}
else if ($type == 'arrange') {
    $_POST['arrange_type'] = in_array($_POST['arrange_type'], array('random', 'alphabetically')) ? $_POST['arrange_type'] : 'random';
    $grading_sections = intval($_POST['sections']);
    if ($grading_sections > 0) {
        $complete_update = 1;
        if (isset($_POST['skip_disabled']) && $_POST['skip_disabled'] == 1) {
            \lib\Database::query("
SELECT s.student_rcs, r.section_is_enabled
FROM students AS s
LEFT JOIN (
    SELECT section_id, section_is_enabled
    FROM sections
) as r ON s.student_section_id=r.section_id
ORDER BY student_rcs
");
        }
        else {
            \lib\Database::query("SELECT student_rcs, 1 as section_is_enabled FROM students");
        }
        $good_users = array();
        $disabled_users = array();
        foreach (\lib\Database::rows() as $user) {
            if ($user['section_is_enabled'] == 0) {
                $disabled_users[] = $user['student_rcs'];
            }
            else {
                $good_users[] = $user['student_rcs'];
            }
        }

        if ($_POST['arrange_type'] == 'random') {
            shuffle($good_users);
        }
        $per_section = ceil(count($good_users) / $grading_sections);
        for ($i = 1; $i <= $grading_sections; $i++) {
            $update = array_slice($good_users, ($i-1) * $per_section, $per_section * $i);
            // this should only happen if we try to split n users into n+1 grading sections
            if (count($update) == 0) {
                continue;
            }
            $update_string = array_pad(array(), count($update), "?");
            \lib\Database::query("UPDATE students SET student_grading_id=? WHERE student_rcs IN (".implode(",", $update_string).")", array_merge(array($i), $update));
        }
        if (count($disabled_users) > 0) {
            $update_string = array_pad(array(), count($disabled_users), "?");
            \lib\Database::query("UPDATE students SET student_grading_id=? WHERE student_rcs IN (" . implode(",", $update_string) . ")", array_merge(array($grading_sections + 1), $disabled_users));
        }
    }
}

header('Location: '.__BASE_URL__.'/account/admin-grading-sections.php?course='.$_GET['course']."&update={$complete_update}");