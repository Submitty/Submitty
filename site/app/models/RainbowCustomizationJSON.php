<?php


namespace app\models;

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
 * This class is a PHP representation of a customization.json file as used in RainbowGrades
 */
class RainbowCustomizationJSON extends AbstractModel
{
    protected $core;
    private $loaded = False;            // Stores if this object has been loaded with data yet

    private $display_benchmark;
    private $messages;
    private $display;
    private $benchmark_percent;
    private $gradeables;

    public function __construct(Core $main_core) {
        $this->core = $main_core;
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

        $this->loaded = True;
    }

    public function loadFromRainbowCustomization(RainbowCustomization $customization)
    {
        throw new NotImplementedException('');
    }

    public function saveToJsonFile()
    {
        if($this->loaded === False)
        {
            throw new FileWriteException('Unable to write data to customization.json because this object ' .
                'contains no data.');
        }
    }


}