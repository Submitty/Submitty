<?php

declare(strict_types=1);

namespace tests\app\entities\plagiarism;

use app\entities\plagiarism\PlagiarismConfig;
use app\exceptions\ValidationException;
use app\exceptions\FileNotFoundException;
use app\libraries\DateUtils;
use tests\BaseUnitTest;

class PlagiarismConfigTester extends BaseUnitTest {
    private $my_config;

    public function setUp(): void {
        DateUtils::setTimezone(new \DateTimeZone("America/New_York"));

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
            [],
            4,
            ["ta", "ta2"]
        );
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
        $this->assertEquals($this->my_config->getHashSize(), 10);
        $this->assertEquals($this->my_config->getOtherGradeables(), []);
        $this->assertEquals($this->my_config->getIgnoredSubmissions(), ["ta", "ta2"]);
    }

    public function testSetVersionStatus() {
        // Accurate
        $this->my_config->setVersionStatus("active_version");
        $this->assertEquals($this->my_config->getVersionStatus(), "active_version");
        // Exception
        $this->expectException(ValidationException::class);
        $this->my_config->setVersionStatus("latest_version");
        $this->assertEquals($this->my_config->getVersionStatus(), "active_version");
    }

    public function testSetRegexArray() {
        // Accurate
        $this->my_config->setRegexArray(["foo.txt", "*_3.cpp"]);
        $this->assertEquals($this->my_config->getRegexArray(), ["foo.txt", "*_3.cpp"]);
        // Exception
        $this->expectException(ValidationException::class);
        $this->my_config->setRegexArray(["foo\..\secret_file.txt", "*_3.cpp"]);
        $this->assertEquals($this->my_config->getRegexArray(), ["foo.txt", "*_3.cpp"]);
    }

    public function testSetLanguage() {
        // Accurate
        $this->my_config->setLanguage("python");
        $this->assertEquals($this->my_config->getLanguage(), "python");
        // Exception
        $this->expectException(ValidationException::class);
        $this->my_config->setLanguage("swift");
        $this->assertEquals($this->my_config->getLanguage(), "python");
    }

    public function testSetThreshhold() {
        // Accurate
        $this->my_config->setThreshold(25);
        $this->assertEquals($this->my_config->getThreshold(), 25);
        // Exception
        $this->expectException(ValidationException::class);
        $this->my_config->setThreshold(-5);
        $this->assertEquals($this->my_config->getThreshold(), 25);
    }

    public function testSetHashSize() {
        $this->my_config->setHashSize(7);
        $this->assertEquals($this->my_config->getHashSize(), 7);
        // Exception
        $this->expectException(ValidationException::class);
        $this->my_config->setHashSize(-3);
        $this->assertEquals($this->my_config->getHashSize(), 7);
    }

    public function testSetOtherGradeables() {
        $this->my_config->setOtherGradeables([
            "other_semester" => "f16",
            "other_course" => "sample",
            "other_gradeable" => "example_gradeable"
        ]);
        $this->assertEquals($this->my_config->getOtherGradeables(), [
            "other_semester" => "f16",
            "other_course" => "sample",
            "other_gradeable" => "example_gradeable"
        ]);
    }

    public function testSetOtherGradeablePaths() {
        // Accurate
        $this->assertFalse($this->my_config->hasOtherGradeablePaths());
        // Exception
        $this->expectException(FileNotFoundException::class);
        $this->my_config->setOtherGradeablePaths(["/var/local/submitty/courses/f17/test_course/hw1","/my_documents/hw1"], 10);
        $this->assertFalse($this->my_config->hasOtherGradeablePaths());
        $this->assertEquals($this->my_config->getOtherGradeablePaths(), []);
    }

    public function testSetRegexDirs() {
        // submissions dir
        $this->my_config->setRegexDirSubmissions(false);
        $this->assertFalse($this->my_config->isRegexDirSubmissionsSelected());

        // results dir
        $this->my_config->setRegexDirResults(true);
        $this->assertTrue($this->my_config->isRegexDirResultsSelected());

        // checkout dir
        $this->my_config->setRegexDirCheckout(true);
        $this->assertTrue($this->my_config->isRegexDirCheckoutSelected());
    }

    public function testSetIgnoredPermissions() {
        $this->my_config->setIgnoredSubmissions([]);
        $this->assertEquals($this->my_config->getIgnoredSubmissions(), []);
    }
}
