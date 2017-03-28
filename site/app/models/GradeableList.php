<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\GradeableType;

class GradeableList extends AbstractModel {
    /**
     * @var Core
     */
    private $core;
    
    /**
     * @var Gradeable[]
     */
    private $gradeables = array();

    /*
     * All elements of $this->gradeables should fall into one of the following six lists. There should
     * be no overlap between them.
     */
    /** @var Gradeable[] */
    private $future_gradeables = array();
    /** @var Gradeable[] */
    private $beta_gradeables = array();
    /** @var Gradeable[] */
    private $open_gradeables = array();
    /** @var Gradeable[] */
    private $closed_gradeables = array();
    /** @var Gradeable[] */
    private $grading_gradeables = array();
    /** @var Gradeable[] */
    private $graded_gradeables = array();

    
    /**
     * @var \DateTime
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
            foreach ($this->core->getQueries()->getAllGradeables($this->core->getUser()->getId()) as $gradeable) {
                $this->gradeables[$gradeable->getId()] = $gradeable;
            }
        }

        $now = new \DateTime("now", new \DateTimeZone($this->core->getConfig()->getTimezone()));

        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getGradeReleasedDate() <= $now) {
                $this->graded_gradeables[$gradeable->getId()] = $gradeable;
            }
            else if ((($gradeable->getType() === GradeableType::ELECTRONIC_FILE && $gradeable->useTAGrading()) ||
                    $gradeable->getType() !== GradeableType::ELECTRONIC_FILE) &&
                    $gradeable->getGradeStartDate() <= $now) {
                $this->grading_gradeables[$gradeable->getId()] = $gradeable;
            }
            else if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE &&
                $gradeable->getOpenDate() <= $now && $gradeable->getDueDate() <= $now) {
                $this->closed_gradeables[$gradeable->getId()] = $gradeable;
            }
            else if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE &&
                $gradeable->getOpenDate() <= $now && $gradeable->getTAViewDate() <= $now) {
                $this->open_gradeables[$gradeable->getId()] = $gradeable;
            }
            else if ($this->core->getUser()->accessGrading() && $gradeable->getTAViewDate() <= $now) {
                $this->beta_gradeables[$gradeable->getId()] = $gradeable;
            }
            else if ($this->core->getUser()->accessAdmin()) {
                $this->future_gradeables[$gradeable->getId()] = $gradeable;
            }
        }
        $sort_array = array(
            'future_gradeables' => 'getGradeStartDate',
            'beta_gradeables' => 'getGradeStartDate',
            'open_gradeables' => 'getDueDate',
            'closed_gradeables' => 'getDueDate',
            'grading_gradeables' => 'getGradeStartDate',
            'graded_gradeables' => 'getGradeReleasedDate'
        );
        foreach ($sort_array as $list => $function) {
            uasort($this->$list, function(Gradeable $a, Gradeable $b) use ($function) {
                if ($a->$function() == $b->$function()) {
                    $function = "getId";
                }
                if ($a->$function() < $b->$function()) {
                    return -1;
                }
                else if ($a->$function() == $b->$function()) {
                    return 0;
                }
                else {
                    return 1;
                }
            });
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

    public function getFutureGradeables() {
        return $this->future_gradeables;
    }

    public function getBetaGradeables() {
        return $this->beta_gradeables;
    }

    /**
     * @return Gradeable[]
     */
    public function getSubmittableElectronicGradeables() {
        $this->now;
        $return = array();
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
                continue;
            }
            $return[$gradeable->getId()] = $gradeable;
            /* This seems to have a bug in it for normal students, need to investigate further
            if ($this->core->getUser()->accessAdmin() ||
                ($gradeable->getTAViewDate() <= $this->now && $this->core->getUser()->accessGrading()) ||
                $gradeable->getOpenDate() <= $this->now) {
                $return[$gradeable->getId()] = $gradeable;
            }
            */
        }
        return $return;
    }
    
    public function getOpenElectronicGradeables() {
        return $this->open_gradeables;
    }
    
    public function getClosedElectronicGradeables() {
        return $this->closed_gradeables;
    }
    
    public function getGradingGradeables() {
        return $this->grading_gradeables;
    }
    
    public function getGradedGradeables() {
        return $this->graded_gradeables;
    }
}