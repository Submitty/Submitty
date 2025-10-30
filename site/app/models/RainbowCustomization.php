<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\FileUtils;

/**
 * Class RainbowCustomization
 * @package app\models
 *
 * This class is a RainbowGrades Customization.  It may contain the data found in customization.json but it also
 * contains additional data that is used by the web user interface to aid in generation/customization.
 */
class RainbowCustomization extends AbstractModel {
    /**/
    protected $core;
    /**
     * @var array<string,int>
     */
    private array $bucket_counts = [];                  // Keep track of how many items are in each bucket
    /**
     * @var array<string,int>
     */
    private array $bucket_remove_lowest = [];           // get how many droplowest are in each bucket
    /**
     * @var array<string,array<mixed>>
     */
    private array $customization_data = [];
    /**
     * @var string
     */
    private string $has_error;
    /**
     * @var string[]
     */
    private array $error_messages;
    /**
     * @var string[]
     */
    private array $used_buckets = [];
    /**
     * @var string[]
     */
    private array $available_buckets;
    private ?object $RCJSON;                            // This is the customization.json php object, or null if it wasn't found

    /*XXX: This is duplicated from AdminGradeableController.php, we really shouldn't have multiple copies lying around.
     * On top of that, Rainbow Grades has its own enum internally. Since that's a separate repo it's probably
     * unavoidable, but the fewer places we can duplicate this, the better.
     */
    /*XXX: It's also going to be annoying to have "none (for practice only)" since in customization it's "none"
     * which is a lot nicer to type.
     */
    //XXX: 'none (for practice only)' we want to truncate to just 'none'.
    const syllabus_buckets = [
        'homework', 'assignment', 'problem-set',
        'quiz', 'test', 'exam',
        'exercise', 'lecture-exercise', 'reading', 'lab', 'recitation', 'worksheet',
        'project',
        'participation', 'note',
        'none'];


    public function __construct(Core $main_core) {
        parent::__construct($main_core);
        $this->has_error = "false";
        $this->error_messages = [];

        // Attempt to load json from customization file
        // If it fails then set to null, will be used to load defaults later
        $this->RCJSON = new RainbowCustomizationJSON($this->core);

        try {
            $this->RCJSON->loadFromJsonFile();
        }
        catch (\Exception $e) {
            $this->RCJSON = null;
        }
    }

    public function buildCustomization(): void {

        //This function should examine the DB(?) / a file(?) and if customization settings already exist, use them. Otherwise, populate with defaults.
        foreach (self::syllabus_buckets as $bucket) {
            $this->customization_data[$bucket] = [];
            $this->bucket_counts[$bucket] = 0;
            $this->bucket_remove_lowest[$bucket] = 0;
        }
        $gradeable_buckets = [];
        $gradeables = $this->core->getQueries()->getGradeableConfigs(null);
        foreach ($gradeables as $gradeable) {
            //XXX: 'none (for practice only)' we want to truncate to just 'none', otherwise use full bucket name
            $bucket = $gradeable->getSyllabusBucket() === "none (for practice only)" ? "none" : $gradeable->getSyllabusBucket();
            /*if(!isset($this->customization_data[$bucket])){
                $this->customization_data[$bucket] = [];
            }*/
            /*XXX: Right now we aren't yet worried about pulling in from existing customization.json but if we do, then what happens in the event of a conflict?
             * I'm tempted to say for version 1.0, too bad, we override with the version from the DB (since that's more up to date), if the gradeable exists
             * In a later version we may want to highlight it in red or something, and ask the user which number to use? I'm not sure if there's a use case for using
             * the version in the customization.json, but the warning might be nice. Might even be an error state where action is required, just so the user isn't
             * confused when the grade distribution shifts around.
             */

            // Update bucket count
            $this->bucket_counts[$bucket]++;

            $manual_grading_points = $gradeable->getManualGradingPoints();
            $autograded_grading_points = 0;

            //If the gradeable has autograding points, load the config and add the non-extra-credit autograder total
            if ($gradeable->hasAutogradingConfig()) {
                $autograded_grading_points = $gradeable->getAutogradingConfig()->getTotalNonExtraCredit();
            }
            $max_score = $manual_grading_points + $autograded_grading_points;
            $gradeable_buckets[$gradeable->getId()] = $bucket;
            $this->customization_data[$bucket][] = [
                "id" => $gradeable->getId(),
                "title" => $gradeable->getTitle(),
                "max_score" => $max_score,
                "manual_grading_points" => $manual_grading_points,
                "autograded_grading_points" => $autograded_grading_points,
                "grade_release_date" => $gradeable->hasReleaseDate() ? DateUtils::dateTimeToString($gradeable->getGradeReleasedDate()) : DateUtils::dateTimeToString($gradeable->getSubmissionOpenDate()),
                "override_percent" => false
            ];
        }
        // Determine which 'buckets' exist in the customization.json
        if (!is_null($this->RCJSON)) {
            $json_buckets = $this->RCJSON->getGradeables();
            foreach ($json_buckets as $json_bucket) {
                // Place those buckets in $this->used_buckets
                $this->used_buckets[] = $json_bucket->type;

                // When preparing the count of how many items are in the bucket, if the instructor has previously
                // entered a value which was greater than the number of gradeables in the database, we should use the
                // instructor entered value instead
                $bucket = $json_bucket->type;

                // Filter out removed gradeables or updated gradeable buckets
                $this->customization_data[$bucket] = array_values(array_filter($this->customization_data[$bucket], function ($g) use ($gradeable_buckets, $json_bucket) {
                    $removed = !isset($gradeable_buckets[$g['id']]);
                    $swapped = !$removed && $gradeable_buckets[$g['id']] !== $json_bucket->type;
                    return !$removed && !$swapped;
                }));

                if ($json_bucket->count > $this->bucket_counts[$bucket]) {
                    $this->bucket_counts[$bucket] = $json_bucket->count;
                }
                $this->bucket_remove_lowest[$bucket] = $json_bucket->remove_lowest ?? 0;
            }

            // If there are no assigned buckets, automatically assign buckets that contain gradeables
            if (count($this->used_buckets) === 0) {
                foreach (self::syllabus_buckets as $potential_bucket) {
                    if ($this->bucket_counts[$potential_bucket] > 0) {
                        $this->used_buckets[] = $potential_bucket;
                    }
                }
            }
        }

        // Reorder buckets
        $this->reorderBuckets();

        //Now that the buckets are ordered and the customization has been initialized, we can
        //loop through to find differences between the percent values from the database vs the customization JSON
        if (!is_null($this->RCJSON) && count($this->RCJSON->getGradeables()) > 0) {
            $json_buckets = $this->RCJSON->getGradeables();
            //we have to keep track of the customization bucket and the JSON bucket separately, since the customization
            //has all buckets (even empty ones) while the JSON only has buckets with content in it.
            $c_bucket = "";
            foreach ($json_buckets as $json_bucket) {
                if (property_exists($json_bucket, 'type')) {
                    $c_bucket = $json_bucket->type;
                }
                else {
                    continue;
                }

                //loop through all gradeables in bucket and compare them
                $j_index = 0;
                foreach ($this->customization_data[$c_bucket] as &$c_gradeable) {
                    if (isset($json_bucket->ids[$j_index]) && property_exists($json_bucket->ids[$j_index], 'percent')) {
                        $c_gradeable['override_percent'] = true;
                        $c_gradeable['percent'] = ($json_bucket->ids[$j_index]->percent) * 100;
                    }
                    $j_index++;
                }
            }
        }
        //XXX: Assuming that the contents of these buckets will be lowercase
        $this->available_buckets = array_diff(self::syllabus_buckets, $this->used_buckets);
    }

    /**
     * Reorder each gradeable bucket in $this->customization_data to match JSON (or grade release date as a fallback) ordering
     */
    private function reorderBuckets(): void {
        $json_buckets_gradeables = [];

        // First, fetch JSON and associate ids with buckets
        if (!is_null($this->RCJSON)) {
            $json_buckets = $this->RCJSON->getGradeables();
            foreach ($json_buckets as $json_bucket) {
                if (property_exists($json_bucket, 'type') && property_exists($json_bucket, 'ids')) {
                    $json_buckets_gradeables[$json_bucket->type] = $json_bucket->ids;
                }
            }
        }

        // Reorder individual buckets
        $temp_customization_data = [];
        foreach ($this->customization_data as $bucket => $gradeables) {
            $json_bucket_ids = array_key_exists($bucket, $json_buckets_gradeables) ? $json_buckets_gradeables[$bucket] : [];
            $temp_customization_data[$bucket] = $this->reorderBucket($gradeables, $json_bucket_ids);
        }
        $this->customization_data = $temp_customization_data;
    }

    /**
     * Returns $bucket_gradeables reordered to match order in $json_bucket_ids.
     * If a gradeable is not present in $json_bucket_ids, it will be added to the end of the array in order of grade release date.
     * @param array<mixed> $bucket_gradeables A gradeable bucket from $this->customization_data
     * @param array<mixed> $json_bucket_ids An "ids" array from a $this->RCSJSON bucket
     * @return array<mixed>
     */
    private function reorderBucket(array $bucket_gradeables, array $json_bucket_ids): array {
        $new_gradeables = [];

        // First, associate gradeables with their IDs
        $gradeables_by_id = array_reduce($bucket_gradeables, function ($accumulator, $gradeable) {
            $accumulator[$gradeable['id']] = $gradeable;
            return $accumulator;
        }, []);

        // Then, add gradeables to $new_gradeables based on JSON ordering
        foreach ($json_bucket_ids as $json_gradeable) {
            if (property_exists($json_gradeable, 'id')) {
                $id = $json_gradeable->id;
                if (array_key_exists($id, $gradeables_by_id)) {
                    $new_gradeables[] = $gradeables_by_id[$id];
                    unset($gradeables_by_id[$id]);
                }
            }
        }

        // Finally, add any remaining gradeables to $new_gradeables based on date ordering.
        $num_unordered_gradeables = count($gradeables_by_id);
        $gradeables_by_date = [];
        foreach ($gradeables_by_id as $gradeable) {
            $num_gradeables_counted = count($gradeables_by_date);

            // Ensure strings are sorted properly
            $gradeable_count_string = str_repeat(
                '0',
                strlen(strval($num_unordered_gradeables)) - strlen(strval($num_gradeables_counted))
            ) . $num_gradeables_counted;

            if (array_key_exists('grade_release_date', $gradeable)) {
                $gradeables_by_date[$gradeable['grade_release_date'] . '_' . $gradeable_count_string] = $gradeable;
            }
            else {
                $gradeables_by_date['END_OF_TIME_' . $gradeable_count_string] = $gradeable;
            }
        }
        ksort($gradeables_by_date);
        foreach ($gradeables_by_date as $gradeable) {
            $new_gradeables[] = $gradeable;
        }

        return $new_gradeables;
    }

    /**
     * Gets curve data for each gradeable
     *
     * In the case no rainbow grades customization.json was found the return value will be an empty array.
     * In the case a customization.json was found the return array will have the form
     * $retArray[bucket_id][gradeable_id] = curve_values
     *
     * If no curve values were found for gradeable_id then gradeable_id will not be present in the return array
     *
     * @return array<string,array<string,array<string|float>>>
     */
    public function getPerGradeableCurves(): array {
        $retArray = [];

        if (!is_null($this->RCJSON)) {
            $json_buckets = $this->RCJSON->getGradeables();

            foreach ($json_buckets as $json_bucket) {
                $retArray[$json_bucket->type] = [];

                foreach ($json_bucket->ids as $json_gradeable) {
                    if (property_exists($json_gradeable, 'curve')) {
                        $curve_data = $json_gradeable->curve;
                        $curve_data_pos = 0;
                        $selected_benchmarks = $this->RCJSON->getDisplayBenchmarks();
                        $benchmarks_with_input_fields = array_slice($this->RCJSON::allowed_display_benchmarks, 3);

                        $retArray[$json_bucket->type][$json_gradeable->id] = [];

                        foreach ($benchmarks_with_input_fields as $benchmark) {
                            if (in_array($benchmark, $selected_benchmarks, true)) {
                                $val = $curve_data[$curve_data_pos];
                                $curve_data_pos++;
                            }
                            else {
                                $val = '';
                            }

                            array_push($retArray[$json_bucket->type][$json_gradeable->id], $val);
                        }
                    }
                }
            }
        }

        return $retArray;
    }

    /**
     * Get an array containing what percentage of the grade the bucket counts toward.  The key is the name of the
     * bucket and the value is the percentage which has been cast back to a whole number integer.  This differs
     * from the customization.json in that in the json file the percentage is represented as a decimal between 0 and 1.
     * This value is represented as an integer between 0 and 100.
     *
     * ex.  key => value
     *     'test' => 50
     *     'lab' => 25
     *     'homework' => 25
     *
     * @return array<string,int>
     */
    public function getBucketPercentages(): array {
        $retArray = [];

        if (!is_null($this->RCJSON)) {
            $json_buckets = $this->RCJSON->getGradeables();

            $sum = 0;

            foreach ($json_buckets as $json_bucket) {
                // Get percentage, cast back to whole number integer
                $retArray[$json_bucket->type] = (int) ($json_bucket->percent * 100);

                // Keep track of the sum
                $sum += $retArray[$json_bucket->type];
            }

            // Save the sum of used percentages to special key in array
            $retArray['used_percentage'] = $sum;

            // Assign percentage values for buckets added automatically (i.e. when no other buckets are used)
            $json_bucket_types = [];
            foreach ($json_buckets as $json_bucket) {
                $json_bucket_types[] = $json_bucket->type;
            }
            $automatic_buckets = array_diff($this->used_buckets, $json_bucket_types);
            foreach ($automatic_buckets as $automatic_bucket) {
                $retArray[$automatic_bucket] = 0;
            }
        }

        return $retArray;
    }

    /**
     * @return array<string,int>
     */
    public function getBucketCounts(): array {
        return $this->bucket_counts;
    }

    /**
     * @return array<string,int>
     */
    public function getBucketRemoveLowest(): array {
        return $this->bucket_remove_lowest;
    }

    /**
     * @return array<string,array<mixed>>
     */
    public function getCustomizationData(): array {
        return $this->customization_data;
    }

    /**
     * @return string[]
     */
    public function getAvailableBuckets(): array {
        return $this->available_buckets;
    }

    /**
     * @return string[]
     */
    public function getUsedBuckets(): array {
        return $this->used_buckets;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array {
        return !is_null($this->RCJSON) ? $this->RCJSON->getMessages() : [];
    }


    /**
     * Get display benchmarks
     *
     * Get a multidimensional array that contains not only a list of usable display benchmarks but also which ones
     * are in use (in the customization.json)
     *
     * @return array<int,array<string,bool|string>> multidimensional array of display benchmark data
     */
    public function getDisplayBenchmarks(): array {
        $allowedBenchmarks = RainbowCustomizationJSON::allowed_display_benchmarks;
        // null safe operator
        $usedBenchmarks = $this->RCJSON?->getDisplayBenchmarks() ?? [];
        $benchmarksData = [];
        foreach ($allowedBenchmarks as $benchmark) {
            $benchmarkUsed = in_array($benchmark, $usedBenchmarks, true);
            $benchmarksData[] = ['id' => $benchmark, 'isUsed' => $benchmarkUsed];
        }
        return $benchmarksData;
    }

    /**
     * Get benchmark percentages
     *
     * @return object An object which maps benchmarks to the percentage (as a decimal) that is needed to obtain that
     *                letter grade
     */
    public function getBenchmarkPercent(): object {
        if (!is_null($this->RCJSON)) {
            $percent_obj = $this->RCJSON->getBenchmarkPercent();

            // If the RCJSON was found and it contains the benchmark percent fields then return it
            if ($percent_obj !== (object) []) {
                return $percent_obj;
            }
        }

        // Otherwise return a default benchmark percent object
        return (object) [
                'lowest_a-' => 0.9,
                'lowest_b-' => 0.8,
                'lowest_c-' => 0.7,
                'lowest_d' => 0.6,
            ];
    }

    /**
     * Get final grade cutoffs
     *
     * @return object An object which maps final grade cutoffs to the percentage (as a decimal) that is needed to
     *                obtain that letter grade
     */
    public function getFinalCutoff(): object {
        if (!is_null($this->RCJSON)) {
            $percent_obj = $this->RCJSON->getFinalCutoff();

            // If the RCJSON was found and it contains the final grade cutoff percent fields then return it
            if ($percent_obj !== (object) []) {
                return $percent_obj;
            }
        }

        // Otherwise return a default final cutoff percent object
        return (object) [
                'A' => 93.0,
                'A-' => 90.0,
                'B+' => 87.0,
                'B' => 83.0,
                'B-' => 80.0,
                'C+' => 77.0,
                'C' => 73.0,
                'C-' => 70.0,
                'D+' => 67.0,
                'D' => 60.0,
            ];
    }

    /**
     * Get display options
     *
     * Get a multidimensional array that contains not only a list of usable display options but also which ones
     * are in use (in the customization.json)
     *
     * @return array<int,array<string,bool|string>> multidimensional array of display option data
     */
    public function getDisplay(): array {
        $allowed_display_options = RainbowCustomizationJSON::allowed_display;
        $display_options = [];

        $used_display_options = $this->RCJSON ? $this->RCJSON->getDisplay() : [];

        foreach ($allowed_display_options as $option_id) {
            $display_options[] = [
                'id' => $option_id,
                'isUsed' => in_array($option_id, $used_display_options, true)
            ];
        }

        return $display_options;
    }

    /**
     * Get display description
     * @return array<string>  array of display description
     */
    public function getDisplayDescription(): array {
        return RainbowCustomizationJSON::allowed_display_description;
    }

    /**
     * Get section ids and labels
     *
     * If no customization.json file exists then this function will generate defaults
     * by examining what sections are registered in the database.  If a file does exist then sections and labels will
     * be read out of that.  If it turns out that new sections have been registered in the database that
     * dont yet exist in the file, those new sections will be added with default values.
     *
     * @return object The object mapping section ids to labels
     */
    public function getSectionsAndLabels(): object {
        // Get sections from db
        $db_sections = $this->core->getQueries()->getRegistrationSections();

        $sections = [];

        // Configure sections
        foreach ($db_sections as $section) {
            $key = $section['sections_registration_id'];

            $sections[$key] = $key;
        }

        // If RCJSON is not null then load it
        if (!is_null($this->RCJSON)) {
            // Get sections from the file
            $sectionsFromFile = (array) $this->RCJSON->getSection();

            // gets the number of sections in the database
            $sectionsCount = count($sections);

            // creates a copy of the database sections to compare against the file sections
            $database_copy = [];
            for ($i = 0; $i < $sectionsCount; $i++) {
                $database_copy[$db_sections[$i]['sections_registration_id']] = (string) $db_sections[$i]['sections_registration_id'];
            }

            // checks whether or not sections have been added and updates the file sections
            $result = array_diff_key($database_copy, $sectionsFromFile);
            if (count($result) > 0) {
                foreach ($result as $x => $val) {
                    $sectionsFromFile[$x] = $val;
                }
            }

            // checks whether or not sections have been removed and updates the file sections
            $result = array_diff_key($sectionsFromFile, $database_copy);
            if (count($result) > 0) {
                foreach ($result as $x => $val) {
                    unset($sectionsFromFile[$x]);
                }
            }

            // Collect sections out of the file
            return (object) $sectionsFromFile;
        }
        else {
            // RCJSON was null so return database sections as default
            // Collect sections out of the database
            return (object) $sections;
        }
    }

    /**
     * Get omit sections from stats from json file if there is any
     *
     * @return string[]
     */
    public function getOmittedSections(): array {
        return $this->RCJSON?->getOmittedSections() ?? [];
    }

    /**
     * Get plagiarism from json file if there is any
     *
     * @return array<object>  array of plagiarism JSON object
     */
    public function getPlagiarism(): array {
        return $this->RCJSON?->getPlagiarism() ?? [];
    }

    /**
     * Get manual grades from json file if there are any
     *
     * @return array<object>  array of manual grades JSON object
     */
    public function getManualGrades(): array {
        return $this->RCJSON?->getManualGrades() ?? [];
    }

    /**
     * Get performance warnings from json file if there are any
     *
     * @return array<object>  array of performance warnings JSON object
     */
    public function getPerformanceWarnings(): array {
        return $this->RCJSON?->getPerformanceWarnings() ?? [];
    }

    /**
     * This function handles processing the incoming post data
     *
     * @param string $form The JSON string to process
     */
    public function processForm($form): void {

        // Get a new customization file
        $this->RCJSON = new RainbowCustomizationJSON($this->core);
        $form_json = json_decode($form);

        if (isset($form_json->display_benchmark)) {
            foreach ($form_json->display_benchmark as $benchmark) {
                $this->RCJSON->addDisplayBenchmarks($benchmark);
            }
        }

        if (isset($form_json->benchmark_percent)) {
            foreach ($form_json->benchmark_percent as $key => $value) {
                $this->RCJSON->addBenchmarkPercent((string) $key, $value);
            }
        }

        if (isset($form_json->final_cutoff)) {
            foreach ($form_json->final_cutoff as $key => $value) {
                $this->RCJSON->addFinalCutoff((string) $key, $value);
            }
        }

        if (isset($form_json->section)) {
            foreach ($form_json->section as $key => $value) {
                $this->RCJSON->addSection((string) $key, $value);
            }
        }

        if (isset($form_json->omit_section_from_stats)) {
            foreach ($form_json->omit_section_from_stats as $omit_section) {
                $this->RCJSON->addOmittedSection($omit_section);
            }
        }

        if (isset($form_json->gradeables)) {
            foreach ($form_json->gradeables as $gradeable) {
                $this->RCJSON->addGradeable($gradeable);
            }
        }

        if (isset($form_json->messages)) {
            foreach ($form_json->messages as $message) {
                $this->RCJSON->addMessage($message);
            }
        }

        if (isset($form_json->plagiarism)) {
            foreach ($form_json->plagiarism as $plagiarism_single) {
                $this->RCJSON->addPlagiarismEntry($plagiarism_single);
            }
        }

        if (isset($form_json->manual_grade)) {
            foreach ($form_json->manual_grade as $manual_grade) {
                $this->RCJSON->addManualGradeEntry($manual_grade);
            }
        }

        if (isset($form_json->warning)) {
            foreach ($form_json->warning as $warning) {
                $this->RCJSON->addPerformanceWarningEntry($warning);
            }
        }

        if (isset($form_json->display)) {
            foreach ($form_json->display as $display_option) {
                $this->RCJSON->addDisplay($display_option);
            }
        }

        // Write to customization file
        $this->RCJSON->saveToJsonFile();
    }

    public function error(): string {
        return $this->has_error;
    }

    /**
     * @return array<string>
     */
    public function getErrorMessages(): array {
        return $this->error_messages;
    }

    public function doesManualCustomizationExist(): bool {
        // using RCJSON will have issue because constructor will call loadFromJsonFile
        // which will return null if gui_customization is not found.
        // return $this->RCJSON->doesManualCustomizationExist();
        return file_exists(FileUtils::joinPaths(
            $this->core->getConfig()->getCoursePath(),
            'rainbow_grades',
            'manual_customization.json'
        ));
    }

    /**
     * Check if the manual customization is being used
     *
     * @return bool
     */
    public function usesManualCustomization(): bool {
        if (!$this->doesManualCustomizationExist()) {
            return false;
        }

        $customization_dest = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'rainbow_grades', 'customization.json');
        $manual_customization_dest = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'rainbow_grades', 'manual_customization.json');

        $customization_json = json_encode(json_decode(file_get_contents($customization_dest), true), JSON_PRETTY_PRINT);
        $manual_customization_json = json_encode(json_decode(file_get_contents($manual_customization_dest), true), JSON_PRETTY_PRINT);

        // Manual or GUI JSON contents are copied to the main customization.json file for the build processes
        return $customization_json === $manual_customization_json;
    }
}
