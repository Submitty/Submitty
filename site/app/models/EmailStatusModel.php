<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class EmailStatusModel extends AbstractModel {
    /** @prop-read array */
    // A map of email subjects to the row in the database
    protected $emails = [];
    // A map of the email subjects to number of successfully sent emails
    protected $successes = [];
    // A map of the email subjects to the number of errors in emails sent
    protected $errors = [];

    public function __construct(Core $core, $details){
        parent::__construct($core);
        foreach ($details as $rows){
            if (!array_key_exists($rows["subject"], $this->emails)){
                // initialize all the counter arrays
                $this->emails[$rows["subject"]] = [];
                $this->successes[$rows["subject"]] = 0;
                $this->errors[$rows["subject"]] = 0;
            }
            $this->emails[$rows["subject"]][] = $rows;
            if ($rows["sent"] != null){
                $this->successes[$rows["subject"]] += 1;
            }
            else if($rows["error"] !=  null){
                $this->errors[$rows["subject"]] += 1;
            }
        }
    }
    public function getData(){
        return $this->emails; 
    }
    public function getSuccesses(){
        return $this->successes;
    }
    public function getErrors(){
        return $this->errors;
    }
}