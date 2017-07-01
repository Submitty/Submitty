<?php

namespace tests\unitTests;

use app\libraries\Core;
use app\libraries\database\AbstractDatabaseQueries;
use app\libraries\GradeableType;
use app\libraries\Output;
use app\libraries\Utils;
use app\models\Config;
use app\models\Gradeable;
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

        $config = $this->createMockModel(Config::class);
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

        $config->method('getTimezone')->willReturn(new \DateTimeZone("America/New_York"));

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

        $queries = $this->createMock(AbstractDatabaseQueries::class);
        $core->method('getQueries')->willReturn($queries);

        $user = $this->createMockModel(User::class);
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
     * Utilty function that helps us mock Models from our system. Because they rely on the magic __call() function,
     * we cannot directly mock these as we would any other object as then we won't have access to any methods that
     * require the __call() magic function. However, PHPUnit allows us to specify functions that are mockable (even
     * if they are not directly defined) via setMethods(), so we use reflection on our given class to get all methods
     * that are documented in the PHPDoc via the "@method" tag, and then also the defined functions. Kind of hacky,
     * and does slow testing down some, but it's easier than having to manually update a list of needed functions
     * when mocking these things.
     *
     * @param string $class
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function createMockModel($class) {
        $builder = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes();

        $reflection = new \ReflectionClass($class);
        $methods = array();
        $reflection->getDocComment();
        $matches = array();
        preg_match_all("/@method.* (.*)\(.*\)/", $reflection->getDocComment(), $matches);
        foreach ($matches[1] as $match) {
            $methods[] = $match;
        }
        foreach ($reflection->getMethods() as $method) {
            if (!Utils::startsWith($method->getName(), "__")) {
                $methods[] = $method->getName();
            }
        }
        $builder->setMethods(array_unique($methods));
        return $builder->getMock();
    }
}
