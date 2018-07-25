<?php

namespace app\libraries;

use app\models\Gradeable;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\TaGradedGradeable;
use app\models\GradeableComponent;
use app\models\GradingSection;
use app\models\User;
use InvalidArgumentException;

class Access {
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
    /**
     * Check that the current user is at or above the minimum grading group required for a gradeable
     * If the gradeable has peer grading, this will also accept for students
     */
    const CHECK_GRADEABLE_MIN_GROUP     = 1 << 5 | self::REQUIRE_ARG_GRADEABLE;
    /**
     * Check that a given user is in the current user's grading section for a gradeable
     * Only applies to limited access graders
     */
    const CHECK_GRADING_SECTION_GRADER  = 1 << 6 | self::REQUIRE_ARG_GRADEABLE;
    /**
     * Check that a given user is in the current user's peer grading assignment for a gradeable
     * Only applies to students
     */
    const CHECK_PEER_ASSIGNMENT_STUDENT = 1 << 7 | self::REQUIRE_ARG_GRADEABLE;
    /** Require that the given gradeable have an active version / submission */
    const CHECK_HAS_SUBMISSION          = 1 << 8 | self::REQUIRE_ARG_GRADEABLE;
    /** Check that a valid CSRF token was passed in the request */
    const CHECK_CSRF                    = 1 << 9;
    /** Allow access if the gradeable is our own, even if sections are checked */
    const ALLOW_SELF_GRADEABLE          = 1 << 10 | self::REQUIRE_ARG_GRADEABLE;
    /**
     * Check if the given component allows peer grading
     * Only applies to students
     */
    const CHECK_COMPONENT_PEER_STUDENT  = 1 << 11 | self::REQUIRE_ARG_COMPONENT;

    /** If the current set of flags requires the "gradeable" argument */
    const REQUIRE_ARG_GRADEABLE         = 1 << 24;
    /** If the current set of flags requires the "gradeable" argument */
    const REQUIRE_ARG_COMPONENT         = 1 << 25;

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

        $this->permissions["grading.electronic.status"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.electronic.status.full"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.electronic.status.warnings"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.electronic.details"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.electronic.details.show_all"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.electronic.details.show_all_no_sections"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.electronic.details.show_empty_teams"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.electronic.grade"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT;
        $this->permissions["grading.electronic.grade.if_no_sections_exist"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.electronic.save_one_component"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_HAS_SUBMISSION | self::CHECK_COMPONENT_PEER_STUDENT;
        $this->permissions["grading.electronic.save_general_comment"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_HAS_SUBMISSION;
        $this->permissions["grading.electronic.get_mark_data"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_COMPONENT_PEER_STUDENT;
        $this->permissions["grading.electronic.get_gradeable_comment"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT;
        $this->permissions["grading.electronic.add_one_new_mark"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
        $this->permissions["grading.electronic.delete_one_mark"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
        $this->permissions["grading.electronic.get_marked_users"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.electronic.get_marked_users.full_stats"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.electronic.show_edit_teams"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.electronic.import_teams"] = self::ALLOW_MIN_INSTRUCTOR | self::CHECK_CSRF;
        $this->permissions["grading.electronic.export_teams"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.electronic.submit_team_form"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.electronic.verify_grader"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.electronic.verify_all"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;

        $this->permissions["autograding.load_checks"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::ALLOW_SELF_GRADEABLE;
        $this->permissions["autograding.show_hidden_cases"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;


        $this->permissions["grading.simple"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER;
        $this->permissions["grading.simple.grade"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.simple.show_all"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
    }

    /**
     * Check if the currently logged in user is allowed to do an action
     * @param string $action Name of the action (see Access::$permissions)
     * @param array $args Any extra arguments that are required to check permissions
     * @return bool True if they are allowed to do that action
     */
    public function canI(string $action, $args = []) {
        $user = $args["user"] ?? $this->core->getUser();
        return $this->canUser($user, $action, $args);
    }

    /**
     * Check if a user is allowed to do an action
     * @param User|null $user User to check access for
     * @param string $action Name of the action (see Access::$permissions)
     * @param array $args Any extra arguments that are required to check permissions
     * @return bool True if they are allowed to do that action
     */
    public function canUser($user, string $action, $args) {
        if (!array_key_exists($action, $this->permissions)) {
            throw new InvalidArgumentException("Unknown action '$action'");
        }
        $checks = $this->permissions[$action];

        //Some things may be available when there is no user
        if ($user === null) {
            if (!($checks & self::ALLOW_LOGGED_OUT)) {
                return false;
            }
            $group = User::GROUP_NONE;
        } else {
            $group = $user->getGroup();
        }

        //Check user group first
        if ($group === User::GROUP_STUDENT && !self::checkBits($checks, self::ALLOW_STUDENT)) {
            return false;
        } else if ($group === User::GROUP_LIMITED_ACCESS_GRADER && !self::checkBits($checks, self::ALLOW_LIMITED_ACCESS_GRADER)) {
            return false;
        } else if ($group === User::GROUP_FULL_ACCESS_GRADER && !self::checkBits($checks, self::ALLOW_FULL_ACCESS_GRADER)) {
            return false;
        } else if ($group === User::GROUP_INSTRUCTOR && !self::checkBits($checks, self::ALLOW_INSTRUCTOR)) {
            return false;
        }

        if (self::checkBits($checks, self::CHECK_CSRF)) {
            if ($this->core->checkCsrfToken()) {
                return false;
            }
        }

        if (self::checkBits($checks, self::REQUIRE_ARG_GRADEABLE)) {
            //If we have a graded gradeable, it should have the regular gradeable
            // in it already
            if (array_key_exists("graded_gradeable", $args)) {
                $g = $args["graded_gradeable"];
            } else {
                $g = $this->requireArg($args, "gradeable");
                if ($g === null) {
                    return false;
                }
            }

            /* @var Gradeable|null $gradeable */
            /* @var Gradeable|null $graded_gradeable */
            /* @var \app\models\gradeable\Gradeable|null $new_gradeable */
            /* @var GradedGradeable|null $new_graded_gradeable */
            list($gradeable, $graded_gradeable, $new_gradeable, $new_graded_gradeable) = self::resolveNewGradeable($g);

            if (self::checkBits($checks, self::CHECK_GRADEABLE_MIN_GROUP)) {
                //Make sure they meet the minimum requirements
                $minimum = $new_gradeable ? $new_gradeable->getMinGradingGroup() : $gradeable->getMinimumGradingGroup();
                if (!$this->checkGroupPrivilege($group, $minimum)) {

                    if (
                        //Full access graders are allowed to view submissions if there is no manual grading
                        !($group === User::GROUP_FULL_ACCESS_GRADER && !$gradeable->isTaGrading())
                        &&
                        //Students are allowed to see this if its a peer graded assignment
                        !($group === User::GROUP_STUDENT && $gradeable->isPeerGrading())
                       ) {

                        //Otherwise, you're not allowed
                        return false;
                    }
                }
            }

            if (self::checkBits($checks, self::CHECK_HAS_SUBMISSION)) {
                if (($new_graded_gradeable ? $new_graded_gradeable->getAutoGradedGradeable()->getActiveVersion() : $graded_gradeable->getActiveVersion()) <= 0) {
                    return false;
                }
            }

            if (self::checkBits($checks, self::CHECK_GRADING_SECTION_GRADER) && $group === User::GROUP_LIMITED_ACCESS_GRADER) {
                //Check their grading section
                if (!$this->checkGradingSection($new_graded_gradeable ?? $graded_gradeable)) {
                    return false;
                }
            }

            if (self::checkBits($checks, self::CHECK_PEER_ASSIGNMENT_STUDENT) && $group === User::GROUP_STUDENT) {
                //If they're allowed to view their own
                if (!($gradeable->getUser()->getId() === $user->getId() && self::checkBits($checks, self::ALLOW_SELF_GRADEABLE))) {
                    //Check their peer assignment
                    if (!$this->checkPeerAssignment($gradeable)) {
                        return false;
                    }
                }
            }
        }

        if (self::checkBits($checks, self::REQUIRE_ARG_COMPONENT)) {
            /* @var GradeableComponent|null $component */
            $component = $this->requireArg($args, "component");
            if ($component === null) {
                return false;
            }

            if (self::checkBits($checks, self::CHECK_COMPONENT_PEER_STUDENT) && $group === User::GROUP_STUDENT) {
                //Make sure a component allows students to access it via peer grading
                if (!$component->getIsPeer()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Require that a named key is in the list of args. Throws otherwise
     * @param array $args List of args
     * @param string $name Name of required arg
     * @return mixed Arg value
     */
    private function requireArg(array $args, string $name) {
        if (!array_key_exists($name, $args)) {
            throw new InvalidArgumentException("Missing argument '$name'");
        }
        return $args[$name];
    }

    /**
     * Check if a bit set matches a given bit mask
     * @param int $bits Bit set (list of flags)
     * @param int $test Bit mask (flags to check for)
     * @return bool If matches
     */
    private function checkBits(int $bits, int $test) {
        return ($bits & $test) === $test;
    }

    /**
     * Check if a limited access grader has a user in their section
     * @param Gradeable|GradedGradeable $g
     * @return bool If they are
     */
    public function checkGradingSection($g) {
        $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

        /* @var Gradeable|null $gradeable */
        /* @var Gradeable|null $graded_gradeable */
        /* @var \app\models\gradeable\Gradeable|null $new_gradeable */
        /* @var GradedGradeable|null $new_graded_gradeable */
        list($gradeable, $graded_gradeable, $new_gradeable, $new_graded_gradeable) = self::resolveNewGradeable($g);

        //If it's not a user's gradeable then you can't check grading section
        if (($new_gradeable)->getUser() === null && ($new_gradeable)->getTeam() === null) {
            return true;
        }

        //If a user is a limited access grader, and the gradeable is being graded, and the
        // gradeable can be viewed by limited access graders.
        if (($gradeable ?? $new_gradeable)->getGradeStartDate() <= $now) {
            //Check to see if the requested user is assigned to this grader.
            $sections = ($gradeable ?? $new_gradeable)->getGradingSectionsForUser($this->core->getUser());


            foreach ($sections as $section) {
                /** @var GradingSection $section */
                if (($gradeable ?? $new_gradeable)->isTeamAssignment()) {
                    if ($section->containsTeam(($new_gradeable)->getTeam())) {
                        return true;
                    }
                } else {
                    if ($section->containsUser(($new_gradeable)->getUser())) {
                        return true;
                    }
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
     * Check that a user group has privilege at least equal to minimum
     * @param int $check
     * @param int $minimum
     * @return bool
     */
    public function checkGroupPrivilege(int $check, int $minimum) {
        //Because access levels decrease as they get more powerful, this needs to be <=
        // If groups ever become non-sequential in the future, this needs to be replaced.
        return $check <= $minimum;
    }

    /**
     * TODO: Remove this enormous hack when Kevin does the everything
     * Get all the permutations of [Graded]Gradeables from an unknown type gradeable-like object
     * Here are the classes that are currently supported:
     * \app\models\Gradeable
     * \app\models\gradeable\Gradeable
     * \app\models\gradeable\GradedGradeable
     * \app\models\gradeable\TaGradedGradeable
     * \app\models\gradeable\AutoGradedGradeable
     * @param mixed|null $g
     * @return array
     */
    private static function resolveNewGradeable($g) {
        $gradeable = null;
        $graded_gradeable = null;
        $new_gradeable = null;
        $new_graded_gradeable = null;
        if ($g !== null) {
            if ($g instanceof GradedGradeable) {
                $gradeable = null;
                $graded_gradeable = null;
                $new_gradeable = $g->getGradeable();
                $new_graded_gradeable = $g;
            } else if ($g instanceof \app\models\gradeable\Gradeable) {
                $gradeable = null;
                $graded_gradeable = null;
                $new_gradeable = $g;
                $new_graded_gradeable = null;
            } else if ($g instanceof Gradeable) {
                $gradeable = $g;
                $graded_gradeable = $g;
                $new_gradeable = null;
                $new_graded_gradeable = null;
            } else if ($g instanceof TaGradedGradeable) {
                $gradeable = null;
                $graded_gradeable = null;
                $new_gradeable = $g->getGradedGradeable()->getGradeable();
                $new_graded_gradeable = $g->getGradedGradeable();
            } else if ($g instanceof AutoGradedGradeable) {
                $gradeable = null;
                $graded_gradeable = null;
                $new_gradeable = $g->getGradedGradeable()->getGradeable();
                $new_graded_gradeable = $g->getGradedGradeable();
            }
        }

        /* @var Gradeable|null $gradeable */
        /* @var Gradeable|null $graded_gradeable */
        /* @var \app\models\gradeable\Gradeable|null $new_gradeable */
        /* @var GradedGradeable|null $new_graded_gradeable */
        return [$gradeable, $graded_gradeable, $new_gradeable, $new_graded_gradeable];
    }
}