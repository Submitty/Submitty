<?php

namespace app\views\admin;

use app\libraries\FileUtils;
use app\views\AbstractView;

class GradeableView extends AbstractView {
    public function uploadConfigForm($target_dir, $all_files) {
        $html_output = <<<HTML
<div class="content">
    <h2>Upload Gradeable Config</h2>

    <br><br>
    <p>
    Following the assignment configuration specifications:<br>
    <a href="http://submitty.org/instructor/Assignment-Configuration">
    http://submitty.org/instructor/Assignment-Configuration</a><br>
    and examples:<br>
    <a target=_blank href="https://github.com/Submitty/Tutorial/tree/master/examples">Submitty Tutorial example autograding configurations</a><br>
    <a target=_blank href="https://github.com/Submitty/Submitty/tree/master/more_autograding_examples">Additional example autograding configurations</a><br>
    </p>
    
    <br><br>
    <p>
    Prepare your assignment configuration as a single <code>config.json</code> file.<br>
          Or as a zip of the <code>config.json</code>, and the directories <code>provided_code</code>,
          <code>test_input</code>, <code>test_output</code>, and/or <code>custom_validation_code</code>.
    </p>
    
    <br><br>

    <form action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'process_upload_config'))}" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        Upload Config: <input type="file" name="config_upload" /><br />
        <input type="submit" value="Upload" />
    </form>
</div>
HTML;
        if (count($all_files) > 0) {
            $html_output .= <<<HTML
<div class="content">
       <h2>Previous Uploads</h2>
<br>
<b>located in {$target_dir}</b>
<br>&nbsp;<br>
<ul>
HTML;
            $html_output .= $this->display_files($all_files, $target_dir);
            $html_output .= <<<HTML
</ul>
</div>
HTML;

            $semester = $this->core->getConfig()->getSemester();
            $course = $this->core->getConfig()->getCourse();
            $build_script_output_file = "/var/local/submitty/courses/" . $semester . "/" . $course . "/build_script_output.txt";
            if (file_exists($build_script_output_file)) {
                $contents = file_get_contents($build_script_output_file);
                $html_output .= <<<HTML
<div class="content">
<h2>Output from most recent BUILD_{$course}.sh</h2>
<br>
<b>{$build_script_output_file}</b>
<br>&nbsp;<br>
<pre>
{$contents}
</pre>
</div>
HTML;
            }
        }

        return $html_output;
    }

    private function display_files($file, $indent = 1, $seen_root = false) {
        $margin_left = 15;
        $neg_margin_left = -15 * ($indent);
        $output = "";
        foreach($file as $k => $v) {
            $id = str_replace(array("/", "."), "_", rtrim($v['path'], "/"));
            if (isset($v['files'])) {
                $folder_name = ($seen_root) ? $k : $v['path'];
                $output .= <<<HTML
<div>
<span id='{$id}-span' class='icon-folder-closed'></span><a onclick='openDiv("{$id}");'>{$folder_name}</a>
<div id='{$id}' style='margin-left: {$margin_left}px; display: none'>
HTML;
                $output .= $this->display_files($v['files'], $indent+1, true);
                $output .= <<<HTML
</div>\n
</div>
HTML;
            }
            else {
                $html_file = htmlentities($v['name']);
                $url_file = urlencode(htmlentities($v['name']));
                $url = $this->core->buildUrl(array('component' => 'misc', 'page' => 'display_file',
                                                   'dir' => 'config_upload', 'path' => $v['path']));

                $output .= <<<HTML
    <div>
        <div class="file-viewer"><a onclick='openFrame("{$url}", "{$id}", "{$v['name']}")'>
            <span class='icon-plus'></span>{$html_file}</a> <a onclick='openUrl("{$url}")'>(Popout)</a>
        </div> 

        <div id="file_viewer_{$id}" style='margin-left: {$neg_margin_left}px'></div>
    </div>
HTML;
            }
        }

        return $output;
    }
}
