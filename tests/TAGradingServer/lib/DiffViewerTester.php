<?php

namespace tests;

use lib\DiffViewer;

class DiffViewerTester extends \PHPUnit_Framework_TestCase {

    public function diffDir() {
        $dir = __DIR__.'/data/diffs';
        $files = scandir($dir);
        $diffs = array();
        foreach ($files as $file) {
            if (is_dir($dir."/".$file) && strpos($file, '.') === false) {
                $diffs[] = array($dir."/".$file);
            }
        }
        return $diffs;
    }

    /**
     * @param $diffDir:
     *
     * @dataProvider diffDir
     */
    public function testDiffViewer($diffDir) {
        $this->markTestSkipped();
        $diff = new DiffViewer();
        $diff->load("{$diffDir}/input_actual.txt", "{$diffDir}/input_expected.txt", "{$diffDir}/input_differences.json");
        $this->assertStringEqualsFile($diffDir."/output_actual.txt", $diff->getDisplayActual());
        $this->assertStringEqualsFile($diffDir."/output_expected.txt", $diff->getDisplayExpected());
    }

    /**
     * @expectedException \Exception
     */
    public function testActualException() {
        $diff = new DiffViewer();
        $diff->load("file_that_doesnt_exist", "", "");
    }

    /**
     * @expectedException \Exception
     */
    public function testExpectedException() {
        $diff = new DiffViewer();
        $diff->load(__DIR__."/data/diff_test_01/input_actual.txt", 
                    "file_that_doesnt_exist", "");
    }

    /**
     * @expectedException \Exception
     */
    public function testDifferencesException() {
        $diff = new DiffViewer();
        $diff->load(__DIR__."/data/diff_test_01/input_actual.txt", 
                    __DIR__."/data/diff_test_01/input_expected.txt", 
                    "file_that_doesnt_exist");
    }
}