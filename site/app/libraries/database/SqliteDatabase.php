<?php

namespace app\libraries\database;

use app\exceptions\NotImplementedException;

class SqliteDatabase extends AbstractDatabase {
    protected $path;
    protected $memory = true;

    public function __construct($connection_params = []) {
        parent::__construct($connection_params);
        if (isset($connection_params['path'])) {
            $this->path = $connection_params['path'];
            $this->memory = false;
        }
        else {
            $this->memory = isset($connection_params['memory']) && $connection_params['memory'] === true;
        }
    }

    public function getConnectionDetails(): array {
        $details = [
            'driver' => 'pdo_sqlite',
            'memory' => $this->memory
        ];
        if (isset($this->path)) {
            $details['path'] = $this->path;
        }
        return $details;
    }

    public function fromDatabaseToPHPArray($text, $parse_bools = false, $start = 0, &$end = null) {
        throw new NotImplementedException();
    }

    public function fromPHPToDatabaseArray($array) {
        throw new NotImplementedException();
    }
}
