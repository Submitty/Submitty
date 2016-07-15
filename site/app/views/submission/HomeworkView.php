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

    public function noAssignments() {
        return <<<HTML
<div class="sub">
    There are currently no released assignments. Try checking back later.
</div>

HTML;
    }

    public function assignmentSelect($assignments, $assignment_id) {
        $return = <<<HTML
<div class="sub">
    <span style="font-weight: bold;">Select Assignment:</span>
    <select style="margin-left: 5px">
HTML;
        foreach ($assignments as $assignment) {
            if ($assignment_id === $assignment['assignment_id']) {
                $selected = "selected";
            }
            else {
                $selected = "";
            }
            $return .= "\t\t<option {$selected}>{$assignment['assignment_name']}</option>\n";
        }

        $return .= <<<HTML
    </select>
</div>
HTML;

        return $return;
    }

    public function showAssignment($assignment, $assignment_select) {
        $return = <<<HTML
<script src="{$this->core->getConfig()->getBaseUrl()}js/drag_and_drop.js"></script>
{$assignment_select}
<div class="content">
    <h2>View Assignment {$assignment['assignment_name']}</h2>
    <div class="sub">
        Prepare your assignment for submission exactly as described on the course webpage.
        By clicking "Submit File" you are confirming that you have read, understand, and agree to follow the
        Academic Integrity Policy.
    </div>
    <div id="upload-boxes" style="display:table; border-spacing: 5px; width:100%">
HTML;
        for ($i = 1; $i <= $assignment['num_parts']; $i++) {
            $return .= <<<HTML
        <div id="upload{$i}" style="cursor: pointer; text-align: center; border: dashed 2px lightgrey; display:table-cell; height: 150px;">
            <h3 class="label" id="label{$i}">Drag your {$assignment['part_names'][($i-1)]} here or click to open file browser</h3>
            <input type="file" name="files" id="input_file{$i}" style="display: none" onchange="addFilesFromInput({$i})" />
        </div>
HTML;
        }
        $return .= <<<HTML
    </div>
    <button type="button" id="submit" class="btn btn-primary">Submit</button>
    <button type="button" id="startnew" class="btn btn-primary">Start New</button>
</div>
<div class="content">
    <span style="font-style: italic">No submissions for this assignment.</span>
</div>
HTML;

        return $return;

    }
}