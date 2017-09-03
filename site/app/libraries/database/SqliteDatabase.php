<?php

namespace app\libraries\database;


use app\exceptions\NotImplementedException;

class SqliteDatabase extends AbstractDatabase {
    protected $path;
    protected $memory = true;

    public function __construct($connection_params=array()) {
        parent::__construct($connection_params);
        if (isset($connection_params['path'])) {
            $this->path = $connection_params['path'];
        }
        else {
            $this->memory = isset($connection_params['memory']) && $connection_params['memory'] === true;
        }
    }

    public function getDSN() {
        $param = '';
        if (isset($this->path)) {
            $param = $this->path;
        }
        elseif ($this->memory === true) {
            $param = ':memory:';
        }
        return "sqlite:{$param}";
    }

    public function fromDatabaseToPHPArray($text, $parse_bools = false, $start=0, &$end=null) {
        throw new NotImplementedException();
    }

    public function fromPHPToDatabaseArray($array) {
        throw new NotImplementedException();
    }

}
