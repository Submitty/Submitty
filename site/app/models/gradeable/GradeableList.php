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
 * @method Gradeable[] getGradeables()
 * @method Gradeable[] getFutureGradeables()
 * @method Gradeable[] getBetaGradeables()
 * @method Gradeable[] getOpenGradeables()
 * @method Gradeable[] getClosedGradeables()
 * @method Gradeable[] getGradingGradeables()
 * @method Gradeable[] getGradedGradeables()
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

    /** @prop @var Gradeable[]  */
    protected $gradeables = array();

    /*
     * All elements of $this->gradeables should fall into one of the following six lists. There should
     * be no overlap between them.
     */
    /** @prop @var Gradeable[] These are gradeables which are only viewable by admin users */
    protected $future_gradeables = array();
    /** @prop @var Gradeable[] These are gradeables which are only viewable by admin & TA users */
    protected $beta_gradeables = array();
    /** @prop @var Gradeable[] */
    protected $open_gradeables = array();
    /** @prop @var Gradeable[] */
    protected $closed_gradeables = array();
    /** @prop @var Gradeable[] */
    protected $grading_gradeables = array();
    /** @prop @var Gradeable[] */
    protected $graded_gradeables = array();

    /** @var \DateTime Timestamp of when we initially loaded the GradeableList so that all timestamp comparisons are
     against the same time (and don't have any potential mismatch of seconds */
    protected $now;

    /**
     * GradeableList constructor.
     *
     * @param Core $core
     * @param User $user The user to filter gradeables by (null if current user)
     * @throws \Exception if a Gradeable failed to load from the database
     */
    public function __construct(Core $core, User $user = null) {
        parent::__construct($core);
        $this->user = ($user === null) ? $this->core->getUser() : $user;
        foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
            $this->gradeables[$gradeable->getId()] = $gradeable;
        }

        $this->now = $this->core->getDateTimeNow();

        $submitter = new Submitter($core, $this->core->getUser());
        foreach ($this->gradeables as $gradeable) {
            /** @var Gradeable $gradeable */
            if ($gradeable->getGradeReleasedDate() <= $this->now) {
                $this->graded_gradeables[$gradeable->getId()] = $gradeable;
            }
            elseif ($gradeable->getType() === GradeableType::ELECTRONIC_FILE && !$gradeable->hasDueDate()) {
                // Filter out gradeables with no due date
                if ($gradeable->isStudentSubmit()) {
                    if ($gradeable->getGradeStartDate() < $this->core->getDateTimeNow() && $this->core->getUser()->accessGrading()) {
                        // Put in 'grading' category only if user is a grader
                        $this->grading_gradeables[$gradeable->getId()] = $gradeable;
                    }
                    else {
                        $this->open_gradeables[$gradeable->getId()] = $gradeable;
                    }
                }
                else {
                    // If there is no due date and no student submission, it should
                    //  automatically show up in the 'Grading' category
                    $this->grading_gradeables[$gradeable->getId()] = $gradeable;
                }
            }
            elseif (
                (
                    ($gradeable->getType() === GradeableType::ELECTRONIC_FILE && $gradeable->isTaGrading())
                    || $gradeable->getType() !== GradeableType::ELECTRONIC_FILE
                )
                && $gradeable->getGradeStartDate() <= $this->now
            ) {
                $this->grading_gradeables[$gradeable->getId()] = $gradeable;
            }
            elseif (
                $gradeable->getType() === GradeableType::ELECTRONIC_FILE
                && $gradeable->getSubmissionOpenDate() <= $this->now
                && $gradeable->getSubmissionDueDate() <= $this->now
            ) {
                $this->closed_gradeables[$gradeable->getId()] = $gradeable;
            }
            elseif (
                $gradeable->getType() === GradeableType::ELECTRONIC_FILE
                && $gradeable->getSubmissionOpenDate() <= $this->now
                && $gradeable->getTaViewStartDate() <= $this->now
            ) {
                $this->open_gradeables[$gradeable->getId()] = $gradeable;
            }
            elseif ($this->core->getUser()->accessGrading() && $gradeable->getTaViewStartDate() <= $this->now) {
                $this->beta_gradeables[$gradeable->getId()] = $gradeable;
            }
            elseif ($this->core->getUser()->accessAdmin()) {
                $this->future_gradeables[$gradeable->getId()] = $gradeable;
            }
        }
        $sort_array = array(
            'future_gradeables' => 'getGradeStartDate',
            'beta_gradeables' => 'getGradeStartDate',
            'open_gradeables' => 'getSubmissionDueDate',
            'closed_gradeables' => 'getSubmissionDueDate',
            'grading_gradeables' => 'getGradeStartDate',
            'graded_gradeables' => 'getGradeReleasedDate'
        );
        foreach ($sort_array as $list => $function) {
            uasort($this->$list, function (Gradeable $a, Gradeable $b) use ($function) {
                if ($a->$function() == $b->$function()) {
                    if ($a->getId() < $b->getId()) {
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
        $return = array();
        foreach ($this->gradeables as $gradeable) {
            if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
                continue;
            }
            if (
                $this->core->getUser()->accessAdmin()
                || ($gradeable->getTaViewStartDate() <= $this->now && $this->core->getUser()->accessGrading())
                || $gradeable->getSubmissionOpenDate() <= $this->now
            ) {
                $return[$gradeable->getId()] = $gradeable;
            }
        }
        return $return;
    }
}
