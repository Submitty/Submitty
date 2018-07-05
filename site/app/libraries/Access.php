<?php


namespace app\libraries;


use app\models\Gradeable;
use app\models\User;

class Access {
    const USER_GROUP_INSTRUCTOR = 1;
    const USER_GROUP_TA         = 2;
    const USER_GROUP_MENTOR     = 3;
    const USER_GROUP_STUDENT    = 4;

    // Bit field for various access control options
    const ALLOW_INSTRUCTOR              = 1 << 0;
    const ALLOW_TA                      = 1 << 1;
    const ALLOW_MENTOR                  = 1 << 2;
    const ALLOW_STUDENT                 = 1 << 3;
    const ALLOW_LOGGED_OUT              = 1 << 4;
    const CHECK_GRADEABLE_MIN_GROUP     = 1 << 5;
    const CHECK_GRADING_SECTION_MENTOR  = 1 << 6;
    const CHECK_PEER_ASSIGNMENT_STUDENT = 1 << 7;

    //
    const ALLOW_MIN_STUDENT    = self::ALLOW_INSTRUCTOR | self::ALLOW_TA | self::ALLOW_MENTOR | self::ALLOW_STUDENT;
    const ALLOW_MIN_MENTOR     = self::ALLOW_INSTRUCTOR | self::ALLOW_TA | self::ALLOW_MENTOR;
    const ALLOW_MIN_TA         = self::ALLOW_INSTRUCTOR | self::ALLOW_TA;
    const ALLOW_MIN_INSTRUCTOR = self::ALLOW_INSTRUCTOR;

    /**
     * @var Core
     */
    private $core;
    private $permissions = [];

    public function __construct(Core $core) {
        $this->core = $core;

        $this->permissions["grading.show_hidden_cases"] = self::ALLOW_MIN_MENTOR | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_MENTOR;
        $this->permissions["grading.save_grade"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_MENTOR | self::CHECK_PEER_ASSIGNMENT_STUDENT;
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
            return !!($checks & self::ALLOW_LOGGED_OUT);
        }
        //Check user group first
        $group = $user->getGroup();
        if ($group === self::USER_GROUP_STUDENT && !($checks & self::ALLOW_STUDENT)) {
            return false;
        } else if ($group === self::USER_GROUP_MENTOR && !($checks & self::ALLOW_MENTOR)) {
            return false;
        } else if ($group === self::USER_GROUP_TA && !($checks & self::ALLOW_TA)) {
            return false;
        } else if ($group === self::USER_GROUP_INSTRUCTOR && !($checks & self::ALLOW_INSTRUCTOR)) {
            return false;
        }

        /* @var Gradeable|null $gradeable */
        $gradeable = $args["gradeable"] ?? null;

        if ($checks & self::CHECK_GRADEABLE_MIN_GROUP) {
            if ($group > $gradeable->getMinimumGradingGroup()) {
                return false;
            }
        }

        if ($group === self::USER_GROUP_MENTOR && ($checks & self::CHECK_GRADING_SECTION_MENTOR)) {
            //Check their grading section
            $who_id = $args["who_id"];

            if (!$this->checkGradingSection($gradeable, $who_id)) {
                return false;
            }
        }
        if ($group === self::USER_GROUP_STUDENT && ($checks & self::CHECK_PEER_ASSIGNMENT_STUDENT)) {
            //Check their grading section
            $who_id = $args["who_id"];

            if (!$this->checkPeerAssignment($gradeable, $who_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a limited access grader has a user in their section
     * @param Gradeable $gradeable
     * @param string $who_id
     * @return bool If they are
     */
    private function checkGradingSection(Gradeable $gradeable, string $who_id) {
        $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

        //If a user is a limited access grader, and the gradeable is being graded, and the
        // gradeable can be viewed by limited access graders.
        if ($gradeable->getGradeStartDate() <= $now) {
            //Check to see if the requested user is assigned to this grader.
            if ($gradeable->isGradeByRegistration()) {
                $sections = $this->core->getUser()->getGradingRegistrationSections();
                $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),
                    $this->core->getUser()->getId());
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            foreach($students as $student) {
                /* @var User $student */
                if($student->getId() === $who_id){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a student is allowed to peer grade another
     * @param Gradeable $gradeable
     * @param string $who_id
     * @return bool
     */
    private function checkPeerAssignment(Gradeable $gradeable, string $who_id) {
        if(!$gradeable->getPeerGrading()) {
            return false;
        } else {
            $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            return in_array($who_id, $user_ids_to_grade);
        }
    }
}