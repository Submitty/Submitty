<?php

namespace tests\unitTests;

use app\libraries\Core;
use app\libraries\Output;
use app\models\Config;

class BaseUnitTest extends \PHPUnit_Framework_TestCase {

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function mockCore() {
        $core = $this->createMock(Core::class);

        $config = $this->createMock(Config::class);
        $config->method('getCoursePath')->willReturn("aaa");
        $core->method('getConfig')->willReturn($config);
        $core->method('checkCsrfToken')->willReturn(true);

        $output = $this->createMock(Output::class);
        $core->method('getOutput')->willReturn($output);

        return $core;
    }
}