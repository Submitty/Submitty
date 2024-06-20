<?php

namespace app\models;

use app\exceptions\BadArgumentException;
use app\exceptions\FileReadException;
use app\exceptions\MalformedDataException;
use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class RainbowCustomizationJSON
 * @package app\models
 *
 * This class is a PHP representation of a customization.json file as used in RainbowGrades and provides means
 * to update its fields.
 *
 * When adding to data to any property, the appropriate setter must be used as they perform additional validation.
 */
class RainbowCustomizationJSON extends AbstractModel {
    protected $core;

    private $section;                   // Init in constructor
    private $display_benchmark = [];
    private $messages = [];
    private $display = [];
    private $benchmark_percent;         // Init in constructor
    private object $final_cutoff;       // Init in constructor
    private $gradeables = [];
    /**
     * @var object[]
     */
    private array $plagiarism = [];

    // The order of allowed_display and allowed_display_description has to match
    const allowed_display = ['grade_summary', 'grade_details', 'benchmark_percent',
        'exam_seating', 'section', 'messages', 'warning', 'final_grade', 'manual_grade', 'final_cutoff', 'instructor_notes'];

    const allowed_display_description = [
        "Display a column(row) for each gradeable bucket on the syllabus.", //grade_summary
        "Display a column(row) for each gradeable within each gradeable bucket on the syllabus.", //grade_details
        "not used", //benchmark_percent
        "Used for assigned seating for exams, see also:  <a href='https://submitty.org/instructor/course_settings/rainbow_grades/exam_seating'>Exam Seating</a> ", //exam_seating
        "Display the students registration section.", //section
        "Display the optional text message at the top of the page.", //messages
        "not used", //warning
        "Configure cutoffs and display the student's final term letter grade.", //final_grade
        "not used", //manual_grade
        "Display the histogram of average overall grade and count of students with each final term letter grade.", //final_cutoff
        "Optional message for specific students that are only visible on the instructor gradebook, these messages are never displayed to students." //instructor_notes
    ];


    const allowed_display_benchmarks = ["average", "stddev", "perfect", "lowest_a-", "lowest_b-", "lowest_c-",
        "lowest_d"
    ];

    public function __construct(Core $main_core) {
        parent::__construct($main_core);

        // Items that must be initialized as objects
        // This is done so json_encode will properly encode the item when converting to json
        $this->section = (object) [];
        $this->benchmark_percent = (object) [];
        $this->final_cutoff = (object) [];
    }

    /**
     * Get gradeable buckets array
     *
     * @return array
     */
    public function getGradeables() {
        return $this->gradeables;
    }

    /**
     * Get array of plagiarism
     *
     * @return array<object>
     */
    public function getPlagiarism(): array {
        return $this->plagiarism;
    }


    /**
     * Gets an array of display benchmarks
     *
     * @return array The display benchmarks
     */
    public function getDisplayBenchmarks() {
        return $this->display_benchmark;
    }

    /**
     * Gets the benchmark percentages object
     *
     * @return object The benchmark percentages object
     */
    public function getBenchmarkPercent() {
        return $this->benchmark_percent;
    }

    /**
     * Gets the final cutoffs object
     *
     * @return object The final cutoffs object
     */
    public function getFinalCutoff() {
        return $this->final_cutoff;
    }

    /**
     * Gets an array of display
     * @return array<string> The display
     */
    public function getDisplay(): array {
        return $this->display;
    }



    /**
     * Adds a benchmark to the display_benchmarks
     * If it already exists in the array no changes are made
     *
     * @param string $benchmark The benchmark to add
     * @throws BadArgumentException The passed in argument is not allowed
     */
    public function addDisplayBenchmarks(string $benchmark) {
        if (!in_array($benchmark, self::allowed_display_benchmarks)) {
            throw new BadArgumentException('Passed in benchmark not found in the list of allowed benchmarks');
        }

        if (!in_array($benchmark, $this->display_benchmark)) {
            array_push($this->display_benchmark, $benchmark);
        }
    }

    /**
     * Determine the existence of a custom_customization.json inside the course rainbow_grades directory
     *
     * @return bool Indicates if a custom_customization.json exists
     */
    public function doesCustomCustomizationExist() {
        // Get path to custom_customization.json
        $course_path = $this->core->getConfig()->getCoursePath();
        $file_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'custom_customization.json');

        return file_exists($file_path);
    }

    /**
     * Loads the data from the course's rainbow grades customization.json into this php object
     *
     * @throws FileReadException Failure to read the contents of the file
     * @throws MalformedDataException Failure to decode the contents of the JSON string
     */
    public function loadFromJsonFile() {
        // Get contents of file and decode
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'customization.json');

        if (!file_exists($course_path)) {
            throw new FileReadException('Unable to locate the file to read');
        }

        $file_contents = file_get_contents($course_path);

        // Validate file read
        if ($file_contents === false) {
            throw new FileReadException('An error occurred trying to read the contents of customization file.');
        }

        $json = json_decode($file_contents);

        // Validate decode
        if ($json === null) {
            throw new MalformedDataException('Unable to decode JSON string');
        }

        if (isset($json->display_benchmark)) {
            $this->display_benchmark = $json->display_benchmark;
        }

        if (isset($json->section)) {
            $this->section = $json->section;
        }

        if (isset($json->messages)) {
            $this->messages = $json->messages;
        }

        if (isset($json->display)) {
            $this->display = $json->display;
        }

        if (isset($json->benchmark_percent)) {
            $this->benchmark_percent = $json->benchmark_percent;
        }

        if (isset($json->final_cutoff)) {
            $this->final_cutoff = $json->final_cutoff;
        }

        if (isset($json->gradeables)) {
            $this->gradeables = $json->gradeables;
        }

        if (isset($json->plagiarism)) {
            $this->plagiarism = $json->plagiarism;
        }
    }

    /**
     * Add an item to the 'display' array
     *
     * @param string $display The item to add
     * @throws BadArgumentException The passed in argument is not allowed.
     */
    public function addDisplay($display) {
        if (!in_array($display, self::allowed_display)) {
            throw new BadArgumentException('Passed in display not found in the list of allowed display items');
        }

        if (!in_array($display, $this->display)) {
            $this->display[] = $display;
        }
    }

    /**
     * Add a section label
     *
     * @param string $sectionID The sectionID
     * @param string $label The label you would like to assign to the sectionID
     * @throws BadArgumentException The passed in section label is empty
     */
    public function addSection($sectionID, $label) {
        if (empty($label)) {
            throw new BadArgumentException('The section label may not be empty.');
        }

        $this->section->$sectionID = $label;
    }

    /**
     * Add a benchmark percent
     *
     * @param string $benchmark The benchmark - this is the key for this json field
     * @param float $percent The percent - this is the value for this json field
     * @throws BadArgumentException The passed in percent was empty
     */
    public function addBenchmarkPercent($benchmark, $percent) {
        if (empty($percent)) {
            throw new BadArgumentException('The benchmark percent may not be empty.');
        }

        $this->benchmark_percent->$benchmark = (float) $percent;
    }

    /**
     * Add a final cutoff
     *
     * @param string $cutoff The cutoff - this is the key for this json field
     * @param float $percent The percent - this is the value for this json field
     */
    public function addFinalCutoff(string $cutoff, float $percent): void {
        // To satisfy php-lint, add the pair to an array, then cast the array back to an object
        $final_cutoff_array = (array) $this->final_cutoff;
        $final_cutoff_array[$cutoff] = $percent;
        $this->final_cutoff = (object) $final_cutoff_array;
    }

    /**
     * Get the section object
     *
     * @return object
     */
    public function getSection() {
        return $this->section;
    }

    /**
     * Add a gradeable object to the gradeables array
     *
     * @param object $gradeable
     */
    public function addGradeable(object $gradeable) {
        // Validation of this item will be better handled when schema validation is complete, until then just make
        // sure gradeable is not empty
        $emptyObject = (object) [];
        if ($gradeable == $emptyObject) {
            throw new BadArgumentException('Gradeable may not be empty.');
        }

        $this->gradeables[] = $gradeable;
    }

    /**
     * Get messages
     *
     * @return array
     */
    public function getMessages() {
        return $this->messages;
    }


    /**
     * Add a message to the message array
     *
     * @param string $message
     */
    public function addMessage(string $message) {
        if (empty($message)) {
            throw new BadArgumentException('You may not add an empty message.');
        }

        $this->messages[] = $message;
    }


    /**
     * Add plagiarism entry to existing array
     *
     * @param object{
     *     "user": string,
     *     "gradeable": string,
     *     "penalty": int
     * } $plagiarismEntry
     */
    public function addPlagiarismEntry(object $plagiarismEntry): void {
        $emptyArray = [
            "user" => "",
            "gradeable" => "",
            "penalty" => 0
        ];

        $plagiarismArray = json_decode(json_encode($plagiarismEntry), true);

        if ($plagiarismArray === $emptyArray) {
            throw new BadArgumentException('Plagiarism entry may not be empty.');
        }

        $this->plagiarism[] = $plagiarismEntry;
    }



    /**
     * Save the contents in this objects properties to the customization.json for the current course
     */
    public function saveToJsonFile() {
        // Get path of where to save file
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'customization.json');

        // If display was empty then just add defaults
        if (empty($this->display)) {
            $this->addDisplay('grade_summary');
            $this->addDisplay('grade_details');
        }

        // Create object that will be written to file after collecting non-empty items
        $json = (object) [];

        // Copy each property from $this over to $json
        // @phpstan-ignore-next-line phpstan devs do not like object iteration
        foreach ($this as $key => $value) {
            // Dont include $core or $modified
            if ($key != 'core' && $key != 'modified') {
                $json->$key = $value;
            }
        }

        // Encode
        $json = json_encode($json, JSON_PRETTY_PRINT);

        // Write to file
        file_put_contents($course_path, $json);
    }
}
