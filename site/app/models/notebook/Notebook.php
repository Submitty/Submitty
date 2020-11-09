<?php

namespace app\models\notebook;

use app\libraries\CodeMirrorUtils;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\models\AbstractModel;
use app\exceptions\NotImplementedException;
use app\exceptions\AuthorizationException;
use app\exceptions\FileNotFoundException;
use app\exceptions\IOException;

/**
 * @method array getInputs()
 * @method array getNotebook()
 * @method array getImagePaths()
 * @method array getFileSubmissions()
 */

class Notebook extends AbstractModel {
    /** @prop @var array parsed notebook from the config */
    protected $notebook;
    /** @prop @var array notebook elements that can hold user input */
    protected $inputs = [];
    /** @prop @var string parsed notebook from the config */
    protected $gradeable_id;
    /** @prop @var array of image names and their locations */
    protected $image_paths;
    /** @prop @var array of file_submission notebook cells */
    protected $file_submissions = [];


    public function __construct(Core $core, array $details, string $gradeable_id) {
        parent::__construct($core);

        $this->gradeable_id = $gradeable_id;
        $this->parseNotebook($details);
    }


    protected function parseNotebook(array $details): void {
         // Setup $this->notebook
        $actual_input = [];
        $this->notebook = [];

        // For each item in the notebook array inside the $details collect data and assign to variables in
        // $this->notebook
        for ($i = 0; $i < count($details); $i++) {
            $notebook_cell = $details[$i];
            $do_add = true;

            // If cell is of markdown type then figure out if it is markdown_string or markdown_file and pass this
            // markdown forward as 'data' as opposed to 'string' or 'file'
            if (
                isset($notebook_cell['type'])
                && $notebook_cell['type'] === 'markdown'
            ) {
                $markdown = $this->getMarkdownData($notebook_cell);

                // Remove string or file from $notebook_cell
                unset($notebook_cell['markdown_string']);
                unset($notebook_cell['markdown_file']);

                // Read as data
                $notebook_cell['markdown_data'] = $markdown;

                // If next entry is an input type, we assign this as a label - otherwise it is plain markdown
                if ($i < count($details) - 1) {
                    $next_cell = &$details[$i + 1];
                    if (
                        isset($next_cell['type'])
                        && ($next_cell['type'] == 'short_answer' || $next_cell['type'] == 'multiple_choice')
                    ) {
                        $next_cell['label'] = $markdown;
                        // Do not add current cell to notebook, since it is embedded in the label
                        $do_add = false;
                    }
                }
            }
            elseif (
                isset($notebook_cell['type'])
                && $notebook_cell['type'] === 'short_answer'
            ) {
                $notebook_cell['codemirror_mode'] = CodeMirrorUtils::getCodeMirrorMode($notebook_cell['programming_language'] ?? null);
            }

            // Add this cell $this->notebook
            if ($do_add) {
                $this->notebook[] = $notebook_cell;
            }

            // If cell is a type of input add it to the $actual_inputs array
            if (isset($notebook_cell['type']) && in_array($notebook_cell['type'], ['short_answer', 'multiple_choice'])) {
                $actual_input[] = $notebook_cell;
            }

            if (
                isset($notebook_cell['type'])
                && $notebook_cell['type'] === 'file_submission'
                && !in_array($notebook_cell, $this->file_submissions)
            ) {
                $notebook_cell['label'] = $notebook_cell['label'] ?? "";
                $this->file_submissions[] = $notebook_cell;
            }
        }

        $this->image_paths = $this->getNotebookImagePaths();

        // Setup $this->inputs
        for ($i = 0; $i < count($actual_input); $i++) {
            if ($actual_input[$i]['type'] == 'short_answer') {
                    $this->inputs[$i] = new SubmissionCodeBox($this->core, $actual_input[$i]);
            }
            elseif ($actual_input[$i]['type'] == 'multiple_choice') {
                $actual_input[$i]['allow_multiple'] = $actual_input[$i]['allow_multiple'] ?? false;

                $this->inputs[$i] = new SubmissionMultipleChoice($this->core, $actual_input[$i]);
            }
        }

        if (isset($details['item_pool'])) {
            $this->notebook['item_pool'] = $details['item_pool'];
        }
    }


    private function getMarkdownData($cell) {
        // If markdown_string is set then just return that
        if (isset($cell['markdown_string'])) {
            return $cell['markdown_string'];
        }
        elseif (isset($cell['markdown_file'])) {
            // Else if markdown_file is set then read the file and return its contents
            // TODO: Implement reading from markdown_file and passing that along
            throw new NotImplementedException("Reading from a markdown_file is not yet implemented.");
        }
        else {
            // Else something unexpected happened
            throw new \InvalidArgumentException("An error occured parsing notebook data.\n" .
                "Markdown configuration may only specify one of 'markdown_string' or 'markdown_file'");
        }
    }


   /**
    * Gets a new 'notebook' which contains information about most recent submissions
    *
    * @return array An updated 'notebook' which has the most recent submission data entered into the
    * @param array $new_notebook a notebook config to parse
    * @param int $version which version to get notebook submission values from
    * @param string $student_id which student's notebook to pull data from
    */
    public function getMostRecentNotebookSubmissions(int $version, array $new_notebook, string $student_id): array {
        foreach ($new_notebook as $notebookKey => $notebookVal) {
            if (isset($notebookVal['type'])) {
                if ($notebookVal['type'] == "short_answer") {
                    // If no previous submissions set string to default initial_value
                    if ($version === 0) {
                        $recentSubmission = $notebookVal['initial_value'] ?? "";
                    }
                    else {
                        // Else there has been a previous submission try to get it
                        try {
                            // Try to get the most recent submission
                            $recentSubmission = $this->getRecentSubmissionContents($notebookVal['filename'], $version, $student_id);
                        }
                        catch (AuthorizationException $e) {
                            // If the user lacked permission then just set to default instructor provided string
                            $recentSubmission = $notebookVal['initial_value'] ?? "";
                        }
                    }

                    // Add field to the array
                    $new_notebook[$notebookKey]['recent_submission'] = $recentSubmission;
                }
                elseif ($notebookVal['type'] == "multiple_choice") {
                    // If no previous submissions do nothing, else there has been, so try and get it
                    if ($version === 0) {
                        continue;
                    }
                    else {
                        try {
                            // Try to get the most recent submission
                            $recentSubmission = $this->getRecentSubmissionContents($notebookVal['filename'], $version, $student_id);

                            // Add field to the array
                            $new_notebook[$notebookKey]['recent_submission'] = $recentSubmission;
                        }
                        catch (AuthorizationException $e) {
                            // If failed to get the most recent submission then skip
                            continue;
                        }
                    }
                }
            }
        }

        // Operate on notebook to add prev_submission field to inputs
        return $new_notebook;
    }


    /**
     * Get the data from the student's most recent submission
     *
     * @param string $filename Name of the file to collect the data out of
     * @param string $version which version to get from
     * @param string $student_id id of which user to collect data from
     * @throws AuthorizationException if the user lacks permissions to read the submissions file
     * @throws FileNotFoundException if file with passed filename could not be found
     * @throws IOException if there was an error reading contents from the file
     * @return string if successful returns the contents of a students most recent submission
     */
    private function getRecentSubmissionContents(string $filename, string $version, string $student_id): string {

        // Get items in path to student's submission folder
        $course_path = $this->core->getConfig()->getCoursePath();
        $gradable_dir = $this->getGradeableId();

        // Join path items
        $complete_file_path = FileUtils::joinPaths(
            $course_path,
            'submissions',
            $gradable_dir,
            $student_id,
            $version,
            $filename
        );

        // Check if the user has permission to access this submission
        $isAuthorized = $this->core->getAccess()->canI('path.read', ["dir" => "submissions", "path" => $complete_file_path]);

        // If user lacks permission to get the submission contents throw Auth exception
        if (!$isAuthorized) {
            throw new AuthorizationException("The user lacks permissions to access this data.");
        }

        // If desired file does not exist in the most recent submission directory throw exception
        if (!file_exists($complete_file_path)) {
            throw new FileNotFoundException("Unable to locate submission file.");
        }

        // Read file contents into string
        $file_contents = file_get_contents($complete_file_path);

        // If file_contents is False an error has occured
        if ($file_contents === false) {
            throw new IOException("An error occurred retrieving submission contents.");
        }

        // Remove trailing newline
        $file_contents = rtrim($file_contents, "\n");

        return $file_contents;
    }


    private function getNotebookImagePaths() {
        $image_paths = [];
        foreach ($this->notebook as $cell) {
            if (isset($cell['type']) && $cell['type'] == "image") {
                $image_name = $cell['image'];
                $imgPath = FileUtils::joinPaths(
                    $this->core->getConfig()->getCoursePath(),
                    'test_input',
                    $this->gradeable_id,
                    $image_name
                );
                $content_type = FileUtils::getContentType($imgPath);
                if (substr($content_type, 0, 5) === 'image') {
                    // Read image path, convert to base64 encoding
                    $inputImageData = base64_encode(file_get_contents($imgPath));
                    // Format the image SRC:  data:{mime};base64,{data};
                    $inputimagesrc = 'data: ' . mime_content_type($imgPath) . ';charset=utf-8;base64,' . $inputImageData;
                    // insert the sample image data
                    $image_paths[$image_name] = $inputimagesrc;
                }
            }
        }
        return $image_paths;
    }


    public function getNumParts(): int {
        return count($this->file_submissions);
    }
}
