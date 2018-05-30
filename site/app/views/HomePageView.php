<?php
namespace app\views;

use app\authentication\DatabaseAuthentication;
use app\views\AbstractView;
use app\models\Course;



class HomePageView extends AbstractView {


    /*
    *@param List of courses the student is in.
    */
    public function showHomePage($user, $courses = array(), $changeNameText) {
        $rankTitles = [
            0 => "Developer:",
            1 => "Instructor:",
            2 => "Full Access Grader:",
            3 => "Grader:",
            4 => "Student:"
        ];
        $ranks = array();

        //Create rank lists
        for ($i = 0; $i < 5; $i++){
            $ranks[$i] = [];
            $ranks[$i]["title"] = $rankTitles[$i];
            $ranks[$i]["courses"] = [];
        }

        //Assemble courses into rank lists
        foreach ($courses as $course) {
            $rank = $this->core->getQueries()->getGroupForUserInClass($course->getSemester(), $course->getTitle(), $user->getId());
            array_push($ranks[$rank]["courses"], $course);
        }

        //Filter any ranks with no courses
        $ranks = array_filter($ranks, function($rank) {
            return count($rank["courses"]) > 0;
        });

        return $this->core->getOutput()->renderTwigTemplate('HomePage.twig', [
            "user" => $user,
            "ranks" => $ranks,
            "changeNameText" => $changeNameText,
            "showChangePassword" => $this->core->getAuthentication() instanceof DatabaseAuthentication
        ]);
    }
}
