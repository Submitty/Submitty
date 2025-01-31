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

    private object $section;                   // Init in constructor
    /**
     * @var string[]
     */
    private array $display_benchmark = [];
    /**
     * @var string[]
     */
    private array $messages = [];
    /**
     * @var string[]
     */
    private array $display = [];
    private object $benchmark_percent;         // Init in constructor
    private object $final_cutoff;       // Init in constructor
    /**
     * @var object[]
     */
    private array $gradeables = [];
    /**
     * @var object[]
     */
    private array $plagiarism = [];
    /**
     * @var object[]
     */
    private array $manual_grade = [];
    /**
     * @var object[]
     */
    private array $warning = [];

    // The order of allowed_display and allowed_display_description has to match
    const allowed_display = ['grade_summary', 'grade_details', 'exam_seating', 'section',
        'messages', 'warning', 'final_grade', 'final_cutoff', 'instructor_notes', 'display_rank_to_individual'];

    const allowed_display_description = [
        "Display a column(row) for each gradeable bucket on the syllabus.", //grade_summary
        "Display a column(row) for each gradeable within each gradeable bucket on the syllabus.", //grade_details
        "Used for assigned seating for exams, see also:  <a href='https://submitty.org/instructor/course_settings/rainbow_grades/exam_seating'>Exam Seating</a> ", //exam_seating
        "Display the students registration section.", //section
        "Display the optional text message at the top of the page.", //messages
        "Generate Academic Performance Warnings for each student that fails to obtain a target score on a given list of gradeables.", //warning
        "Configure cutoffs and display the student's final term letter grade.", //final_grade
        "Display the histogram of average overall grade and count of students with each final term letter grade.", //final_cutoff
        "Optional message for specific students that are only visible on the instructor gradebook, these messages are never displayed to students.", //instructor_notes
        "Display each student's rank in the course to themselves." //display_rank_to_individual
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
     * @return object[]
     */
    public function getGradeables() {
        return $this->gradeables;
    }

    /**
     * Get array of plagiarism
     *
     * @return object[]
     */
    public function getPlagiarism(): array {
        return $this->plagiarism;
    }

    /**
     * Get array of manual grades
     *
     * @return object[]
     */
    public function getManualGrades(): array {
        return $this->manual_grade;
    }

    /**
     * Get array of performance warnings
     *
     * @return array<object>
     */
    public function getPerformanceWarnings(): array {
        return $this->warning;
    }

    /**
     * Gets an array of display benchmarks
     *
     * @return string[] The display benchmarks
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
     * @return string[] The display
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
    public function addDisplayBenchmarks(string $benchmark): void {
        if (!in_array($benchmark, self::allowed_display_benchmarks, true)) {
            throw new BadArgumentException('Passed in benchmark not found in the list of allowed benchmarks');
        }

        if (!in_array($benchmark, $this->display_benchmark, true)) {
            array_push($this->display_benchmark, $benchmark);
        }
    }

    /**
     * Determine the existence of a manual_customization.json inside the course rainbow_grades directory
     *
     * @return bool Indicates if a manual_customization.json exists
     */
    public function doesManualCustomizationExist() {
        // Get path to manual_customization.json
        $course_path = $this->core->getConfig()->getCoursePath();
        $file_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'manual_customization.json');

        return file_exists($file_path);
    }

    /**
     * Loads the data from the course's rainbow grades customization.json into this php object
     *
     * @throws FileReadException Failure to read the contents of the file
     * @throws MalformedDataException Failure to decode the contents of the JSON string
     */
    public function loadFromJsonFile(): void {
        // Get contents of file and decode
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'gui_customization.json');

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

        if (isset($json->manual_grade)) {
            $this->manual_grade = $json->manual_grade;
        }

        if (isset($json->warning)) {
            $this->warning = $json->warning;
        }
    }

    /**
     * Add an item to the 'display' array
     *
     * @param string $display The item to add
     * @throws BadArgumentException The passed in argument is not allowed.
     */
    public function addDisplay(string $display): void {
        if (!in_array($display, self::allowed_display, true)) {
            throw new BadArgumentException('Passed in display not found in the list of allowed display items');
        }

        if (!in_array($display, $this->display, true)) {
            $this->display[] = $display;
        }
    }

    /**
     * Add a section label
     *
     * @param string $sectionID The sectionID
     * @param string $label The label you would like to assign to the sectionID
     */
    public function addSection(string $sectionID, string $label): void {
        // If label is not set, use sectionID as default
        $this->section->$sectionID = $label === '' ? $sectionID : $label;
    }

    /**
     * Add a benchmark percent
     *
     * @param string $benchmark The benchmark - this is the key for this json field
     * @param float $percent The percent - this is the value for this json field
     * @throws BadArgumentException The passed in percent was empty
     */
    public function addBenchmarkPercent(string $benchmark, ?float $percent): void {
        if (is_null($percent)) {
            throw new BadArgumentException('The benchmark percent may not be empty.');
        }

        $this->benchmark_percent->$benchmark = $percent;
    }

    /**
     * Add a final cutoff
     *
     * @param string $cutoff The cutoff - this is the key for this json field
     * @param float $percent The percent - this is the value for this json field
     */
    public function addFinalCutoff(string $cutoff, ?float $percent): void {
        if (is_null($percent)) {
            throw new BadArgumentException('The final cutoff may not be empty.');
        }

        $this->final_cutoff->$cutoff = $percent;
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
    public function addGradeable(object $gradeable): void {
        // Validation of this item will be better handled when schema validation is complete, until then just make
        // sure gradeable is not empty
        $emptyObject = (object) [];
        if ($gradeable === $emptyObject) {
            throw new BadArgumentException('Gradeable may not be empty.');
        }

        $this->gradeables[] = $gradeable;
    }

    /**
     * Get messages
     *
     * @return string[]
     */
    public function getMessages() {
        return $this->messages;
    }


    /**
     * Add a message to the message array
     *
     * @param string $message
     */
    public function addMessage(string $message): void {
        if ($message === '') {
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
     * Add a manual grade entry to existing array
     *
     * @param object{
     *     "user": string,
     *     "grade": string,
     *     "note": string
     * } $manualGradeEntry
     */
    public function addManualGradeEntry(object $manualGradeEntry): void {
        $emptyArray = [
            "user" => "",
            "grade" => "",
            "note" => ""
        ];

        $manualGradeArray = (array) $manualGradeEntry;

        if ($manualGradeArray === $emptyArray) {
            throw new BadArgumentException('Manual grading entry may not be empty.');
        }

        $this->manual_grade[] = $manualGradeEntry;
    }


    /**
     * Add a performance warning entry to existing array
     *
     * @param object{
     *     "msg": string,
     *     "ids": string,
     *     "value": float
     * } $performanceWarningEntry
     */
    public function addPerformanceWarningEntry(object $performanceWarningEntry): void {
        $emptyArray = [
            "user" => "",
            "grade" => "",
            "note" => ""
        ];

        $performanceWarningArray = (array) $performanceWarningEntry;

        if ($performanceWarningArray === $emptyArray) {
            throw new BadArgumentException('Performance Warning entry may not be empty.');
        }

        $this->warning[] = $performanceWarningEntry;
    }


    /**
     * Save the contents in this objects properties to the customization.json for the current course
     */
    public function saveToJsonFile(): void {
        // Get path of where to save file
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'gui_customization.json');

        // If display was empty then just add defaults
        if (count($this->display) === 0) {
            $this->addDisplay('grade_summary');
            $this->addDisplay('grade_details');
        }

        // Create object that will be written to file after collecting non-empty items
        $json = (object) [];

        // Copy each property from $this over to $json
        // @phpstan-ignore-next-line phpstan devs do not like object iteration
        foreach ($this as $key => $value) {
            // Dont include $core or $modified
            if ($key !== 'core' && $key !== 'modified') {
                $json->$key = $value;
            }
        }

        // Encode
        $json = json_encode($json, JSON_PRETTY_PRINT);

        // Write to file
        file_put_contents($course_path, $json);
    }
}
