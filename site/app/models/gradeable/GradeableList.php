<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\GradeableType;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class GradeableList
 *
 * Container of gradeables in the system. This loads all the gradeables for either the logged in
 * user or a specific user, split up into several different lists depending on type, as well as
 * how the dates are configured for that gradeable. The order of the gradeables in these lists
 * are either by a specific datetime (if different) with later datetimes being earlier in the list
 * and then ids (if datetimes are equal) by alphabetically.
 *
 * @method \app\models\gradeable\Gradeable[] getGradeables()
 * @method \app\models\gradeable\Gradeable[] getFutureGradeables()
 * @method \app\models\gradeable\Gradeable[] getBetaGradeables()
 * @method \app\models\gradeable\Gradeable[] getOpenGradeables()
 * @method \app\models\gradeable\Gradeable[] getClosedGradeables()
 * @method \app\models\gradeable\Gradeable[] getGradingGradeables()
 * @method \app\models\gradeable\Gradeable[] getGradedGradeables()
 */
class GradeableList extends AbstractModel {
    const FUTURE  = 0;
    const BETA    = 1;
    const OPEN    = 2;
    const CLOSED  = 3;
    const GRADING = 4;
    const GRADED  = 5;

    /** @prop @var User */
    protected $user;

    /**
     * @prop
     * @var array<string, \app\models\gradeable\Gradeable>
     */
    protected $gradeables = [];

    /*
     * All elements of $this->gradeables should fall into one of the following six lists. There should
     * be no overlap between them.
     */
    /** @prop @var \app\models\gradeable\Gradeable[] These are gradeables which are only viewable by admin users */
    protected $future_gradeables = [];
    /** @prop @var \app\models\gradeable\Gradeable[] These are gradeables which are only viewable by admin & TA users */
    protected $beta_gradeables = [];
    /** @prop @var \app\models\gradeable\Gradeable[] */
    protected $open_gradeables = [];
    /** @prop @var \app\models\gradeable\Gradeable[] */
    protected $closed_gradeables = [];
    /** @prop @var \app\models\gradeable\Gradeable[] */
    protected $grading_gradeables = [];
    /** @prop @var \app\models\gradeable\Gradeable[] */
    protected $graded_gradeables = [];

    /**
     * @var \DateTime Timestamp of when we initially loaded the GradeableList so that all timestamp comparisons are
     * against the same time (and don't have any potential mismatch of seconds */
    protected $now;

    /**
     * GradeableList constructor.
     *
     * @param Core $core
     * @param ?User $user The user to filter gradeables by (null if current user)
     * @param ?array<string, \app\models\gradeable\Gradeable> $gradeables
     * @throws \Exception if a Gradeable failed to load from the database
     */
    public function __construct(Core $core, User $user = null, ?array $gradeables = null) {
        parent::__construct($core);
        $this->user = ($user === null) ? $this->core->getUser() : $user;
        if ($gradeables === null) {
            foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
                /** @var Gradeable $gradeable */
                $this->gradeables[$gradeable->getId()] = $gradeable;
            }
        }
        else {
            $this->gradeables = $gradeables;
        }

        $this->now = $this->core->getDateTimeNow();

        foreach ($this->gradeables as $id => $gradeable) {
            if ($gradeable->getGradeReleasedDate() <= $this->now) {
                $this->graded_gradeables[$id] = $gradeable;
            }
            elseif ($gradeable->getType() === GradeableType::ELECTRONIC_FILE && !$gradeable->hasDueDate()) {
                // Filter out gradeables with no due date
                if ($gradeable->isStudentSubmit()) {
                    if ($gradeable->getGradeStartDate() < $this->core->getDateTimeNow() && $this->core->getUser()->accessGrading()) {
                        // Put in 'grading' category only if user is a grader
                        $this->grading_gradeables[$id] = $gradeable;
                    }
                    else {
                        $this->open_gradeables[$id] = $gradeable;
                    }
                }
                else {
                    // If there is no due date and no student submission, it should
                    //  automatically show up in the 'Grading' category
                    $this->grading_gradeables[$id] = $gradeable;
                }
            }
            elseif (
                (
                    ($gradeable->getType() === GradeableType::ELECTRONIC_FILE && $gradeable->isTaGrading())
                    || $gradeable->getType() !== GradeableType::ELECTRONIC_FILE
                )
                && $gradeable->getGradeStartDate() <= $this->now
            ) {
                $this->grading_gradeables[$id] = $gradeable;
            }
            elseif (
                $gradeable->getType() === GradeableType::ELECTRONIC_FILE
                && $gradeable->getSubmissionOpenDate() <= $this->now
                && $gradeable->getSubmissionDueDate() <= $this->now
            ) {
                $this->closed_gradeables[$id] = $gradeable;
            }
            elseif (
                $gradeable->getType() === GradeableType::ELECTRONIC_FILE
                && $gradeable->getSubmissionOpenDate() <= $this->now
                && $gradeable->getTaViewStartDate() <= $this->now
            ) {
                $this->open_gradeables[$id] = $gradeable;
            }
            elseif ($this->core->getUser()->accessGrading() && $gradeable->getTaViewStartDate() <= $this->now) {
                $this->beta_gradeables[$id] = $gradeable;
            }
            elseif ($this->core->getUser()->accessAdmin()) {
                $this->future_gradeables[$id] = $gradeable;
            }
        }
        $sort_array = [
            'future_gradeables' => 'getGradeStartDate',
            'beta_gradeables' => 'getGradeStartDate',
            'open_gradeables' => 'getSubmissionDueDate',
            'closed_gradeables' => 'getSubmissionDueDate',
            'grading_gradeables' => 'getGradeStartDate',
            'graded_gradeables' => 'getGradeReleasedDate'
        ];
        foreach ($sort_array as $list => $function) {
            uasort($this->$list, function (Gradeable $a, Gradeable $b) use ($function) {
                if ($a->$function() == $b->$function()) {
                    if (strtolower($a->getTitle()) == strtolower($b->getTitle())) {
                        if (strtolower($a->getId()) < strtolower($b->getId())) {
                            return -1;
                        }
                        else {
                            return 1;
                        }
                    }
                    elseif (strtolower($a->getTitle()) < strtolower($b->getTitle())) {
                        return -1;
                    }
                    else {
                        return 1;
                    }
                }
                elseif ($a->$function() > $b->$function()) {
                    return -1;
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
            elseif ($type === null) {
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
     * Gets the list of electronic gradeables that the current user has access to submit for. Admins have
     * access to all gradeables, while graders have access to all gradeables that have the TA view date
     * having passed, and then students are only allowed to submit to gradeables past its open date.
     *
     * @return Gradeable[]
     */
    public function getSubmittableElectronicGradeables() {
        $return = [];
        foreach ($this->gradeables as $id => $gradeable) {
            if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
                continue;
            }
            if (
                $this->core->getUser()->accessAdmin()
                || ($gradeable->getTaViewStartDate() <= $this->now && $this->core->getUser()->accessGrading())
                || $gradeable->getSubmissionOpenDate() <= $this->now
            ) {
                $return[$id] = $gradeable;
            }
        }
        return $return;
    }

    /**
     * @return array<int, Gradeable[]>
     */
    public function getGradeablesBySection(): array {
        return [
            self::FUTURE => $this->getFutureGradeables(),
            self::BETA => $this->getBetaGradeables(),
            self::OPEN => $this->getOpenGradeables(),
            self::CLOSED => $this->getClosedGradeables(),
            self::GRADING => $this->getGradingGradeables(),
            self::GRADED => $this->getGradedGradeables()
        ];
    }

    /**
     * A static factory method to create a new GradeableList object that contains
     * all gradeables in all courses of a single user.
     * The method loads from the database of all courses and get all gradeables information.
     * Only load once unless the user refreshes the page.
     *
     * @param Core $core
     * @param User $user The user to filter gradeables by
     * @return GradeableList
     * @throws \Exception if a Gradeable failed to load from the database
     */
    public static function getAllGradeableListFromUserId(Core $core, User $user): GradeableList {
        $gradeables = [];
        // Load the gradeable information for each course
        $courses = $core->getQueries()->getCourseForUserId($user->getId());
        foreach ($courses as $course) {
            /** @var \app\models\Course $course */
            $core->loadCourseConfig($course->getSemester(), $course->getTitle());
            $core->loadCourseDatabase();
            foreach ($core->getQueries()->getGradeableConfigs(null) as $gradeable) {
                /** @var Gradeable $gradeable */
                $gradeables[serialize([$course->getSemester(), $course->getTitle(), $gradeable->getId()])] = $gradeable;
            }
            $core->getCourseDB()->disconnect();
        }

        $core->getConfig()->setCourseLoaded(false);
        return new GradeableList($core, $user, $gradeables);
    }
}
