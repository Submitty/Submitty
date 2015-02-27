<?php

class DataFunctionsTest extends PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        //print is_dir("public/controller/");
        require_once "public/controller/data_functions.php";
    }

    public function testIsValidSemester() {
        $this->assertTrue(is_valid_semester("s15"), "s15 should be a valid semester");
    }
}