<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\GradeableType;

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
     * @var DateTime
     *
    */
    private $now;
    
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
                        $id = str_replace("form_", "", basename($entry, ".json"));
                        $this->gradeables[$id] = new GradeableFile($this->core, $id);
                    }
                }
            }
            uasort($this->gradeables, function(Gradeable $a, Gradeable $b) {
                return $a->getId() < $b->getId();
            });
        }
        else {
            $this->gradeables = $this->core->getQueries()->getAllGradeables($this->core->getUser()->getId());
        }
        $this->now = new \DateTime("now", new \DateTimeZone($this->core->getConfig()->getTimezone()));
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
    
    /**
     * @param GradeableType|null $type
     *
     * @return int
     */
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
    
    public function getSubmittableElectronicGradeables() {
        /** @var Gradeable[] $list */
        $list = array();
    
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE &&

	        // ORIGINAL
                //($gradeable->getOpenDate() < $this->now || $this->core->getUser()->accessAdmin())) {
		
		// TEMPORARY - ALLOW LIMITED & FULL ACCESS GRADERS TO PRACTICE ALL FUTURE HOMEWORKS
                ($gradeable->getOpenDate() < $this->now || $this->core->getUser()->accessGrading())) {

                $list[$gradeable->getId()] = $gradeable;
            }
        }
        uasort($list, function(Gradeable $a, Gradeable $b) {
            return $a->getDueDate() < $b->getDueDate();
        });
        return $list;
    }
    
    public function getOpenElectronicGradeables() {
        /** @var Gradeable[] $list */
        $list = array();
        
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
                if ($gradeable->getOpenDate() < $this->now && $gradeable->getDueDate() > $this->now) {
                    $list[$gradeable->getId()] = $gradeable;
                }
            }
        }
        uasort($list, function(Gradeable $a, Gradeable $b) {
            return $a->getDueDate() < $b->getDueDate();
        });
        return $list;
    }
    
    public function getClosedElectronicGradeables() {
        $list = array();
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getType() == GradeableType::ELECTRONIC_FILE) {
                if ($gradeable->getDueDate() < $this->now && $gradeable->getGradeStartDate() > $this->now) {
                    $list[$gradeable->getId()] = $gradeable;
                }
            }
        }
        uasort($list, function(Gradeable $a, Gradeable $b) {
            return $a->getDueDate() < $b->getDueDate();
        });
        return $list;
    }
    
    public function getGradingGradeables() {
        $list = array();
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getGradeStartDate() < $this->now
                && $gradeable->getGradeReleasedDate() > $this->now) {
                $list[$gradeable->getId()] = $gradeable;
            }
        }
        uasort($list, function(Gradeable $a, Gradeable $b) {
            return $a->getGradeStartDate() < $b->getGradeStartDate();
        });
        return $list;
    }
    
    public function getGradedGradeables() {
        $list = array();
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getGradeReleasedDate() < $this->now) {
                $list[$gradeable->getId()] = $gradeable;
            }
        }
        uasort($list, function(Gradeable $a, Gradeable $b) {
            return $a->getGradeStartDate() < $b->getGradeStartDate();
        });
        return $list;
    }
    
    public function getFutureGradeables() {
        $list = array();
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getType()==GradeableType::ELECTRONIC_FILE) {
                if ($gradeable->getOpenDate() > $this->now) {
                    $list[$gradeable->getId()] = $gradeable;
                }
            }
            elseif($gradeable->getGradeStartDate() > $this->now){
                $list[$gradeable->getId()] = $gradeable;
            }
        }
        // only electronic gradeables have due_dates so future items must be sorted by the date grading begins
        uasort($list, function(Gradeable $a, Gradeable $b) {
            return $a->getGradeStartDate() < $b->getGradeStartDate();
        });
        return $list;
    }
    
}