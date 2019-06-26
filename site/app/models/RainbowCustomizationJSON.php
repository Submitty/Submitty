<?php


namespace app\models;

use app\exceptions\BadArgumentException;
use app\exceptions\FileReadException;
use app\exceptions\FileWriteException;
use app\exceptions\MalformedDataException;
use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\FileUtils;

/**
 * Class RainbowCustomizationJSON
 * @package app\models
 *
 * This class is a PHP representation of a customization.json file as used in RainbowGrades and provides means
 * to update its fields.
 */
class RainbowCustomizationJSON extends AbstractModel
{
    protected $core;

    private $section;
    private $display_benchmark = [];
    private $messages = [];
    private $display = [];
    private $benchmark_percent;
    private $gradeables = [];

    const allowed_display = ['instructor_notes', 'grade_summary', 'grade_details', 'iclicker', 'final_grade',
        'exam_seating', 'display_rank_to_individual', 'display_benchmark', 'benchmark_percent', 'section', 'messages',
        'final_cutoff', 'manual_grade', 'warning'];

    const allowed_display_benchmarks = ["average", "stddev", "perfect", "lowest_a-", "lowest_b-", "lowest_c-",
        "lowest_d"
    ];

    public function __construct(Core $main_core) {
        $this->core = $main_core;

        $this->section = (object)[];
        $this->benchmark_percent = (object)[];
    }

    public function getGradeables()
    {
        return $this->gradeables;
    }

    /**
     * Gets an array of display benchmarks
     *
     * @return array The display benchmarks
     */
    public function getDisplayBenchmarks()
    {
        return $this->display_benchmark;
    }

    /**
     * Adds a benchmark to the display_benchmarks
     * If it already exists in the array no changes are made
     *
     * @param string $benchmark The benchmark to add
     * @throws BadArgumentException The passed in argument is not allowed
     */
    public function addDisplayBenchmarks(string $benchmark)
    {
        if(!in_array($benchmark, self::allowed_display_benchmarks))
        {
            throw new BadArgumentException('Passed in benchmark not found in the list of allowed benchmarks');
        }

        if(!in_array($benchmark, $this->display_benchmark))
        {
            array_push($this->display_benchmark, $benchmark);
        }
    }

    /**
     * Removes a benchmark from display_benchmarks
     * If the benchmark was not found, then no changes are made
     *
     * @param string $benchmark
     * @throws BadArgumentException The passed in argument is not allowed
     */
    public function removeDisplayBenchmark(string $benchmark)
    {
        if(!in_array($benchmark, self::allowed_display_benchmarks))
        {
            throw new BadArgumentException('Passed in benchmark not found in the list of allowed benchmarks');
        }

        $key = array_search($benchmark, $this->display_benchmark);

        if($key != False)
        {
            unset($this->display_benchmark[$key]);
        }
    }

    /**
     * Loads the data from the courses rainbow grades customization.json into this php object
     *
     * @throws FileReadException Failure to read the contents of the file
     * @throws MalformedDataException Failure to decode the contents of the JSON string
     */
    public function loadFromJsonFile()
    {
        // Get contents of file and decode
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'customization_no_comments.json');
        $file_contents = file_get_contents($course_path);

        // Validate file read
        if($file_contents === False)
        {
            throw new FileReadException('An error occured trying to read the contents of customization file.');
        }

        $json = json_decode($file_contents);

        // Validate decode
        if($json === NULL)
        {
            throw new MalformedDataException('Unable to decode JSON string');
        }

        if(isset($json->display_benchmark))
        {
            $this->display_benchmark = $json->display_benchmark;
        }

        if(isset($json->section))
        {
            $this->section = $json->section;
        }

        if(isset($json->messages))
        {
            $this->messages = $json->messages;
        }

        if(isset($json->display))
        {
            $this->display = $json->display;
        }

        if(isset($json->benchmark_percent))
        {
            $this->benchmark_percent = $json->benchmark_percent;
        }

        if(isset($json->gradeables))
        {
            $this->gradeables = $json->gradeables;
        }
    }

    public function loadFromRainbowCustomization(RainbowCustomization $customization)
    {
        throw new NotImplementedException('');
    }

    public function saveToJsonFile()
    {
        // Get path of where to save file
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_path = FileUtils::joinPaths($course_path, 'rainbow_grades', 'customization.json');

        $json = (object)[];

        if(empty($this->display))
        {
            $json->display[] = 'grade_summary';
            $json->display[] = 'grade_details';
        }
        else
        {
            $json->display = $this->display;
        }

        if(!empty($this->display_benchmark))
        {
            $json->display_benchmark = $this->display_benchmark;
        }

        $json = json_encode($json, JSON_PRETTY_PRINT);

        file_put_contents($course_path, $json);
    }


}