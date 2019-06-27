<?php
namespace app\models;

use app\exceptions\ValidationException;
use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\libraries\DatabaseUtils;
use app\libraries\FileUtils;
use app\libraries\GradeableType;

/**
 * Class RainbowCustomization
 * @package app\models
 *
 * This class is a RainbowGrades Customization.  It may contain the data found in customization.json but it also
 * contains additional data that is used by the web user interface to aid in generation/customization.
 */
class RainbowCustomization extends AbstractModel{
    /**/
    protected $core;
    private $customization_data = [];
    private $has_error;
    private $error_messages;
    private $used_buckets = [];
    private $available_buckets;
    private $RCJSON;                           // This is the customization.json php object, or null if it wasn't found
    private $sections;                         // Contains section ids mapped to labels

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
        $this->core = $main_core;
        $this->has_error = "false";
        $this->error_messages = [];

        $this->sections = (object)[];

        // Attempt to load json from customization file
        // If it fails then set to null, will be used to load defaults later
        $this->RCJSON = new RainbowCustomizationJSON($this->core);

        try
        {
            $this->RCJSON->loadFromJsonFile();
        }
        catch(\Exception $e)
        {
            $this->RCJSON = null;
        }
    }

    public function buildCustomization(){

        //This function should examine the DB(?) / a file(?) and if customization settings already exist, use them. Otherwise, populate with defaults.
        foreach (self::syllabus_buckets as $bucket){
            $this->customization_data[$bucket] = [];
        }

        $gradeables = $this->core->getQueries()->getGradeableConfigs(null);
        foreach ($gradeables as $gradeable){
            //XXX: 'none (for practice only)' we want to truncate to just 'none', otherwise use full bucket name
            $bucket = $gradeable->getSyllabusBucket() == "none (for practice only)" ? "none" : $gradeable->getSyllabusBucket();
            /*if(!isset($this->customization_data[$bucket])){
                $this->customization_data[$bucket] = [];
            }*/
            /*XXX: Right now we aren't yet worried about pulling in from existing customization.json but if we do, then what happens in the event of a conflict?
             * I'm tempted to say for version 1.0, too bad, we override with the version from the DB (since that's more up to date), if the gradeable exists
             * In a later version we may want to highlight it in red or something, and ask the user which number to use? I'm not sure if there's a use case for using
             * the version in the customization.json, but the warning might be nice. Might even be an error state where action is required, just so the user isn't
             * confused when the grade distribution shifts around.
             */

            $max_score = $gradeable->getTAPoints();
            //If the gradeable has autograding points, load the config and add the non-extra-credit autograder total
            if ($gradeable->hasAutogradingConfig()){
                $last_index = count($this->customization_data[$bucket])-1;
                $max_score += $gradeable->getAutogradingConfig()->getTotalNonExtraCredit();
            }

            $this->customization_data[$bucket][] = [
                "id" => $gradeable->getId(),
                "title" => $gradeable->getTitle(),
                "max_score" => $max_score
            ];
        }

        //XXX: Assuming that the contents of these buckets will be lowercase
        $this->available_buckets = array_diff(self::syllabus_buckets,$this->used_buckets);
    }

    public function getCustomizationData(){
        return $this->customization_data;
    }

    public function getAvailableBuckets(){
        return $this->available_buckets;
    }

    public function getUsedBuckets(){
        return $this->used_buckets;
    }

    public function getDisplayBenchmarks()
    {
        $displayBenchmarks = RainbowCustomizationJSON::allowed_display_benchmarks;
        $retArray = [];

        !is_null($this->RCJSON) ?
            $usedDisplayBenchmarks = $this->RCJSON->getDisplayBenchmarks() :
            $usedDisplayBenchmarks = [];

        foreach ($displayBenchmarks as $displayBenchmark)
        {
            in_array($displayBenchmark, $usedDisplayBenchmarks) ? $isUsed = True : $isUsed = False;

            // Add benchmark to return array
            $retArray[] = ['id' => $displayBenchmark, 'isUsed' => $isUsed];
        }

        return $retArray;
    }

    public function getSectionsAndLabels()
    {
        // Get sections from db
        $db = new DatabaseQueries($this->core);
        $db_sections = $db->getRegistrationSections();

        $sections = [];

        // Configure sections
        foreach($db_sections as $section)
        {
            $key = $section['sections_registration_id'];

            $sections[$key] = $key;
        }

        // If RCJSON is not null then load it
        if(!is_null($this->RCJSON))
        {
            // Get sections from the file
            $sectionsFromFile = (array)$this->RCJSON->getSection();

            // If sections from database is larger than sections from file then there must be a new section in
            // in the database, add new fields into sections from file with defaults
            $sectionsFromFileCount = count($sectionsFromFile);
            $sectionsCount = count($sections);

            if($sectionsFromFileCount != $sectionsCount)
            {
                for($i = $sectionsFromFileCount + 1; $i <= $sectionsCount; $i++)
                {
                    $sectionsFromFile[$i] = (string)$i;
                }
            }

            return (object)$sectionsFromFile;
        }
        // RCJSON was null so return database sections as default
        else
        {
            // Collect sections out of the database
            return (object)$sections;
        }
    }

    // This function handles processing the incoming post data
    public function processForm(){

        // Get a new customization file
        $json = new RainbowCustomizationJSON($this->core);

        $form_json = (object)$_POST['customization'];

        if(isset($form_json->display_benchmark))
        {
            foreach($form_json->display_benchmark as $benchmark)
            {
                $json->addDisplayBenchmarks($benchmark);
            }
        }

        if(isset($form_json->section))
        {
            foreach($form_json->section as $key => $value)
            {
                $json->addSection((string)$key, $value);
            }
        }

        // Write to customization file
        $json->saveToJsonFile();

        print("");

//        $this->has_error = "true";
//        foreach($_POST as $field => $value){
//            $this->error_messages[] = "$field: $value";
//        }
//        throw new ValidationException('Debug Rainbow Grades error', $this->error_messages);
    }

    public function error(){
        return $this->has_error;
    }

    public function getErrorMessages(){
        return $this->error_messages;
    }
}