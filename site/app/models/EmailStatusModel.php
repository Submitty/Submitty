<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class EmailStatusModel extends AbstractModel {
    // A set of all unique subjects of emails
    /** @prop-read Set */
    protected $subjects = [];
    // A map of email subjects to the rows that are still pending to send in the database
    /** @prop-read array */
    protected $pending = [];
    // A map of email subjects to the rows that successfully sent in the database
    /** @prop-read array */
    protected $successes = [];
    // A map of email subjects to the rows that resulted in an error in the database
    /** @prop-read array */
    protected $errors = [];

    public function __construct(Core $core, $data){
        parent::__construct($core);
        foreach ($data as $row){
            $this->subjects[$row["subject"]] = 1;
            if (!array_key_exists($row["subject"], $this->subjects)){
                // initialize all the counter arrays
                $this->pending[$row["subject"]] = [];
                $this->successes[$row["subject"]] = [];
                $this->errors[$row["subject"]] = [];
            }
            if ($row["sent"] != null){
                $this->successes[$row["subject"]][] = $row;
            }
            else if($row["error"] !=  null){
                $this->errors[$row["subject"]][] = $row;
            } 
            else {
                $this->pending[$row["subject"]][] = $row;
            }
        }
    }
    public function getSubjects(){
        return $this->subjects;
    }
    public function getPending(){
        return $this->pending; 
    }
    public function getSuccesses(){
        return $this->successes;
    }
    public function getErrors(){
        return $this->errors;
    }
}