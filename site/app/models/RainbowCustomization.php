<?php
namespace app\models;

use app\libraries\Core;
use app\libraries\DatabaseUtils;
use app\libraries\FileUtils;
use app\libraries\GradeableType;

class RainbowCustomization extends AbstractModel{
    /**/
    protected $core;
    private $customization_data;
    private $has_error;
    private $error_messages;


    public function __construct(Core $main_core) {
        $this->core = $main_core;
        $has_error = "false";
        $error_messages = [];
    }

    public function buildCustomization(){
        //This function should examine the DB(?) / a file(?) and if customization settings already exist, use them. Otherwise, populate with defaults.
        $this->customization_data = [];

        //$gids = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $gradeables = $this->core->getQueries()->getAllGradeables();
        foreach ($gradeables as $gradeable){
            $bucket = $gradeable->getBucket();
            if(!isset($this->customization_data[$bucket])){
                $this->customization_data[$bucket] = [];
            }
            $this->customization_data[$bucket][] = array($gradeable->getId(),$gradeable->getName());
        }
    }

    public function getCustomizationData(){
        return $this->customization_data;
    }

    public function getCustomizationJSON(){
        //Logic to trim down the customization data to just what's shown
        $json_data = [];
        return json_encode($json_data);
    }

    public function processForm(){
        $this->has_error = "true";
        foreach($_POST as $field => $value){
            $this->error_messages[] = "$field: $value";
        }
    }

    public function error(){
        return $this->has_error;
    }

    public function getErrorMessages(){
        return $this->error_messages;
    }
}