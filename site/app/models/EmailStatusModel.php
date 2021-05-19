<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class EmailStatusModel extends AbstractModel {
    /** @prop-read array */
    protected $data;

    public function __construct(Core $core, $details){
        parent::__construct($core);
        $this->data = $details;
    }
    public function getData(){
        return $this->data; 
    }
}