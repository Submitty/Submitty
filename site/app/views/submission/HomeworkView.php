<?php

namespace app\views\submission;

use app\libraries\Core;
use app\models\User;

class HomeworkView {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function startContent() {
        $user = $this->core->getUser();
        return <<<HTML
<div id="content">
    <h2>Homework Submissions for {$user->getDetail('user_firstname')} {$user->getDetail('user_lastname')} ({$user->getDetail('user_id')})</h2>

HTML;
    }

    public function endContent() {
        return <<<HTML
</div>

HTML;
    }

    public function noAssignments() {
        return <<<HTML
<div class="sub">
    There are currently no released assignments. Try checking back later.
</div>

HTML;
    }

    public function assignmentSelect($assignments) {
        $return = <<<HTML
<div class="sub">
    <span style="font-weight: bold;">Select Assignment:</span>
    <select style="margin-left: 5px">
HTML;
        foreach ($assignments as $assignment) {
            $return .= "\t\t<option>{$assignment['rubric_name']}</option>\n";
        }

        $return .= <<<HTML
    </select>
</div>
HTML;

        return $return;
    }

    public function showAssignment($assignment) {
        $return = <<<HTML
<div class="spacer30"></div>
<h2>View Assignment {$assignment['rubric_name']}</h2>
<div class="panel">
    <div class="sub">
        <h3>Upload New Version</h3>
        <div class="sub">
            Prepare your assignment for submission exactly as described on the course webpage. 
            By clicking "Submit File" you are confirming that you have read, understand, and agree to follow the 
            Academic Integrity Policy.
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            <span style="font-weight: bold">Select File:</span> <input type="file" /> <input type="submit" value="Submit" />
        </form>
    </div>
</div>
<div class="panel">
    <span style="font-style: italic">No submissions for this assignment.</span>
</div>

HTML;

        return $return;

    }
}