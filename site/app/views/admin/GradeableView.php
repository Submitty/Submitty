<?php

namespace app\views\admin;

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
    <a href="https://github.com/Submitty/Submitty/tree/master/sample_files/sample_assignment_config">
    https://github.com/Submitty/Submitty/tree/master/sample_files/sample_assignment_config</a>
    </p>
    
    <br><br>
    <p>
    Prepare your assignment configuration as a single <code>config.json</code> file.<br>
    Or as a zip of the <code>config.json</code>, and the directories <code>test_input</code>,
    <code>test_output</code>, and/or <code>test_code</code>.
    </p>
    
    <br><br>

    <form action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'process_upload_config'))}" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        Upload Config: <input type="file" name="config_upload" /><br />
        <input type="submit" value="Upload" />
    </form>
    <br /><br />
    Upload Directory: {$target_dir}
    <br /><br />
    Previous Uploads:<br />
HTML;
      foreach ($all_files as $key => $value) {
          $html_output .= <<<HTML
    - {$target_dir}/{$key}<br /> 
HTML;

      }
      //$html_output .= $this->display_files($all_files);
      $html_output .= <<<HTML
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
$contents
</pre>
</div>
HTML;
      }

      return $html_output;
    }

    private function display_files($file, $output = "", $indent = 1) {
        $margin_left = 15;
        $neg_margin_left = -15 * ($indent);
        if (is_array($file)) {
            foreach($file as $k => $v) {
                $id = str_replace("/", "_", $k);
                $indent += 1;
                $output .= <<<HTML
<div>
    <span id='{$id}-span' class='icon-folder-closed'></span><a onclick='openDiv("{$id}");'>{$k}</a>
    <div id='{$id}' style='margin-left: {$margin_left}px; display: none'>
HTML;

                //$output .= $this->display_files($v, $output, $indent);
                $indent -= 1;
                $output .= <<<HTML
    </div>\n
</div>
HTML;
            }
        }
        else {
            // TODO: urlencode necessary to handle '#' in a filename
            //       htmlentities probably not necessary (and could be harmful)
            //       may want to strip url parameters too
            $html_file = htmlentities($file);
            $url_file = urlencode(htmlentities($file));
            $output .= <<<HTML
    <div>
        <div class="file-viewer"><a onclick='openFrame("{$url_file}")'>
            <span class='icon-plus'></span>{$html_file}</a>
        </div> <a onclick='openFile("{$url_file}")'>(Popout)</a><br />

        <div id="file_viewer" style='margin-left: {$neg_margin_left}px'></div>
    </div>
HTML;
        }

        return $output;
    }
}
