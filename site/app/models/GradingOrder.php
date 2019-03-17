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
     * @var Submitter[][] $section_submitters Section name => Submitter[]
     */
    protected $section_submitters;

    /**
     * @var bool[] $has_submission Submitter id => bool
     */
    protected $has_submission;

    /**
     * @var GradingSection[] $sections
     */
    protected $sections;

    /**
     * @var string[] $all_user_ids
     */
    protected $all_user_ids;

    /**
     * @var string[] $all_team_ids
     */
    protected $all_team_ids;

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
        if ($all) {
            $this->sections = $gradeable->getAllGradingSections();
        } else {
            $this->sections = $gradeable->getGradingSectionsForUser($user);
        }

        $this->all_user_ids = [];
        $this->all_team_ids = [];

        //Collect all submitters by section
        $this->section_submitters = [];
        foreach ($this->sections as $section) {
            $submitters = $section->getSubmitters();
            $this->section_submitters[$section->getName()] = $submitters;

            //Collect all team/user ids
            if ($gradeable->isTeamAssignment()) {
                foreach ($submitters as $submitter) {
                    $this->all_team_ids[] = $submitter->getId();
                }
            } else {
                foreach ($submitters as $submitter) {
                    $this->all_user_ids[] = $submitter->getId();
                }
            }
        }

        //Find which submitters have submitted
        $versions = $this->core->getQueries()->getActiveVersions($gradeable, array_merge($this->all_user_ids, $this->all_team_ids));
        foreach ($versions as $id => $version) {
            /* @var GradedGradeable $graded_gradeable */
            $this->has_submission[$id] = $version > 0;
        }

        $this->sort();
    }

    /**
     * Sort grading order.
     */
    public function sort() {
        //TODO: More sort criteria
        foreach ($this->section_submitters as $name => &$section) {
            usort($section, function(Submitter $a, Submitter $b) {
                return ($a->getId() < $b->getId()) ? -1 : 1;
            });
        }
        unset($section);
    }

    /**
     * Given the current submitter, get the previous submitter to grade.
     * Will skip students that do not need to be graded (eg no submission)
     * @param Submitter $submitter Current grading submitter
     * @return Submitter Previous submitter to grade
     */
    public function getPrevSubmitter(Submitter $submitter) {
        return $this->getPrevSubmitterMatching($submitter, function(Submitter $sub) {
            return $this->getHasSubmission($sub);
        });
    }

    /**
     * Given the current submitter, get the next submitter to grade.
     * Will skip students that do not need to be graded (eg no submission)
     * @param Submitter $submitter Current grading submitter
     * @return Submitter Next submitter to grade
     */
    public function getNextSubmitter(Submitter $submitter) {
        return $this->getNextSubmitterMatching($submitter, function(Submitter $sub) {
            return $this->getHasSubmission($sub);
        });

    }


    /**
     * Given the current submitter, get the previous submitter to grade.
     * Will only include students that cause $fn to return true
     * @param Submitter $submitter Current grading submitter
     * @param callable $fn Args: (Submitter) Returns: bool, true if the submitter should be included
     * @return Submitter Previous submitter to grade
     */
    public function getPrevSubmitterMatching(Submitter $submitter, callable $fn) {
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
            //Repeat until we find one that works
        } while (!$fn($sub));

        return $sub;
    }

    /**
     * Given the current submitter, get the next submitter to grade.
     * Will only include students that cause $fn to return true
     * @param Submitter $submitter Current grading submitter
     * @param callable $fn Args: (Submitter) Returns: bool, true if the submitter should be included
     * @return Submitter Next submitter to grade
     */
    public function getNextSubmitterMatching(Submitter $submitter, callable $fn) {
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
            //Repeat until we find one that works
        } while (!$fn($sub));

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
     * Determine if a given submitter has submitted, or false if they are not in any of these sections
     * @param Submitter $submitter
     * @return bool
     */
    public function getHasSubmission(Submitter $submitter) {
        if (array_key_exists($submitter->getId(), $this->has_submission)) {
            return $this->has_submission[$submitter->getId()];
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
     * @return Submitter[][] Map of section name => Submitter[]
     */
    public function getSectionSubmitters() {
        return $this->section_submitters;
    }

    /**
     * Get a list of all graders for all sections
     * @return User[][] Map of section name => User[]
     */
    public function getSectionGraders() {
        return array_combine(
            $this->getSectionNames(),
            array_map(function (GradingSection $section) {
                return $section->getGraders();
            }, $this->sections)
        );
    }

    /**
     * Get a list of all section names
     * @return string[] List of section names
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

    /**
     * Get graded gradeables for all students, in the correct order.
     * Note: This calls core->getQueries()->getGradedGradeables() so it's likely very slow. Use with caution.
     * @return GradedGradeable[] All graded gradeables for students, in the correct order
     */
    public function getSortedGradedGradeables() {
        $iter = $this->core->getQueries()->getGradedGradeables([$this->gradeable],
            $this->all_user_ids, $this->all_team_ids, [$this->getSectionKey(), 'team_id', 'user_id']);

        $gg_idx = [];
        $unsorted = [];
        foreach ($iter as $gg) {
            $idx = $this->getSubmitterIndex($gg->getSubmitter());
            //Should never happen, but better to be safe
            if ($idx === false) {
                $unsorted[] = $gg;
            } else {
                $gg_idx[$idx] = $gg;
            }
        }

        return array_merge($gg_idx, $unsorted);
    }
}

