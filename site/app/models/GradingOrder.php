<?php

namespace app\models;

use app\libraries\Core;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\gradeable\Gradeable;

class GradingOrder extends AbstractModel {

    /**
     * @var Gradeable $gradeable
     */
    protected $gradeable;

    /**
     * @var User $user
     */
    protected $user;

    /**
     * @var boolean $all
     */
    protected $all;

    /**
     * @var Submitter[][] $section_submitters
     */
    protected $section_submitters;

    /**
     * @var bool[] $has_submission
     */
    protected $has_submission;

    /**
     * @var GradingSection[] $sections
     */
    protected $sections;

    /**
     * GradingOrder constructor.
     * @param Core $core
     * @param Gradeable $gradeable
     * @param User $user
     * @param boolean $all
     */
    public function __construct(Core $core, Gradeable $gradeable, User $user, $all = false) {
        parent::__construct($core);

        $this->gradeable = $gradeable;
        $this->user = $user;
        $this->all = $all;

        //Get that user's grading sections

        $this->section_submitters = [];
        if ($all) {
            $this->sections = $gradeable->getAllGradingSections();
        } else {
            $this->sections = $gradeable->getGradingSectionsForUser($user);
        }

        $user_ids = [];
        $team_ids = [];

        foreach ($this->sections as $section) {
            $submitters = $section->getSubmitters();
            $this->section_submitters[$section->getName()] = $submitters;

            //Collect all team/user ids
            if ($gradeable->isTeamAssignment()) {
                foreach ($submitters as $submitter) {
                    $team_ids[] = $submitter->getId();
                }
            } else {
                foreach ($submitters as $submitter) {
                    $user_ids[] = $submitter->getId();
                }
            }
        }

        //TODO: Make faster
        $section_key = $this->getSectionKey();
        $graded_gradeables = $this->core->getQueries()->getGradedGradeables([$gradeable], $user_ids, $team_ids, [$section_key, 'team_id', 'user_id']);

        $found = [];
        $ggs = [];
        foreach ($graded_gradeables as $graded_gradeable) {
            /* @var GradedGradeable $graded_gradeable */
            if ($graded_gradeable->getAutoGradedGradeable()->getActiveVersion() > 0) {
                $found[] = $graded_gradeable->getSubmitter()->getId();
            }
            $ggs[] = $graded_gradeable;
        }

        //Find which submitters have no submission
        $this->has_submission = [];
        foreach ($this->section_submitters as $section) {
            foreach ($section as $submitter) {
                $this->has_submission[$submitter->getId()] = in_array($submitter->getId(), $found, true);
            }
        }

        $this->sort();
    }

    public function sort() {
        foreach ($this->section_submitters as $name => &$section) {
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

        do {
            $index--;
            $sub = $this->getSubmitterByIndex($index);
            if ($sub === false) {
                return null;
            }
            //Repeat until we find one that exists
        } while (!$this->has_submission[$sub->getId()]);

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

        do {
            $index++;
            $sub = $this->getSubmitterByIndex($index);
            if ($sub === false) {
                return null;
            }
            //Repeat until we find one that exists
        } while (!$this->has_submission[$sub->getId()]);

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
        foreach ($this->section_submitters as $name => $section) {
            for ($i = 0; $i < count($section); $i ++) {
                $testSub = $section[$i];

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
        foreach ($this->section_submitters as $name => $section) {
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
        foreach ($this->section_submitters as $name => $section) {
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

    /**
     * Get a list of all graders for all sections
     * @return User[][]
     */
    public function getSectionGraders() {
        return array_map(function (GradingSection $section) {
            return $section->getGraders();
        }, $this->sections);
    }

    /**
     * Get a list of all section names
     * @return string[]
     */
    public function getSectionNames() {
        return array_map(function (GradingSection $section) {
            return $section->getName();
        }, $this->sections);
    }

    /**
     * Get name of column for section
     * @return string
     */
    public function getSectionKey() {
        return $this->gradeable->isGradeByRegistration() ? "registration_section" : "rotating_section";
    }
}

