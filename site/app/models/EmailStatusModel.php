<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class EmailStatusModel extends AbstractModel {
    /** @prop-read array */
    protected $emails = [];

    protected $subjects;
    public function __construct(Core $core, $details){
        parent::__construct($core);
        foreach ($details as $rows){
            if (array_key_exists($rows["subject"], $this->emails)){
                $this->emails[$rows["subject"]][] = $rows;
            }
            else {
                $this->emails[$rows["subject"]] = [$rows];
            }
        }
    }
    public function getData(){
        return $this->emails; 
    }
}