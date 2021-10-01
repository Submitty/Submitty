<?php

declare(strict_types=1);

namespace tests\app\entities\plagiarism;

use app\entities\plagiarism\PlagiarismConfig;
use app\exceptions\ValidationException;
use Exception;
use Doctrine\ORM\Mapping as ORM;
use app\libraries\plagiarism\PlagiarismUtils;
use tests\BaseUnitTest;

class PlagiarismConfigTester extends BaseUnitTest {

    private $my_config;

    public function setUp(): void {
        $this->my_config = new PlagiarismConfig(
            "homework_1",
            1,
            "all_versions",
            ["*.cpp"],
            true,
            false,
            false,
            "plaintext",
            20,
            10,
            [],
            ["ta", "ta2"]
        );
    }

    public function tearDown(): void {
    }

    public function testGetters(): void {
        $this->assertEquals($this->my_config->getGradeableID(), "homework_1");
        $this->assertEquals($this->my_config->getConfigID(), 1);
        $this->assertEquals($this->my_config->getVersionStatus(), "all_versions");
        $this->assertEquals($this->my_config->getRegexArray(), ["*.cpp"]);
        $this->assertEquals($this->my_config->isRegexDirSubmissionsSelected(), true);
        $this->assertEquals($this->my_config->isRegexDirResultsSelected(), false);
        $this->assertEquals($this->my_config->isRegexDirCheckoutSelected(), false);
        $this->assertEquals($this->my_config->getLanguage(), "plaintext");
        $this->assertEquals($this->my_config->getThreshold(), 20);
        $this->assertEquals($this->my_config->getSequenceLength(), 10);
        $this->assertEquals($this->my_config->getOtherGradeables(), []);
        $this->assertEquals($this->my_config->getIgnoredSubmissions(), ["ta", "ta2"]);
    }

    public function testSetters(): void {
        // version status
        $this->my_config->setVersionStatus("active_version");
        $this->assertEquals($this->my_config->getVersionStatus(), "active_version");
        $exception_thrown = false;
        try {
            $this->my_config->setVersionStatus("latest_version");
        }
        catch (Exception $e) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->assertEquals($this->my_config->getVersionStatus(), "active_version");

        // regex array
        $this->my_config->setRegexArray(["foo.txt", "*_3.cpp"]);
        $this->assertEquals($this->my_config->getRegexArray(), ["foo.txt", "*_3.cpp"]);
        $exception_thrown = false;
        try {
            $this->my_config->setRegexArray(["foo\..\secret_file.txt", "*_3.cpp"]);
        }
        catch (Exception $e) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->assertEquals($this->my_config->getRegexArray(), ["foo.txt", "*_3.cpp"]);

        // submissions dir
        $this->my_config->setRegexDirSubmissions(false);
        $this->assertFalse($this->my_config->isRegexDirSubmissionsSelected());

        // results dir
        $this->my_config->setRegexDirResults(true);
        $this->assertTrue($this->my_config->isRegexDirResultsSelected());

        // checkout dir
        $this->my_config->setRegexDirCheckout(true);
        $this->assertTrue($this->my_config->isRegexDirCheckoutSelected());

        // language
        $this->my_config->setLanguage("python");
        $this->assertEquals($this->my_config->getLanguage(), "python");
        $exception_thrown = false;
        try {
            $this->my_config->setLanguage("swift");
        }
        catch (Exception $e) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->assertEquals($this->my_config->getLanguage(), "python");

        // threshold
        $this->my_config->setThreshold(25);
        $this->assertEquals($this->my_config->getThreshold(), 25);
        $exception_thrown = false;
        try {
            $this->my_config->setThreshold(-5);
        }
        catch (Exception $e) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->assertEquals($this->my_config->getThreshold(), 25);

        // sequence length
        $this->my_config->setSequenceLength(7);
        $this->assertEquals($this->my_config->getSequenceLength(), 7);
        $exception_thrown = false;
        try {
            $this->my_config->setSequenceLength(-3);
        }
        catch (Exception $e) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);
        $this->assertEquals($this->my_config->getSequenceLength(), 7);

        // other gradeables
        $this->my_config->setOtherGradeables([
            "prior_semester" => "f16",
            "prior_course" => "sample",
            "prior_gradeable" => "example_gradeable"
        ]);
        $this->assertEquals($this->my_config->getOtherGradeables(), [
            "prior_semester" => "f16",
            "prior_course" => "sample",
            "prior_gradeable" => "example_gradeable"
        ]);

        // ignored submissions
        $this->my_config->setIgnoredSubmissions([]);
        $this->assertEquals($this->my_config->getIgnoredSubmissions(), []);
    }
}
