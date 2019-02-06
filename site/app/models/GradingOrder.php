<?php

namespace app\models;

use app\libraries\Core;
use app\models\gradeable\Submitter;
use app\models\gradeable\Gradeable;

class GradingOrder extends AbstractModel {

    /**
     * @var Submitter[][] $section_submitters
     */
    protected $section_submitters;

    /**
     * @var GradingSection[] $sections
     */
    protected $sections;

    /**
     * GradingOrder constructor.
     * @param Core $core
     * @param Gradeable $gradeable
     * @param User $user
     */
    public function __construct(Core $core, Gradeable $gradeable, User $user) {
        parent::__construct($core);

        //Get that user's grading sections

        $this->section_submitters = [];
        $this->sections = $gradeable->getGradingSectionsForUser($user);
        foreach ($this->sections as $section) {
            $this->section_submitters[] = $section->getSubmitters();
        }

        $this->sort();
    }

    public function sort() {
        foreach ($this->section_submitters as &$section) {
            usort($section, function(Submitter $a, Submitter $b) {
                return ($a->getId() < $b->getId()) ? -1 : 1;
            });
        }
        unset($section);
    }

    /**
     * Given the current submitter, get the previous submitter to grade
     * @param Submitter $submitter Current grading submitter
     * @return Submitter Previous submitter to grade
     */
    public function getPrevSubmitter(Submitter $submitter) {
        $index = $this->getSubmitterIndex($submitter);
        if ($index === false) {
            return null;
        }
        $sub = $this->getSubmitterByIndex($index - 1);
        if ($sub === false) {
            return null;
        }
        return $sub;
    }

    /**
     * Given the current submitter, get the next submitter to grade
     * @param Submitter $submitter Current grading submitter
     * @return Submitter Next submitter to grade
     */
    public function getNextSubmitter(Submitter $submitter) {
        $index = $this->getSubmitterIndex($submitter);
        if ($index === false) {
            return null;
        }
        $sub = $this->getSubmitterByIndex($index + 1);
        if ($sub === false) {
            return null;
        }
        return $sub;
    }

    /**
     * Get the index of a Submitter in the order
     * @param Submitter $submitter Submitter to find
     * @return int|bool Index or false if not found
     */
    protected function getSubmitterIndex(Submitter $submitter) {
        $count = 0;

        //Iterate through all sections and their submitters to find this one
        for ($i = 0; $i < count($this->section_submitters); $i ++) {
            $section = $this->section_submitters[$i];

            for ($j = 0; $j < count($section); $j ++) {
                $testSub = $section[$j];

                //Found them
                if ($testSub->getId() === $submitter->getId()) {
                    return $count;
                }

                $count ++;
            }
        }
        return false;
    }

    /**
     * Given a Submitter's index, find them
     * @param int $index Index of Submitter in grading order
     * @return Submitter|bool Submitter if found, false otherwise
     */
    protected function getSubmitterByIndex(int $index) {
        //Sanity
        if ($index < 0) {
            return false;
        }

        //Iterate through all sections and their submitters to find this one
        for ($i = 0; $i < count($this->section_submitters); $i ++) {
            $section = $this->section_submitters[$i];

            //Easy skip
            if (count($section) <= $index) {
                $index -= count($section);
                continue;
            }

            //Found them
            return $section[$index];
        }
        return false;
    }

    /**
     * Check if we have the given submitter in one of our grading sections
     * @param Submitter $submitter Id of submitter to check
     * @return boolean True if we have them
     */
    public function containsSubmitter(Submitter $submitter) {
        foreach ($this->section_submitters as $section) {
            foreach ($section as $section_sub) {
                if ($submitter->getId() === $section_sub->getId()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get a list of all submitters, split into sections
     * @return Submitter[][]
     */
    public function getSectionSubmitters() {
        return $this->section_submitters;
    }
}

