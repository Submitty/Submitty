<?php

require_once "../../toolbox/functions.php";

use lib\Database;

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$complete_update = 0;

$_POST['arrange_type'] = in_array($_POST['arrange_type'], array('random', 'alphabetically')) ? $_POST['arrange_type'] : 'random';
$grading_sections = intval($_POST['sections']);
if ($grading_sections > 0) {
    $complete_update = 1;
    
    Database::query("SELECT user_id FROM users WHERE (user_group=? AND registration_section IS NOT NULL) OR (manual_registration) ORDER BY user_id ASC;", array(4));
    
    $good_users = array();
    foreach (\lib\Database::rows() as $user) {
        $good_users[] = $user['user_id'];
    }

    if ($_POST['arrange_type'] == 'random') {
        shuffle($good_users);
    }

    Database::query("UPDATE users SET rotating_section=NULL");
    Database::query("SELECT MAX(sections_rotating_id) as max FROM sections_rotating", array());
    $highest_section = Database::row()['max'];

    // We have to determine the number of students to put into each section. We cannot simply
    // divide number of students by number of grading sections as that might give a non-round
    // number as there's no way to easily resolve that. We can however, go through the number
    // of students saying that all sections get 1 student, all sections get 2 students, etc.
    // until we run out of students to give to sections. This should mean that at worst, some
    // sections will have one less student than others.

    $section_counts = array_fill(0, $grading_sections, 0);
    for ($i = 0; $i < count($good_users); $i++) {
        $section = $i % $grading_sections;
        $section_counts[$section]++;
        if ($section > $highest_section) {
            Database::query("INSERT INTO sections_rotating (sections_rotating_id) VALUES (?)", array($section));
        }
    }
    var_dump($section_counts);
    for ($i = 0; $i < $grading_sections; $i++) {
        $users = array_splice($good_users, 0, $section_counts[$i]);
        $update_array = array_merge(array($i+1), $users);
        $update_string = implode(",", array_pad(array(), count($users), "?"));
        Database::query("UPDATE users SET rotating_section=? WHERE user_id IN ({$update_string})", $update_array);
    }
}

header('Location: '.__BASE_URL__.'/account/admin-rotating-sections.php?course='.$_GET['course'].'&semester='.$_GET['semester']."&update={$complete_update}");