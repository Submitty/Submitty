<?php


namespace app\libraries;


use app\models\Gradeable;
use app\models\GradingSection;
use app\models\User;

class Access {
    const USER_GROUP_INSTRUCTOR            = 1;
    const USER_GROUP_FULL_ACCESS_GRADER    = 2;
    const USER_GROUP_LIMITED_ACCESS_GRADER = 3;
    const USER_GROUP_STUDENT               = 4;
    /** Logged out */
    const USER_GROUP_NONE                  = 5;

    // Access control options

    /** Allow Instructors to do this */
    const ALLOW_INSTRUCTOR              = 1 << 0;
    /** Allow full access graders to do this */
    const ALLOW_FULL_ACCESS_GRADER      = 1 << 1;
    /** Allow limited access graders to do this */
    const ALLOW_LIMITED_ACCESS_GRADER   = 1 << 2;
    /** Allow students to do this */
    const ALLOW_STUDENT                 = 1 << 3;
    /** Allow logged out users to do this */
    const ALLOW_LOGGED_OUT              = 1 << 4;
    /** Check that the current user is at or above the minimum grading group required for a gradeable */
    const CHECK_GRADEABLE_MIN_GROUP     = 1 << 5;
    /**
     * Check that a given user is in the current user's grading section for a gradeable
     * Only applies to limited access graders
     */
    const CHECK_GRADING_SECTION_GRADER  = 1 << 6;
    /**
     * Check that a given user is in the current user's peer grading assignment for a gradeable
     * Only applies to students
     */
    const CHECK_PEER_ASSIGNMENT_STUDENT = 1 << 7;
    /** Require that the given gradeable have an active version / submission */
    const CHECK_HAS_SUBMISSION          = 1 << 8;
    /** Check that a valid CSRF token was passed in the request */
    const CHECK_CSRF                    = 1 << 9;

    // Broader user group access cases since generally actions are "minimum this group"

    const ALLOW_MIN_STUDENT               = self::ALLOW_INSTRUCTOR | self::ALLOW_FULL_ACCESS_GRADER | self::ALLOW_LIMITED_ACCESS_GRADER | self::ALLOW_STUDENT;
    const ALLOW_MIN_LIMITED_ACCESS_GRADER = self::ALLOW_INSTRUCTOR | self::ALLOW_FULL_ACCESS_GRADER | self::ALLOW_LIMITED_ACCESS_GRADER;
    const ALLOW_MIN_FULL_ACCESS_GRADER    = self::ALLOW_INSTRUCTOR | self::ALLOW_FULL_ACCESS_GRADER;
    const ALLOW_MIN_INSTRUCTOR            = self::ALLOW_INSTRUCTOR;

    /**
     * @var Core
     */
    private $core;
    private $permissions = [];

    public function __construct(Core $core) {
        $this->core = $core;

        $this->permissions["grading.details"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.grade"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT;
        $this->permissions["grading.show_hidden_cases"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
        $this->permissions["grading.save_one_component"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_HAS_SUBMISSION;
        $this->permissions["grading.save_general_comment"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_HAS_SUBMISSION;
        $this->permissions["grading.get_mark_data"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT;
        $this->permissions["grading.get_gradeable_comment"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT;
        $this->permissions["grading.add_one_new_mark"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
        $this->permissions["grading.delete_one_mark"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
        $this->permissions["grading.import_teams"] = self::ALLOW_MIN_INSTRUCTOR | self::CHECK_CSRF;
        $this->permissions["grading.export_teams"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.submit_team_form"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.verify_grader"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.verify_all"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
    }

    /**
     * Check if the currently logged in user is allowed to do an action
     * @param string $action Name of the action (see Access::$permissions)
     * @param array $args Any extra arguments that are required to check permissions
     * @return bool True if they are allowed to do that action
     */
    public function canI(string $action, $args = []) {
        if (!array_key_exists($action, $this->permissions)) {
            return false;
        }
        $checks = $this->permissions[$action];

        //Some things may be available when there is no user
        $user = $this->core->getUser();
        if ($user === null) {
            if (!($checks & self::ALLOW_LOGGED_OUT)) {
                return false;
            }
            $group = self::USER_GROUP_NONE;
        } else {
            $group = $user->getGroup();
        }

        //Check user group first
        if ($group === self::USER_GROUP_STUDENT && !($checks & self::ALLOW_STUDENT)) {
            return false;
        } else if ($group === self::USER_GROUP_LIMITED_ACCESS_GRADER && !($checks & self::ALLOW_LIMITED_ACCESS_GRADER)) {
            return false;
        } else if ($group === self::USER_GROUP_FULL_ACCESS_GRADER && !($checks & self::ALLOW_FULL_ACCESS_GRADER)) {
            return false;
        } else if ($group === self::USER_GROUP_INSTRUCTOR && !($checks & self::ALLOW_INSTRUCTOR)) {
            return false;
        }

        if ($checks & self::CHECK_CSRF) {
            if ($this->core->checkCsrfToken()) {
                return false;
            }
        }

        if ($checks & self::CHECK_GRADEABLE_MIN_GROUP) {
            /* @var Gradeable|null $gradeable */
            $gradeable = $args["gradeable"] ?? null;
            if ($gradeable === null) {
                return false;
            }
            if (!$this->checkGroupPrivilege($group, $gradeable->getMinimumGradingGroup())) {
                return false;
            }
        }

        if ($checks & self::CHECK_HAS_SUBMISSION) {
            /* @var Gradeable|null $gradeable */
            $gradeable = $args["gradeable"] ?? null;
            if ($gradeable === null) {
                return false;
            }
            if ($gradeable->getActiveVersion() <= 0) {
                return false;
            }
        }

        if ($group === self::USER_GROUP_LIMITED_ACCESS_GRADER && ($checks & self::CHECK_GRADING_SECTION_GRADER)) {
            /* @var Gradeable|null $gradeable */
            $gradeable = $args["gradeable"] ?? null;
            if ($gradeable === null) {
                return false;
            }
            //Check their grading section
            if (!$this->checkGradingSection($gradeable)) {
                return false;
            }
        }
        if ($group === self::USER_GROUP_STUDENT && ($checks & self::CHECK_PEER_ASSIGNMENT_STUDENT)) {
            /* @var Gradeable|null $gradeable */
            $gradeable = $args["gradeable"] ?? null;
            if ($gradeable === null) {
                return false;
            }
            //Check their peer assignment
            if (!$this->checkPeerAssignment($gradeable)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a limited access grader has a user in their section
     * @param Gradeable $gradeable
     * @return bool If they are
     */
    public function checkGradingSection(Gradeable $gradeable) {
        $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

        //If a user is a limited access grader, and the gradeable is being graded, and the
        // gradeable can be viewed by limited access graders.
        if ($gradeable->getGradeStartDate() <= $now) {
            //Check to see if the requested user is assigned to this grader.
            $sections = $gradeable->getGradingSectionsForUser($this->core->getUser());


            foreach ($sections as $section) {
                /** @var GradingSection $section */
                if ($gradeable->isTeamAssignment()) {
                    return $section->containsTeam($gradeable->getTeam());
                } else {
                    return $section->containsUser($gradeable->getUser());
                }
            }
        }

        return false;
    }

    /**
     * Check if a student is allowed to peer grade another
     * @param Gradeable $gradeable
     * @return bool
     */
    public function checkPeerAssignment(Gradeable $gradeable) {
        if (!$gradeable->getPeerGrading()) {
            return false;
        } else {
            $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            return in_array($gradeable->getUser()->getId(), $user_ids_to_grade);
        }
    }

    /**
     * Check that a a user group has privilege at least equal to minimum
     * @param int $check
     * @param int $minimum
     * @return bool
     */
    public function checkGroupPrivilege(int $check, int $minimum) {
        //Because access levels decrease as they get more powerful, this needs to be <=
        // If groups ever become non-sequential in the future, this needs to be replaced.
        return $check <= $minimum;
    }
}