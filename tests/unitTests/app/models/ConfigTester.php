<?php

namespace tests\unitTests\app\models;

use app\exceptions\ConfigException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\IniParser;
use app\libraries\Utils;
use app\models\Config;

class ConfigTester extends \PHPUnit_Framework_TestCase {
    private $core;

    private $temp_dir = null;
    private $master = null;

    public function setUp() {
        $this->core = $this->createMock(Core::class);
    }

    public function tearDown() {
        if ($this->temp_dir !== null && is_dir($this->temp_dir)) {
            FileUtils::recursiveRmdir($this->temp_dir);
        }
    }
    
    /**
     * This test ensures that the default value of the DEBUG flag within the config model is always false. This
     * means that if the value is not found within the ini file, we don't have to worry about accidently
     * exposing things to students.
     */
    public function testClassProperties() {
        $class = new \ReflectionClass('app\models\Config');
        $properties = $class->getDefaultProperties();
        $this->assertFalse($properties['debug']);
    }

    private function createConfigFile($extra = array()) {
        $this->temp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->temp_dir);
        $course_path = FileUtils::joinPaths($this->temp_dir, "courses", "s17", "csci0000");
        $log_path = FileUtils::joinPaths($this->temp_dir, "logs");
        FileUtils::createDir($course_path, 0777, true);
        FileUtils::createDir(FileUtils::joinPaths($course_path, "config"));
        FileUtils::createDir($log_path);
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'access'));
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'site_errors'));
        $this->master = FileUtils::joinPaths($this->temp_dir,  "master.ini");
        $course = FileUtils::joinPaths($course_path, "config", "config.ini");
        $config = array(
            'site_details' => array(
                'base_url' => "http://example.com",
                'cgi_url' => "http://example.com/cgi",
                'ta_base_url' => "http://example.com/ta",
                'submitty_path' => $this->temp_dir,
                'authentication' => "PamAuthentication",
                'timezone' => "America/Chicago",
            ),
            'logging_details' => array(
                'submitty_log_path' => $log_path,
                'log_exceptions' => true,
            ),
            'database_details' => array(
                'host' => 'db_host',
                'username' => 'db_user',
                'password' => 'db_pass'
            ),
            'submitty_database_details' => array(
                'dbname' => 'submitty'
            )
        );

        $config = array_replace_recursive($config, $extra);
        IniParser::writeFile($this->master, $config);

        $config = array(
            'database_details' => array(
                'dbname' => 'submitty_s17_csci0000'
            ),
            'course_details' => array(
                'course_name' => 'Test Course',
                'course_home_url' => '',
                'default_hw_late_days' => 2,
                'default_student_late_days' => 3,
                'zero_rubric_grades' => false,
                'upload_message' => "",
                'keep_previous_files' => false,
                'display_rainbow_grades_summary' => false,
                'display_custom_message' => false,
                'course_email' => 'Please contact your TA or instructor for a regrade request.',
                'vcs_base_url' => '',
                'vcs_type' => 'git'
            )
        );

        $config = array_replace_recursive($config, $extra);
        IniParser::writeFile($course, $config);
    }

    public function testConfig() {
        $this->createConfigFile();

        $config = new Config($this->core, "s17", "csci0000", $this->master);

        $this->assertFalse($config->isDebug());
        $this->assertEquals("s17", $config->getSemester());
        $this->assertEquals("csci0000", $config->getCourse());
        $this->assertEquals("http://example.com/", $config->getBaseUrl());
        $this->assertEquals("http://example.com/ta/", $config->getTaBaseUrl());
        $this->assertEquals("http://example.com/cgi/", $config->getCgiUrl());
        $this->assertEquals("http://example.com/index.php?semester=s17&course=csci0000", $config->getSiteUrl());
        $this->assertEquals($this->temp_dir, $config->getSubmittyPath());
        $this->assertEquals($this->temp_dir."/courses/s17/csci0000", $config->getCoursePath());
        $this->assertEquals($this->temp_dir."/logs", $config->getLogPath());
        $this->assertTrue($config->shouldLogExceptions());
        $this->assertEquals("pgsql", $config->getDatabaseDriver());
        $db_params = array(
            'host' => 'db_host',
            'username' => 'db_user',
            'password' => 'db_pass'
        );
        $this->assertEquals($db_params, $config->getDatabaseParams());
        $this->assertEquals(array_merge($db_params, array('dbname' => 'submitty')), $config->getSubmittyDatabaseParams());
        $this->assertEquals(array_merge($db_params, array('dbname' => 'submitty_s17_csci0000')), $config->getCourseDatabaseParams());
        $this->assertEquals("Test Course", $config->getCourseName());
        $this->assertEquals("", $config->getCourseHomeUrl());
        $this->assertEquals(2, $config->getDefaultHwLateDays());
        $this->assertEquals(3, $config->getDefaultStudentLateDays());
        $this->assertFalse($config->shouldZeroRubricGrades());
        $this->assertEquals($this->temp_dir, $config->getConfigPath());
        $this->assertEquals("PamAuthentication", $config->getAuthentication());
        $this->assertEquals("America/Chicago", $config->getTimezone()->getName());
        $this->assertEquals("", $config->getUploadMessage());
        $this->assertFalse($config->displayCustomMessage());
        $this->assertFalse($config->keepPreviousFiles());
        $this->assertFalse($config->displayRainbowGradesSummary());
        $this->assertEquals(FileUtils::joinPaths($this->temp_dir, "courses", "s17", "csci0000", "config", "config.ini"),
            $config->getCourseIniPath());

        $expected = array(
            'debug' => false,
            'semester' => 's17',
            'course' => 'csci0000',
            'base_url' => 'http://example.com/',
            'ta_base_url' => 'http://example.com/ta/',
            'cgi_url' => 'http://example.com/cgi/',
            'site_url' => 'http://example.com/index.php?semester=s17&course=csci0000',
            'submitty_path' => $this->temp_dir,
            'course_path' => $this->temp_dir.'/courses/s17/csci0000',
            'submitty_log_path' => $this->temp_dir.'/logs',
            'log_exceptions' => true,
            'database_driver' => 'pgsql',
            'database_params' => $db_params,
            'submitty_database_params' => array_merge($db_params, array('dbname' => 'submitty')),
            'course_database_params' => array_merge($db_params, array('dbname' => 'submitty_s17_csci0000')),
            'course_name' => 'Test Course',
            'config_path' => $this->temp_dir,
            'course_ini_path' => $this->temp_dir.'/courses/s17/csci0000/config/config.ini',
            'authentication' => 'PamAuthentication',
            'timezone' => 'DateTimeZone',
            'course_home_url' => '',
            'default_hw_late_days' => 2,
            'default_student_late_days' => 3,
            'zero_rubric_grades' => false,
            'upload_message' => '',
            'keep_previous_files' => false,
            'display_rainbow_grades_summary' => false,
            'display_custom_message' => false,
            'course_email' => 'Please contact your TA or instructor for a regrade request.',
            'vcs_base_url' => '',
            'vcs_type' => 'git',
            'modified' => false,
            'hidden_details' => null
        );
        $actual = $config->toArray();

        ksort($expected);
        ksort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function testHiddenCourseUrl() {
        $extra = array('hidden_details' => array('course_url' => 'http://example.com/course'));
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000", $this->master);
        $this->assertEquals("http://example.com/course/", $config->getBaseUrl());
        $this->assertEquals("http://example.com/course", $config->getHiddenDetails()['course_url']);
    }

    public function testHiddenTABaseUrl() {
        $extra = array('hidden_details' => array('ta_base_url' => 'http://example.com/hwgrading'));
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000", $this->master);
        $this->assertEquals("http://example.com/hwgrading/", $config->getTaBaseUrl());
        $this->assertEquals("http://example.com/hwgrading", $config->getHiddenDetails()['ta_base_url']);
    }

    public function testDefaultTimezone() {
        $extra = array('site_details' => array('timezone' => null));
        $this->createConfigFile($extra);
        $config = new Config($this->core, "s17", "csci0000", $this->master);
        $this->assertEquals("America/New_York", $config->getTimezone()->getName());
    }

    public function testDebugTrue() {
        $extra = array('site_details' => array('debug' => true));
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000", $this->master);
        $this->assertTrue($config->isDebug());
    }

    public function testDatabaseDriver() {
        $extra = array('database_details' => array('driver' => 'sqlite'));
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000", $this->master);
        $this->assertEquals("sqlite", $config->getDatabaseDriver());
    }

    public function getRequiredSections() {
        return array(
            array('site_details'),
            array('logging_details'),
            array('database_details'),
            array('submitty_database_details'),
            array('course_details')
        );
    }

    /**
     * @dataProvider getRequiredSections
     *
     * @param string $section
     */
    public function testMissingSections($section) {
        try {
            $extra = array($section => null);
            $this->createConfigFile($extra);
    
            new Config($this->core, "s17", "csci0000", $this->master);
            $this->fail("Should have thrown ConfigException");
        }
        catch (ConfigException $exception) {
            $this->assertEquals("Missing config section {$section} in ini file", $exception->getMessage());
        }
    }

    public function getRequiredSettings() {
        $settings = array(
            'site_details' => array(
                'base_url', 'cgi_url', 'ta_base_url', 'submitty_path', 'authentication'
            ),
            'logging_details' => array(
                'submitty_log_path', 'log_exceptions'
            ),
            'course_details' => array(
                'course_name', 'course_home_url', 'default_hw_late_days', 'default_student_late_days',
                'zero_rubric_grades', 'upload_message', 'keep_previous_files', 'display_rainbow_grades_summary',
                'display_custom_message', 'course_email', 'vcs_base_url', 'vcs_type'
            )
        );
        $return = array();
        foreach ($settings as $key => $value) {
            foreach ($value as $vv) {
                $return[] = array($key, $vv);
            }
        }
        return $return;
    }

    /**
     * @dataProvider getRequiredSettings
     *
     * @param string $section
     * @param string $setting
     */
    public function testMissingSectionSetting($section, $setting) {
        try {
            $extra = array($section => array($setting => null));
            $this->createConfigFile($extra);
    
            new Config($this->core, "s17", "csci0000", $this->master);
            $this->fail("Should have thrown ConfigException for {$section}.{$setting}");
        }
        catch (ConfigException $exception) {
            $this->assertEquals("Missing config setting {$section}.{$setting} in configuration ini file",
                $exception->getMessage());
        }

    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid Timezone identifier: invalid
     */
    public function testInvalidTimezone() {
        $extra = array('site_details' => array('timezone' => "invalid"));
        $this->createConfigFile($extra);

        new Config($this->core, "s17", "csci0000", $this->master);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid semester: invalid
     */
    public function testInvalidSemester() {
        $this->createConfigFile();

        new Config($this->core, "invalid", "csci0000", $this->master);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid course: invalid
     */
    public function testInvalidCourse() {
        $this->createConfigFile();

        new Config($this->core, "s17", "invalid", $this->master);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid path for setting submitty_path: /invalid
     */
    public function testInvalidSubmittyPath() {
        $extra = array('site_details' => array('submitty_path' => '/invalid'));
        $this->createConfigFile($extra);

        new Config($this->core, "s17", "csci0000", $this->master);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid path for setting submitty_log_path: /invalid
     */
    public function testInvalidLogPath() {
        $extra = array('logging_details' => array('submitty_log_path' => '/invalid'));
        $this->createConfigFile($extra);

        new Config($this->core, "s17", "csci0000", $this->master);
    }
}
