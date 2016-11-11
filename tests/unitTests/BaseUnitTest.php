<?php

namespace tests\unitTests;

use app\libraries\Core;
use app\libraries\database\IDatabaseQueries;
use app\libraries\Output;
use app\libraries\Utils;
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
    protected function mockCore($config_values=array()) {
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
        $core->method('checkCsrfToken')->willReturn(true);

        $queries = $this->createMock(IDatabaseQueries::class);
        $core->method('getQueries')->willReturn($queries);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn("testUser");
        $core->method('getUser')->willReturn($user);

        $output = $this->createMock(Output::class);
        $core->method('getOutput')->willReturn($output);

        return $core;
    }
}