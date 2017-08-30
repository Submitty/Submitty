<?php
namespace app\views;

use app\authentication\DatabaseAuthentication;
use app\views\AbstractView;
use app\models\Course;



class HomePageView extends AbstractView {


    /*
    *@param List of courses the student is in.
    */
    public function showHomePage($user, $courses = array(), $changeNameText, $course_display_name) {
        $displayedFirstName = $user->getDisplayedFirstName();
        $return = <<< HTML
<div class="content">
    <div class="sub">
        <div class="box half">
        <h2>About You</h2>
            <table>
                <tbody>
                    <tr>
                        <td><b>Username:</b> {$user->getId()} </td>
                    </tr>
                    <tr>
                        <td><b>First Name:</b> {$user->getDisplayedFirstName()} </td>
                        <td><a onclick="userNameChange('$displayedFirstName')"><i class="fa fa-pencil" aria-hidden="true"></i></a></td>
                        <script type="text/javascript">
                            function userNameChange() {
                                $('.popup-form').css('display', 'none');
                                var form = $("#edit-username-form");
                                form.css("display", "block");
                                $('[name="user_name_change"]', form).val("");
                            }
                        </script>
                        <div class="popup-form" id="edit-username-form">
                            <h2>Specify Preferred First Name</h2>
                            <p>{$changeNameText}</p>
                            <p>&emsp;</p>
                            <form method="post" action="{$this->core->buildUrl(array('page' => 'change_username'))}">
                                <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                                <div>
                                    <input type="text" name="user_name_change" />
                                </div>
                                <div style="float: right; width: auto; margin-top: 10px">
                                    <a onclick="$('#edit-username-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
                                    <input class="btn btn-primary" type="submit" value="Submit" />
                                </div>
                            </form>
                        </div>
                    </tr>
                    <tr>
                        <td><b>Last Name:</b> {$user->getLastName()} </td>
                    </tr>
HTML;
        if ($this->core->getAuthentication() instanceof DatabaseAuthentication) {
            $return .= <<<HTML
                    <tr>
                        <td><b>Change Password</b></td>
                        <td><a onclick="passwordChange()"><i class="fa fa-pencil" aria-hidden="true"></i></a></td>
                        <script type="text/javascript">
                            function passwordChange() {
                                $('.popup-form').css('display', 'none');
                                var form = $("#change-password-form");
                                form.css("display", "block");
                                $('[name="new_password"]', form).val("");
                                $('[name="confirm_new_password"]', form).val("");
                            }
                        </script>
                        <div class="popup-form" id="change-password-form">
                            <h2>Change password</h2>
                            <p>Add your message here.</p>
                            <p>&emsp;</p>
                            <form method="post" action="{$this->core->buildUrl(array('page' => 'change_password'))}">
                                <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                                <div>
                                    Password: <input type="password" name="new_password"/>
                                    Confirm: <input type="password" name="confirm_new_password"/>
                                    <a onclick="$('#change-password-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
                                    <input class="btn btn-primary" type="submit" value="Submit" />
                                </div>
                            </form>
                        </div>
                    </tr>
HTML;
        }
        $return .= <<<HTML
                </tbody>
            </table>
        </div>
        <div class="box half">
            <div class="half">
            <h2>Your Courses</h2>
                <table>
                    <tbody>
HTML;

                    foreach($courses as $course){
                        $display_text = $course->getSemester() . " " . $course->getTitle();
                        if($course->getDisplayName() !== "")
                        {
                            $display_text .= " " . $course->getDisplayName();
                        }
                        $return .= <<<HTML
                        <tr>
                            <td colspan="8">
                                <a class="btn btn-primary" style="width: 696px;white-space: normal;" href="{$this->core->buildUrl(array('component' => 'navigation', 'course' => $course->getTitle(), 'semester' => $course->getSemester()))}"> {$display_text}</a>
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
