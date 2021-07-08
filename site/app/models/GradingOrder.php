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
     * @var string[] $not_fully_graded
     */
    protected $not_fully_graded;

    /**
     * @var string[] $grade_inquiry_users
     */
    protected $grade_inquiry_users;

    /**
     * @var int|null $notebook_item_index
     */
    protected $notebook_item_index;

    /**
     * @var int|null $itempool_selected_idx
     */
    protected $itempool_selected_idx;

    /**
     * GradingOrder constructor.
     * @param Core $core
     * @param Gradeable $gradeable
     * @param User $user The current user (the one that is doing the grading)
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
        }
        else {
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
            }
            else {
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

        $this->notebook_item_index = null;
        $this->itempool_selected_idx = null;
    }

    /**
     * Sort grading order.
     */
    public function sort($type, $direction) {
        //Function to turn submitters into "keys" that are sorted (like python's list.sort)
        $keyFn = function (Submitter $a) {
            return $a->getId();
        };

        switch ($type) {
            case "id":
                $keyFn = function (Submitter $a) {
                    return $a->getId();
                };
                break;
            case "first":
                $keyFn = function (Submitter $a) {
                    if ($a->isTeam()) {
                        return $a->getId();
                    }
                    else {
                        return $a->getUser()->getDisplayedFirstName();
                    }
                };
                break;
            case "last":
                $keyFn = function (Submitter $a) {
                    if ($a->isTeam()) {
                        return $a->getId();
                    }
                    else {
                        return $a->getUser()->getDisplayedLastName();
                    }
                };
                break;
            case "random":
                $keyFn = function (Submitter $a) {
                    //So it's (pseudo) randomly ordered, and will be different for each gradeable
                    return md5($a->getId() . $this->gradeable->getId());
                };
                break;
        }

        //Sort based on the keys
        foreach ($this->section_submitters as $name => &$section) {
            //For efficiency, run all the submitters through the key function first and then just compare the keys
            $keys = [];
            foreach ($section as $submitter) {
                $keys[$submitter->getId()] = $keyFn($submitter);
            }

            $directionMult = ($direction === "DESC" ? -1 : 1);

            usort($section, function (Submitter $a, Submitter $b) use ($keys, $directionMult) {
                return strcmp($keys[$a->getId()], $keys[$b->getId()]) * $directionMult;
            });
        }
        unset($section);
    }

    /**
     * Given the current submitter, get the previous submitter to grade.
     * Will skip students that do not need to be graded (eg no submission)
     * @param Submitter $submitter Current grading submitter
     * @param int $component_id Component ID to check (for ungraded/itempool)
     * @param string $filter The filter to use when checking submitters
     * @return Submitter|null Previous submitter to grade
     */
    public function getPrevSubmitter(Submitter $submitter, int $component_id = -1, string $filter = 'default'): ?Submitter {
        return $this->getPrevSubmitterMatching($submitter, $this->getFilterFunction($submitter, $component_id, $filter));
    }

    /**
     * Given the current submitter, get the next submitter to grade.
     * Will skip students that do not need to be graded (eg no submission)
     * @param Submitter $submitter Current grading submitter
     * @param int $component_id Component ID to check (for ungraded/itempool)
     * @param string $filter The filter to use when checking submitters
     * @return Submitter|null Next submitter to grade
     */
    public function getNextSubmitter(Submitter $submitter, int $component_id = -1, string $filter = 'default'): ?Submitter {
        return $this->getNextSubmitterMatching($submitter, $this->getFilterFunction($submitter, $component_id, $filter));
    }

    /**
     * Queries the database to populate $this->not_fully_graded
     *
     * @param $component_id
     */
    private function initUsersNotFullyGraded($component_id) {
        if (is_null($this->not_fully_graded)) {
            $this->not_fully_graded = $this->core->getQueries()->getUsersNotFullyGraded($this->gradeable, $component_id);
        }
    }

    /**
     * Queries the database to populate $this->grade_inquiry_users
     * @param bool $ungraded If it should only find active grade inquiries
     * @param int $component_id The component ID in the gradeable
     * @return void
     */
    private function initUsersGradeInquiry(bool $ungraded, int $component_id) {
        if (is_null($this->grade_inquiry_users)) {
            $this->grade_inquiry_users = $this->core->getQueries()->getRegradeRequestsUsers($this->gradeable->getId(), $ungraded, $component_id);
        }
    }

    /**
     * Given the selected filter, returns a function that can be used to filter students in getSubmitterMatching
     * @param Submitter $submitter Current grading submitter
     * @param int $component_id The component ID selected; -1 if no component was selected
     * @param string $filter The name of the filter.
     * @return callable Args: (Submitter) Returns: bool, true if the submitter should be included
     */
    private function getFilterFunction(Submitter $submitter, int $component_id, string $filter) {
        if ($filter === 'default') {
            return function (Submitter $sub) {
                return $this->getHasSubmission($sub);
            };
        }
        elseif ($filter === 'ungraded') {
            $this->initUsersNotFullyGraded($component_id);

            return function (Submitter $sub) {
                return in_array($sub->getId(), $this->not_fully_graded) && $this->getHasSubmission($sub);
            };
        }
        elseif ($filter === 'itempool') {
            if ($component_id !== -1 && $this->getItempoolIndicesForSubmitter($submitter, $component_id)) {
                return function (Submitter $sub) {
                    return $this->getHasSubmission($sub) && $this->isSameItempool($sub);
                };
            }
            return $this->getFilterFunction($submitter, $component_id, 'default');
        }
        elseif ($filter === 'ungraded-itempool') {
            $this->initUsersNotFullyGraded($component_id);

            if ($component_id !== -1 && $this->getItempoolIndicesForSubmitter($submitter, $component_id)) {
                return function (Submitter $sub) {
                    return in_array($sub->getId(), $this->not_fully_graded) && $this->getHasSubmission($sub) && $this->isSameItempool($sub);
                };
            }
            return $this->getFilterFunction($submitter, $component_id, 'ungraded');
        }
        elseif ($filter === 'inquiry') {
            if ($this->core->getConfig()->isRegradeEnabled()) {
                $this->initUsersGradeInquiry(false, $component_id);

                return function (Submitter $sub) {
                    return in_array($sub->getId(), $this->grade_inquiry_users) && $this->getHasSubmission($sub);
                };
            }
        }
        elseif ($filter === 'active-inquiry') {
            if ($this->core->getConfig()->isRegradeEnabled()) {
                $this->initUsersGradeInquiry(true, $component_id);

                return function (Submitter $sub) {
                    return in_array($sub->getId(), $this->grade_inquiry_users) && $this->getHasSubmission($sub);
                };
            }
        }
        return function (Submitter $sub) {
            return $this->getHasSubmission($sub);
        };
    }

    /**
     * Given the current submitter, get the previous submitter to grade.
     * Will only include students that cause $fn to return true
     * @param Submitter $submitter Current grading submitter
     * @param callable $fn Args: (Submitter) Returns: bool, true if the submitter should be included
     * @return Submitter Previous submitter to grade
     */
    public function getPrevSubmitterMatching(Submitter $submitter, callable $fn) {

        // If $submitter is in one of our sections, then get the $submitters index in the GradingOrder
        if ($this->containsSubmitter($submitter)) {
            $index = $this->getSubmitterIndex($submitter);
            if ($index === false) {
                return null;
            }

        // Else $submitter is not in one of our sections so set $index to number of submitters in our sections
        }
        else {
            $count = 0;

            foreach ($this->section_submitters as $section) {
                $count += count($section);
            }

            $index = $count;
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

        // If $submitter is in one of our sections, then get the $submitters index in the GradingOrder
        if ($this->containsSubmitter($submitter)) {
            $index = $this->getSubmitterIndex($submitter);
            if ($index === false) {
                return null;
            }

        // Else $submitter is not in one of our sections so set $index to -1
        }
        else {
            $index = -1;
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
            for ($i = 0; $i < count($section); $i++) {
                $testSub = $section[$i];

                //Found them
                if ($testSub->getId() === $submitter->getId()) {
                    return $count;
                }

                $count++;
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
        $iter = $this->core->getQueries()->getGradedGradeables(
            [$this->gradeable],
            $this->all_user_ids,
            $this->all_team_ids,
            [$this->getSectionKey(), 'team_id', 'user_id']
        );
        $gg_idx = [];
        $unsorted = [];
        foreach ($iter as $gg) {
            $idx = $this->getSubmitterIndex($gg->getSubmitter());
            //Should never happen, but better to be safe
            if ($idx === false) {
                $unsorted[] = $gg;
            }
            else {
                $gg_idx[$idx] = $gg;
            }
        }
        //Since the array's elements were not added in the same order as the indices, sort to fix it
        ksort($gg_idx);
        return array_merge($gg_idx, $unsorted);
    }

    /**
     * Check if the submitter has the same itempool item for the same component as the one previously identified by getItempoolIndicesForSubmitter
     * @param Submitter $submitter The submitter to check
     * @return bool If the submitter has the same itempool item as the one identified by getItempoolIndicesForSubmitter
     */
    public function isSameItempool(Submitter $submitter): bool {
        $que_idx = 0;

        $gradeable_config = $this->gradeable->getAutogradingConfig();

        $notebook_config = $gradeable_config->getNotebookConfig();
        $hashes = $gradeable_config->getUserSpecificNotebook($submitter->getId())->getHashes();
        foreach ($notebook_config as $key => $item) {
            if ($key === $this->notebook_item_index) {
                $selected_idx = $item["user_item_map"][$submitter->getId()] ?? null;
                if (is_null($selected_idx)) {
                    $selected_idx = $hashes[$que_idx] % count($item['from_pool']);
                }
                return $selected_idx % count($item['from_pool']) === $this->itempool_selected_idx;
            }
            elseif (isset($item['type']) && $item['type'] === 'item') {
                $selected_idx = $item["user_item_map"][$submitter->getId()] ?? null;
                if (is_null($selected_idx)) {
                    $que_idx++;
                }
            }
        }
        return false;
    }

    /**
     * Search for the itempool item for the specified component in the notebook config and save data about the location and submitter's itempool if found
     * @param Submitter $submitter The submitter to store the itempool item index for
     * @param int $component_id The ID of the component that is linked to the target itempool
     * @return bool If the itempool item was found and if the data was saved
     */
    public function getItempoolIndicesForSubmitter(Submitter $submitter, int $component_id): bool {
        $que_idx = 0;

        $item_label = $this->gradeable->getComponent($component_id)->getItempool();

        $gradeable_config = $this->gradeable->getAutogradingConfig();

        $notebook_config = $gradeable_config->getNotebookConfig();
        $hashes = $gradeable_config->getUserSpecificNotebook($submitter->getId())->getHashes();
        foreach ($notebook_config as $key => $item) {
            if (isset($item['type']) && $item['type'] === 'item') {
                $selected_idx = $item["user_item_map"][$submitter->getId()] ?? null;
                if (is_null($selected_idx)) {
                    $selected_idx = $hashes[$que_idx] % count($item['from_pool']);
                    $que_idx++;
                }
                if ($item_label === $item['item_label']) {
                    $this->notebook_item_index = $key;
                    $this->itempool_selected_idx = $selected_idx;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns an string describing the ordering based on its sort type and direction, or an empty string
     * if an unknown sort / direction combination is passed in.
     *
     * @param $sort Sort type
     * @param $direction Direction of sort (ASC or DESC)
     * @return string
     */
    public static function getGradingOrderMessage($sort, $direction) {

        if ($sort == 'first' && $direction == 'ASC') {
            $msg = 'First Name Ascending';
        }
        elseif ($sort == 'first' && $direction == 'DESC') {
            $msg = 'First Name Descending';
        }
        elseif ($sort == 'last' && $direction == 'ASC') {
            $msg = 'Last Name Ascending';
        }
        elseif ($sort == 'last' && $direction == 'DESC') {
            $msg = 'Last Name Descending';
        }
        elseif ($sort == 'id' && $direction == 'ASC') {
            $msg = 'ID Ascending';
        }
        elseif ($sort == 'id' && $direction == 'DESC') {
            $msg = 'ID Descending';
        }
        elseif ($sort == 'random') {
            $msg = 'Randomized';
        }
        else {
            $msg = false;
        }

        return $msg === false ? '' : "$msg Order";
    }
}
