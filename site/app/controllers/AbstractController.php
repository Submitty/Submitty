<?php

namespace app\controllers;

use app\libraries\Core;
use app\models\CourseMaterial;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\AutoGradedTestcase;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Component;
use app\models\gradeable\Gradeable;
use app\models\gradeable\Mark;

abstract class AbstractController {

    /** @var Core  */
    protected $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    /*
     *  Below are methods that try to fetch model objects from request parameters and render JSEND responses
     *      in the failure/error cases if requested in addition to returning false.  HTML-returning routes
     *      should set the `$render_json` parameter to `false`.  The results of these methods should
     *      be strict-type-checked against `false`.  These methods should be called instead of database
     *      query methods for the entire controller layer
     *
     *  TODO: support more than just json responses (i.e. adding error messages to a redirect)
     */

    /**
     * Gets a gradeable config from its id.
     * @param string $gradeable_id
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return Gradeable|bool false in the fail/error case
     */
    protected function tryGetGradeable(string $gradeable_id, bool $render_json = true) {
        if ($gradeable_id === '') {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Missing gradeable_id parameter');
            }
            return false;
        }

        // Get the gradeable
        try {
            return $this->core->getQueries()->getGradeableConfig($gradeable_id);
        }
        catch (\InvalidArgumentException $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid gradeable_id parameter');
            }
        }
        catch (\Exception $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonError('Failed to load gradeable');
            }
        }
        return false;
    }

    /**
     * Gets a gradeable component from its id and a gradeable
     * @param Gradeable $gradeable
     * @param string $component_id
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return Component|bool false in the fail/error case
     */
    protected function tryGetComponent(Gradeable $gradeable, string $component_id, bool $render_json = true) {
        if ($component_id === '') {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Missing component_id parameter');
            }
            return false;
        }
        if (!ctype_digit($component_id)) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid component_id parameter');
            }
            return false;
        }
        $component_id = intval($component_id);
        try {
            return $gradeable->getComponent($component_id);
        }
        catch (\InvalidArgumentException $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid component_id for this gradeable');
            }
            return false;
        }
    }

    /**
     * Gets a mark from its id and a component
     * @param Component $component
     * @param string $mark_id
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return Mark|bool false in the fail/error case
     */
    protected function tryGetMark(Component $component, string $mark_id, bool $render_json = true) {
        if ($mark_id === '') {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Missing mark_id parameter');
            }
            return false;
        }
        if (!ctype_digit($mark_id)) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid mark_id parameter');
            }
            return false;
        }
        $mark_id = intval($mark_id);
        try {
            return $component->getMark($mark_id);
        }
        catch (\InvalidArgumentException $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid mark_id for this component');
            }
            return false;
        }
    }

    /**
     * Gets a submitter id from an anon id
     * @param string $anon_id
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return string|bool false in the fail/error case
     */
    protected function tryGetSubmitterIdFromAnonId(string $anon_id, bool $render_json = true) {
        if ($anon_id === '') {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Missing anon_id parameter');
            }
            return false;
        }

        try {
            $submitter_id = $this->core->getQueries()->getSubmitterIdFromAnonId($anon_id);
            if ($submitter_id === null) {
                if ($render_json) {
                    $this->core->getOutput()->renderJsonFail('Invalid anon_id parameter');
                }
                return false;
            }
            return $submitter_id;
        }
        catch (\Exception $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonError('Error getting user id from anon_id parameter');
            }
        }
        return false;
    }

    /**
     * Gets a graded gradeable for a given gradeable and submitter id
     * @param Gradeable $gradeable
     * @param string $submitter_id
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return \app\models\gradeable\GradedGradeable|bool false in the fail/error case
     */
    protected function tryGetGradedGradeable(Gradeable $gradeable, string $submitter_id, bool $render_json = true) {
        if ($submitter_id === '' || $submitter_id === null) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Must provide a who_id (user/team id) parameter');
            }
            return false;
        }
        try {
            $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $submitter_id, $submitter_id);
            if ($graded_gradeable === null) {
                if ($render_json) {
                    $this->core->getOutput()->renderJsonFail('User not on a team!');
                }
                return false;
            }
            return $graded_gradeable;
        }
        catch (\Exception $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonError('Failed to load Gradeable grade');
            }
        }
        return false;
    }

    /**
     * Gets a submission version for a given auto graded gradeable and version number
     * @param AutoGradedGradeable $auto_graded_gradeable
     * @param string $version
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return AutoGradedVersion|bool false in the fail/error case
     */
    protected function tryGetVersion(AutoGradedGradeable $auto_graded_gradeable, string $version, bool $render_json = true) {
        if ($version !== '') {
            $version = intval($version);
            $version_instance = $auto_graded_gradeable->getAutoGradedVersions()[$version] ?? null;
            if ($version_instance === null) {
                if ($render_json) {
                    $this->core->getOutput()->renderJsonFail('Invalid gradeable version');
                }
                return false;
            }
        }
        else {
            $version_instance = $auto_graded_gradeable->getActiveVersionInstance();
            if ($version_instance === null) {
                if ($render_json) {
                    $this->core->getOutput()->renderJsonFail('No version instance specified and no active version');
                }
                return false;
            }
        }
        return $version_instance;
    }

    /**
     * Gets a testcase for a given version and testcase index
     * @param AutoGradedVersion $version
     * @param string $testcase_index
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return AutoGradedTestcase|bool false in the fail/error case
     */
    protected function tryGetTestcase(AutoGradedVersion $version, string $testcase_index, bool $render_json = true) {
        if ($testcase_index === '') {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Must provide an index parameter');
            }
            return false;
        }
        if (!ctype_digit($testcase_index)) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('index parameter must be a non-negative integer');
            }
            return false;
        }
        $testcase_index = intval($testcase_index);
        $testcase = $version->getTestcases()[$testcase_index] ?? null;
        if ($testcase === null) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid testcase index');
            }
            return false;
        }
        return $testcase;
    }

    /**
     * Gets an autocheck for a given testcase and autocheck index
     * @param AutoGradedTestcase $testcase
     * @param string $autocheck_index
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return \app\models\GradeableAutocheck|bool false in the fail/error case
     */
    protected function tryGetAutocheck(AutoGradedTestcase $testcase, string $autocheck_index, bool $render_json = true) {
        if ($autocheck_index === '') {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Must provide an autocheck index parameter');
            }
            return false;
        }
        if (!ctype_digit($autocheck_index)) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('autocheck index parameter must be a non-negative integer');
            }
            return false;
        }
        $autocheck_index = intval($autocheck_index);
        try {
            return $testcase->getAutocheck($autocheck_index);
        }
        catch (\InvalidArgumentException $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid autocheck index parameter');
            }
            return false;
        }
    }

    /**
     * Gets a course material from its path.
     * @param string $path
     * @param bool $render_json true to render a JSEND response to the output in the failure/error case
     * @return CourseMaterial|bool false in the fail/error case
     */
    public function tryGetCourseMaterial(string $path, bool $render_json = true) {
        if ($path === '') {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Missing path parameter');
            }
            return false;
        }

        // Get the gradeable
        try {
            return $this->core->getQueries()->getCourseMaterial($path);
        }
        catch (\InvalidArgumentException $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonFail('Invalid path parameter');
            }
        }
        catch (\Exception $e) {
            if ($render_json) {
                $this->core->getOutput()->renderJsonError('Failed to load gradeable');
            }
        }
        return false;
    }
}
