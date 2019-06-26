<?php
namespace app\models;

use app\exceptions\ValidationException;
use app\libraries\Core;
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
    private $customization_data;
    private $has_error;
    private $error_messages;
    private $used_buckets;
    private $available_buckets;

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

    const display_benchmarks = [
        'average',
        'stddev',
        'perfect',
        'lowest_a-',
        'lowest_b-',
        'lowest_c-',
        'lowest_d'
    ];


    public function __construct(Core $main_core) {
        $this->core = $main_core;
        $this->has_error = "false";
        $this->error_messages = [];
    }

    public function buildCustomization(){

        $json = new RainbowCustomizationJSON($this->core);
        $json->loadFromJsonFile();

        //This function should examine the DB(?) / a file(?) and if customization settings already exist, use them. Otherwise, populate with defaults.
        $this->customization_data = [];

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
        $this->used_buckets = [];

        /* TODO: Read in used_buckets according to the customization.json if it exists and remove those from the
         * available_buckets array.
         */
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

    //TODO: Implement the real version of this function
    public function getCustomizationJSON(){
        //Logic to trim down the customization data to just what's shown
        $json_data = ["Yes-POST"];
        return json_encode($json_data);
    }

    public function processForm(){
        $this->has_error = "true";
        foreach($_POST as $field => $value){
            $this->error_messages[] = "$field: $value";
        }
        throw new ValidationException('Debug Rainbow Grades error', $this->error_messages);
    }

    public function error(){
        return $this->has_error;
    }

    public function getErrorMessages(){
        return $this->error_messages;
    }
}