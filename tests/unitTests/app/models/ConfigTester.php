<?php

namespace unitTests\app\models;


class ConfigTester extends \PHPUnit_Framework_TestCase {
    public function testClassProperties() {
        $class = new \ReflectionClass('app\models\Config');
        $properties = $class->getDefaultProperties();
        $this->assertFalse($properties['debug']);
    }
}