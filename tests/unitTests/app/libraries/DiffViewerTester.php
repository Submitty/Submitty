<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\DiffViewer;

class DiffViewerTester extends \PHPUnit_Framework_TestCase {

    /**
     * Get all of the various diff test cases we have, ensuring that we only take in a folder that has
     * necessary four files to have the test fixture be properly run
     *
     * @return array
     */
    public function diffDir() {
        $needed_files = array('input_actual.txt', 'input_expected.txt',
            'input_differences.json', 'output_actual.txt', 'output_expected.txt');
        $dir = __TEST_DATA__.'/diffs';
        $files = scandir($dir);
        $diffs = array();
        foreach ($files as $file) {
            if (is_dir($dir."/".$file) && strpos($file, '.') === false) {
                foreach($needed_files as $needed_file) {
                    if (!file_exists($dir."/".$file."/".$needed_file)) {
                        continue 2;
                    }
                }
                $diffs[] = array($dir."/".$file);
            }
        }
        return $diffs;
    }

    /**
     * @param $diffDir
     *
     * @dataProvider diffDir
     */
    public function testDiffViewer($diffDir) {
        $diff = new DiffViewer("{$diffDir}/input_actual.txt", "{$diffDir}/input_expected.txt", "{$diffDir}/input_differences.json", "");
        $this->assertStringEqualsFile($diffDir."/output_actual.txt", $diff->getDisplayActual());
        $this->assertStringEqualsFile($diffDir."/output_expected.txt", $diff->getDisplayExpected());
        $this->assertTrue($diff->existsDifference());
    }

    /**
     * @expectedException \Exception
     */
    public function testActualException() {
        $diff = new DiffViewer("file_that_doesnt_exist", "", "", "");
        $diff->buildViewer();
    }

    /**
     * @expectedException \Exception
     */
    public function testExpectedException() {
        $diff = new DiffViewer(__TEST_DATA__."/diffs/diff_test_01/input_actual.txt",
                               "file_that_doesnt_exist", "", "");
        $diff->buildViewer();
    }

    /**
     * @expectedException \Exception
     */
    public function testDifferencesException() {
        $diff = new DiffViewer(__TEST_DATA__."/diffs/diff_test_01/input_actual.txt",
                               __TEST_DATA__."/diffs/diff_test_01/input_expected.txt",
                               "file_that_doesnt_exist", "");
        $diff->buildViewer();
    }
}
