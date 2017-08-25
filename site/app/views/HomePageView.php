<?php
namespace app\views;

use app\views\AbstractView;
use app\models\Course;



class HomePageView extends AbstractView {

    /*
    *@param List of courses the student is in.
    */
    public function showHomePage($user, $courses = array()) {
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
                        <td><b>First Name:</b> {$user->getFirstName()} </td>
                    </tr>
                    <tr>
                        <td><b>Preferred First Name:</b> {$user->getDisplayedFirstName()} </td>
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
                        $buttonText = $course->getSemester() . " " . $course->getTitle();
                        $return .= <<<HTML
                        <tr>
                            <td colspan="8">
                                <a class="btn btn-primary" style="width:400%;" href="{$this->core->buildUrl(array('component' => 'navigation', 'course' => $course->getTitle(), 'semester' => $course->getSemester()))}"> {$buttonText}</a>
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
