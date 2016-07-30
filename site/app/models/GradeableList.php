<?php

namespace app\models;

use app\libraries\Core;

class GradeableList {
    /**
     * @var Core
     */
    private $core;
    
    /**
     * @var Gradeable[]
     */
    private $gradeables = array();
    
    /**
     * GradeableList constructor.
     *
     * @param Core          $core
     * @param bool          $use_db  are we getting the gradeables from the JSON files or database
     */
    public function __construct(Core $core, $use_db = false) {
        $this->core = $core;
        if (!$use_db) {
            $form_path = $this->core->getConfig()->getCoursePath() . "/config/form/";
            if(is_dir($form_path)) {
                $handle = opendir($form_path);
                while (($entry = readdir($handle)) !== false) {
                    if(is_file($form_path . $entry)) {
                        $this->gradeables[] = new GradeableFile($this->core, str_replace("form_", "", basename($entry, ".json")));
                    }
                }
            }
            usort($this->gradeables, function(Gradeable $a, Gradeable $b) {
                return $a->getId() < $b->getId();
            });
        }
        else {
            $ids = $this->core->getQueries()->getAllGradeablesIds();
            foreach ($ids as $id) {
                $this->gradeables[] = new GradeableDb($this->core, $id);
            }
        }
    }
    
    /**
     * Fetch gradeable from the stored gradeables (assuming it exists). Will return
     * false if the gradeable does not exist.
     *
     * @param string            $id
     * @param GradeableType     $type
     * @return Gradeable|null
     */
    public function getGradeable($id, $type = null) {
        if (array_key_exists($id, $this->gradeables)) {
            $gradeable = $this->gradeables[$id];
            if ($type !== null && $gradeable->getType() === $type) {
                return $gradeable;
            }
            else if ($type === null) {
                return $gradeable;
            }
        }
        return null;
    }
    
    public function getGradeableCount($type = null) {
        $count = 0;
        if ($type !== null) {
            foreach ($this->gradeables as $gradeable) {
                if ($type === $gradeable->getType()) {
                    $count++;
                }
            }
        }
        else {
            $count = count($this->gradeables);
        }
        return $count;
    }
    
    /**
     * Returns the electronic gradeable that's due date is the furthest forward in time
     */
    public function getNewestElectronicGradeable() {
        $date = new \DateTime("1970-01-01 00:00:00", new \DateTimeZone($this->core->getConfig()->getTimezone()));
        $current = null;
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getDueDate() > $date) {
                $date = $gradeable->getDueDate();
                $current = $gradeable;
            }
        }
        return $current;
    }
    
    public function getGradeables($type = null) {
        if ($type === null) {
            return $this->gradeables;
        }
        else {
            $list = array();
            foreach ($this->gradeables as $gradeable) {
                if ($gradeable->getType() === $type) {
                    $list[] = $gradeable;
                }
            }
            return $list;
        }
    }
    
    public function getOpenElectronicGradeables($needs_config = false) {
        /** @var Gradeable[] $list */
        $list = array();
        $now = new \DateTime("now", new \DateTimeZone($this->core->getConfig()->getTimezone()));
        foreach ($this->gradeables as $gradeable) {
            
            if (!$needs_config || $gradeable->hasConfig() == true) {
                if ($this->core->getUser()->accessAdmin() || $gradeable->getOpenDate() < $now) {
                    $list[$gradeable->getId()] = $gradeable;
                }
            }
        }
        uasort($list, function(Gradeable $a, Gradeable $b) {
            return $a->getDueDate() < $b->getDueDate();
        });
        return $list;
    }
}