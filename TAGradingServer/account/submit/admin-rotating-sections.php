<?php

require_once "../../toolbox/functions.php";

check_administrator();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf']) {
    die("invalid csrf token");
}

$complete_update = 0;

$_POST['arrange_type'] = in_array($_POST['arrange_type'], array('random', 'alphabetically')) ? $_POST['arrange_type'] : 'random';
$grading_sections = intval($_POST['sections']);
if ($grading_sections > 0) {
    $complete_update = 1;
    
    \lib\Database::query("SELECT user_id FROM users WHERE (user_group=? AND registration_section IS NOT NULL) OR (manual_registration) ORDER BY user_lastname ASC;", array(4));
    
    $good_users = array();
    foreach (\lib\Database::rows() as $user) {
        array_push($good_users, $user['user_id']);
    }

    if ($_POST['arrange_type'] == 'random') {
        shuffle($good_users);
    }
    
    \lib\Database::query("UPDATE users SET rotating_section=NULL");
    \lib\Database::query("SELECT MAX(sections_rotating_id) as max FROM sections_rotating", array());
    $highest_section = \lib\Database::row()['max'];
    
    $per_section = ceil(count($good_users) / $grading_sections);
    for ($i = 1; $i <= $grading_sections; $i++) {
        $update = array_slice($good_users, ($i-1) * $per_section, $per_section * $i);
        // this should only happen if we try to split n users into n+1 grading sections
        if (count($update) == 0) {
            continue;
        }
        $update_string = array_pad(array(), count($update), "?");
        if ($i > $highest_section){
            \lib\Database::query("INSERT INTO sections_rotating (sections_rotating_id) VALUES(?)", array($i));
        }
        \lib\Database::query("UPDATE users SET rotating_section=? WHERE user_id IN (".implode(",", $update_string).")", array_merge(array($i), $update));
    }
}


header('Location: '.__BASE_URL__.'/account/admin-rotating-sections.php?course='.$_GET['course'].'&semester='.$_GET['semester']."&update={$complete_update}");