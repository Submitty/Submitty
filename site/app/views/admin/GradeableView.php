<?php

namespace app\views\admin;

use app\views\AbstractView;

class GradeableView extends AbstractView {
    public function uploadConfigForm() {

      $html_output = <<<HTML
<div class="content">
    <h2>Upload Gradeable Config</h2>

<br>&nbsp;<br>
<p>
Following the assignment configuration specifications:<br>
<a href="http://submitty.org/instructor/Assignment-Configuration">
http://submitty.org/instructor/Assignment-Configuration</a><br>
and examples:<br>
<a href="https://github.com/Submitty/Submitty/tree/master/sample_files/sample_assignment_config">
https://github.com/Submitty/Submitty/tree/master/sample_files/sample_assignment_config</a>
</p>

<br>&nbsp;<br>
<p>
Prepare your assignment configuration as a single <tt>config.json</tt> file.<br>
Or as a zip of the <tt>config.json</tt>, and the directories <tt>test_input</tt>,
<tt>test_output</tt>, and/or <tt>test_code</tt>.
</p>

<br>&nbsp;<br>

    <form action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'process_upload_config'))}" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        Upload Config: <input type="file" name="config_upload" /><br />
        <input type="submit" value="Upload" />
    </form>
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
}
