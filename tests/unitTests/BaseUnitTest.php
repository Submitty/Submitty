<?php

namespace tests\unitTests;

use app\libraries\Core;
use app\libraries\database\IDatabaseQueries;
use app\libraries\Output;
use app\models\Config;
use app\models\User;

class BaseUnitTest extends \PHPUnit_Framework_TestCase {

    /**
     * Creates a mocked the Core object predefining things with known values so that we don't have to do this
     * repeatidly for a variety of tests. This
     *
     * @param $config_values
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockCore($config_values=array(), $user_config=array()) {
        $core = $this->createMock(Core::class);

        $config = $this->createMock(Config::class);
        if (isset($config_values['semester'])) {
            $config->method('getSemester')->willReturn($config_values['semester']);
        }

        if (isset($config_values['course'])) {
            $config->method('getCourse')->willReturn($config_values['course']);
        }

        if (isset($config_values['tmp_path'])) {
            $config->method('getSubmittyPath')->willReturn($config_values['tmp_path']);
        }

        if (isset($config_values['course_path'])) {
            $config->method('getCoursePath')->willReturn($config_values['course_path']);
        }

        $config->method('getTimezone')->willReturn("America/New_York");

        $core->method('getConfig')->willReturn($config);

        if (isset($config_values['csrf_token'])) {
            $core->method('checkCsrfToken')->willReturn($config_values['csrf_token'] === true);
        }
        else {
            $core->method('checkCsrfToken')->willReturn(true);
        }
        if (isset($config_values['testing'])) {
            $core->method('isTesting')->willReturn($config_values['testing'] === true);
        }
        else {
            $core->method('isTesting')->willReturn(true);
        }


        $queries = $this->createMock(IDatabaseQueries::class);
        $core->method('getQueries')->willReturn($queries);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn("testUser");
        if (isset($user_config['access_grading'])) {
            $user->method('accessGrading')->willReturn($user_config['access_grading'] == true);
        }
        else {
            $user->method('accessGrading')->willReturn(false);
        }

        $core->method('getUser')->willReturn($user);

        $output = $this->createMock(Output::class);
        $core->method('getOutput')->willReturn($output);

        return $core;
    }

    /**
     * This copies the createMock() function from PHPUnit as it was added in 5.4 which is not available to PHP 5.5.
     * @TODO: Remove this once we drop official support for PHP 5.5
     *
     * @param string $originalClassName
     * @return \PHPUnit_Framework_MockObject_MockObject
     * @throws \PHPUnit_Framework_Exception
     */
    public function createMock($originalClassName) {
        if (version_compare("5.6.0", PHP_VERSION, "lt")) {
            return $this->getMockBuilder($originalClassName)
                ->disableOriginalConstructor()
                ->disableOriginalClone()
                ->disableArgumentCloning()
                ->disallowMockingUnknownTypes()
                ->getMock();
        }
        else {
            return $this->getMockBuilder($originalClassName)
                ->disableOriginalConstructor()
                ->disableOriginalClone()
                ->disableArgumentCloning()
                ->getMock();
        }
    }
}