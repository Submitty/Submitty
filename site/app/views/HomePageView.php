<?php
namespace app\views;

use app\views\AbstractView;
use app\models\Course;



class HomePageView extends AbstractView {


    /*
    *@param List of courses the student is in.
    */
    public function showHomePage($user, $courses = array()) {
        $url = "";
        $changeNameText = 'Submitty welcomes individuals of all ages, backgrounds, citizenships,
disabilities, sex, education, ethnicities, family statuses, genders,
gender identities, geographical locations, languages, military
experience, political views, races, religions, sexual orientations,
socioeconomic statuses, and work experiences.

In an effort to create an inclusive environment, you may specify a
preferred name to be used instead of what was provided on the
registration roster.';
        $return = <<< HTML
<div class="content">
    <div class="sub">
        <div class="box half">
        <h2>User Data</h2>
            <table>
                <tbody>
                    <tr>
                        <td><b>User Id:</b> {$user->getId()} </td>
                    </tr>
                    <tr>
                        <td><b>First Name:</b> {$user->getDisplayedFirstName()} </td>
                        <td><a onclick="userNameChange('$user->getDisplayedFirstName()')"><i class="fa fa-pencil" aria-hidden="true"></i></a></td>
                    </tr>
                    <tr>
                        <td><b>Last Name:</b> {$user->getLastName()} </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="box half">
            <div class="half">
            <h2>Courses You're Enrolled In</h2>
                <table>
                    <tbody>
HTML;

                    foreach($courses as $course){
                        $display_text = $course->getSemester() . " " . $course->getTitle();
                        $return .= <<<HTML
                        <tr>
                            <td colspan="8">
                                <a class="btn btn-primary" style="width:400%;" href="{$this->core->buildUrl(array('component' => 'navigation', 'course' => $course->getTitle(), 'semester' => $course->getSemester()))}"> {$display_text}</a>
                            </td>
                        </tr>
HTML;
                    }
                    $return .= <<<HTML
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
HTML;
        return $return;
    }
}
