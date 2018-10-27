<?php

namespace tests\app\models;

use app\exceptions\ConfigException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\IniParser;
use app\libraries\Utils;
use app\models\Config;

class ConfigTester extends \PHPUnit\Framework\TestCase {
    private $core;

    private $temp_dir = null;
    private $config_path = null;
    private $course_ini_path = null;

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
        /** @noinspection PhpUnhandledExceptionInspection */
        $class = new \ReflectionClass('app\models\Config');
        $properties = $class->getDefaultProperties();
        $this->assertFalse($properties['debug']);
    }

    private function createConfigFile($extra = array()) {
        $this->temp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->temp_dir);
        $this->config_path = FileUtils::joinPaths($this->temp_dir, 'config');
        $course_path = FileUtils::joinPaths($this->temp_dir, "courses", "s17", "csci0000");
        $log_path = FileUtils::joinPaths($this->temp_dir, "logs");

        FileUtils::createDir($this->config_path);
        FileUtils::createDir($course_path, 0777, true);
        FileUtils::createDir(FileUtils::joinPaths($course_path, "config"));
        FileUtils::createDir($log_path);
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'access'));
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'autograding'));
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'site_errors'));

        $config = [
            "authentication_method" => "PamAuthentication",
            "database_host" => "/var/run/postgresql",
            "database_user" => "submitty_dbuser",
            "database_password" => "submitty_dbpass",
            "debugging_enabled" => false,
        ];
        $config = array_replace($config, $extra);
        FileUtils::writeJsonFile(FileUtils::joinPaths($this->config_path, "database.json"), $config);

        $config = [
            "submitty_install_dir" => $this->temp_dir,
            "submitty_repository" => "/usr/local/submitty/GIT_CHECKOUT/Submitty",
            "submitty_data_dir" => $this->temp_dir,
            "autograding_log_path" => FileUtils::joinPaths($log_path, 'autograding'),
            "timezone" => "America/Chicago",
            "site_log_path" => $log_path,
            "submission_url" => "http://example.com",
            "vcs_url" => "",
            "cgi_url" => "http://example.com/cgi-bin",
            "institution_name" => "RPI",
            "username_change_text" => "Submitty welcomes individuals of all ages, backgrounds, citizenships, disabilities, sex, education, ethnicities, family statuses, genders, gender identities, geographical locations, languages, military experience, political views, races, religions, sexual orientations, socioeconomic statuses, and work experiences. In an effort to create an inclusive environment, you may specify a preferred name to be used instead of what was provided on the registration roster.",
            "institution_homepage" => "https://rpi.edu",
        ];
        $config = array_replace($config, $extra);
        FileUtils::writeJsonFile(FileUtils::joinPaths($this->config_path, "submitty.json"), $config);

        $this->course_ini_path = FileUtils::joinPaths($course_path, "config", "config.ini");
        $config = array(
            'database_details' => array(
                'dbname' => 'submitty_s17_csci0000'
            ),
            'course_details' => array(
                'course_name' => 'Test Course',
                'course_home_url' => '',
                'default_hw_late_days' => 0,
                'default_student_late_days' => 0,
                'zero_rubric_grades' => false,
                'upload_message' => "",
                'keep_previous_files' => false,
                'display_rainbow_grades_summary' => false,
                'display_custom_message' => false,
                'course_email' => 'Please contact your TA or instructor for a regrade request.',
                'vcs_base_url' => '',
                'vcs_type' => 'git',
                'private_repository' => '',
                'forum_enabled' => true,
                'regrade_enabled' => false,
                'regrade_message' => 'Warning: Frivolous regrade requests may lead to grade deductions or lost late days',
                'room_seating_gradeable_id' => ""
            )
        );

        $config = array_replace_recursive($config, $extra);
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $kkey => $vvalue) {
                    if ($vvalue === null) {
                        unset($config[$key][$kkey]);
                    }
                }
            }
        }
        IniParser::writeFile($this->course_ini_path, $config);
    }

    public function testConfig() {
        $this->createConfigFile();

        $config = new Config($this->core, "s17", "csci0000");

        $config->loadMasterConfigs($this->config_path);

        $this->assertFalse($config->isDebug());
        $this->assertEquals("s17", $config->getSemester());
        $this->assertEquals("csci0000", $config->getCourse());
        $this->assertEquals("http://example.com/", $config->getBaseUrl());
        $this->assertEquals("http://example.com/cgi-bin/", $config->getCgiUrl());
        $this->assertEquals("http://example.com/index.php?", $config->getSiteUrl());
        $this->assertEquals("http://example.com/index.php?", $config->getHomepageUrl());
        $this->assertEquals($this->temp_dir, $config->getSubmittyPath());
        $this->assertEquals($this->temp_dir."/courses/s17/csci0000", $config->getCoursePath());
        $this->assertEquals($this->temp_dir."/logs", $config->getLogPath());
        $this->assertTrue($config->shouldLogExceptions());
        $this->assertEquals("pgsql", $config->getDatabaseDriver());
        $db_params = array(
            'dbname' => 'submitty',
            'host' => '/var/run/postgresql',
            'username' => 'submitty_dbuser',
            'password' => 'submitty_dbpass'
        );

        $this->assertEquals($db_params, $config->getSubmittyDatabaseParams());
        $this->assertEquals("PamAuthentication", $config->getAuthentication());
        $this->assertEquals("America/Chicago", $config->getTimezone()->getName());

        $config->loadCourseIni($this->course_ini_path);
        $this->assertEquals(array_merge($db_params, array('dbname' => 'submitty_s17_csci0000')), $config->getCourseDatabaseParams());
        $this->assertEquals("Test Course", $config->getCourseName());
        $this->assertEquals("http://example.com/index.php?semester=s17&course=csci0000", $config->getSiteUrl());
        $this->assertEquals("", $config->getCourseHomeUrl());
        $this->assertEquals(0, $config->getDefaultHwLateDays());
        $this->assertEquals(0, $config->getDefaultStudentLateDays());
        $this->assertFalse($config->shouldZeroRubricGrades());
        $this->assertEquals(FileUtils::joinPaths($this->temp_dir, 'config'), $config->getConfigPath());

        $this->assertEquals("", $config->getUploadMessage());
        $this->assertFalse($config->displayCustomMessage());
        $this->assertFalse($config->keepPreviousFiles());
        $this->assertFalse($config->displayRainbowGradesSummary());
        $this->assertEquals(FileUtils::joinPaths($this->temp_dir, "courses", "s17", "csci0000", "config", "config.ini"),
            $config->getCourseIniPath());
        $this->assertEquals('', $config->getRoomSeatingGradeableId());
        $this->assertFalse($config->displayRoomSeating());

        $expected = array(
            'debug' => false,
            'semester' => 's17',
            'course' => 'csci0000',
            'base_url' => 'http://example.com/',
            'cgi_url' => 'http://example.com/cgi-bin/',
            'site_url' => 'http://example.com/index.php?semester=s17&course=csci0000',
            'submitty_path' => $this->temp_dir,
            'course_path' => $this->temp_dir.'/courses/s17/csci0000',
            'submitty_log_path' => $this->temp_dir.'/logs',
            'log_exceptions' => true,
            'database_driver' => 'pgsql',
            'submitty_database_params' => $db_params,
            'course_database_params' => array_merge($db_params, array('dbname' => 'submitty_s17_csci0000')),
            'course_name' => 'Test Course',
            'config_path' => FileUtils::joinPaths($this->temp_dir, 'config'),
            'course_ini_path' => $this->temp_dir.'/courses/s17/csci0000/config/config.ini',
            'authentication' => 'PamAuthentication',
            'timezone' => 'DateTimeZone',
            'course_home_url' => '',
            'default_hw_late_days' => 0,
            'default_student_late_days' => 0,
            'zero_rubric_grades' => false,
            'upload_message' => '',
            'keep_previous_files' => false,
            'display_rainbow_grades_summary' => false,
            'display_custom_message' => false,
            'course_email' => 'Please contact your TA or instructor for a regrade request.',
            'vcs_base_url' => 'http://example.com/{$vcs_type}/s17/csci0000/',
            'vcs_type' => 'git',
            'modified' => false,
            'hidden_details' => null,
            'regrade_message' => 'Warning: Frivolous regrade requests may lead to grade deductions or lost late days',
            'course_ini' => [
                'database_details' => [
                    'dbname' => 'submitty_s17_csci0000'
                ],
                'course_details' => [
                    'course_name' => 'Test Course',
                    'course_home_url' => '',
                    'default_hw_late_days' => 0,
                    'default_student_late_days' => 0,
                    'zero_rubric_grades' => false,
                    'upload_message' => "",
                    'keep_previous_files' => false,
                    'display_rainbow_grades_summary' => false,
                    'display_custom_message' => false,
                    'course_email' => 'Please contact your TA or instructor for a regrade request.',
                    'vcs_base_url' => '',
                    'vcs_type' => 'git',
                    'private_repository' => '',
                    'forum_enabled' => true,
                    'regrade_enabled' => false,
                    'regrade_message' => 'Warning: Frivolous regrade requests may lead to grade deductions or lost late days',
                    'room_seating_gradeable_id' => ""
                ]
            ],
            'course_loaded' => true,
            'forum_enabled' => true,
            'institution_homepage' => 'https://rpi.edu',
            'institution_name' => 'RPI',
            'private_repository' => '',
            'regrade_enabled' => false,
            'room_seating_gradeable_id' => '',
            'username_change_text' => 'Submitty welcomes individuals of all ages, backgrounds, citizenships, disabilities, sex, education, ethnicities, family statuses, genders, gender identities, geographical locations, languages, military experience, political views, races, religions, sexual orientations, socioeconomic statuses, and work experiences. In an effort to create an inclusive environment, you may specify a preferred name to be used instead of what was provided on the registration roster.',
            'vcs_url' => 'http://example.com/{$vcs_type}/',
            'wrapper_files' => []
        );
        $actual = $config->toArray();

        ksort($expected);
        ksort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function testHiddenCourseUrl() {
        $extra = array('hidden_details' => array('course_url' => 'http://example.com/course'));
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000");
        $config->loadMasterConfigs($this->config_path);
        $config->loadCourseIni($this->course_ini_path);
        $this->assertEquals("http://example.com/course/", $config->getBaseUrl());
        $this->assertEquals("http://example.com/course", $config->getHiddenDetails()['course_url']);
    }

    public function testDefaultTimezone() {
        $extra = ['timezone' => null];
        $this->createConfigFile($extra);
        $config = new Config($this->core, "s17", "csci0000");
        $config->loadMasterConfigs($this->config_path);
        $this->assertEquals("America/New_York", $config->getTimezone()->getName());
    }

    public function testDebugTrue() {
        $extra = ['debugging_enabled' => true];
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000");
        $config->loadMasterConfigs($this->config_path);
        $this->assertTrue($config->isDebug());
    }

    public function testDatabaseDriver() {
        $extra = ['driver' => 'sqlite'];
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000");
        $config->loadMasterConfigs($this->config_path);
        $config->loadCourseIni($this->course_ini_path);
        $this->assertEquals("sqlite", $config->getDatabaseDriver());
    }

    public function testVcsUrl() {
        $extra = ['vcs_url' => 'https://some.vcs.url.com'];
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "config");
        $config->loadMasterConfigs($this->config_path);
        $this->assertEquals("https://some.vcs.url.com/", $config->getVcsUrl());
    }

    public function testCourseSeating() {
        $extra = ['course_details' => ['room_seating_gradeable_id' => 'test_id']];
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "config");
        $config->loadMasterConfigs($this->config_path);
        $config->loadCourseIni($this->course_ini_path);
        $this->assertEquals("test_id", $config->getRoomSeatingGradeableId());
        $this->assertTrue($config->displayRoomSeating());
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Could not find config directory: /invalid/path
     */
    public function testInvalidMasterConfigPath() {
        $config = new Config($this->core, "s17", "csci1000");
        $config->loadMasterConfigs('/invalid/path');
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessageRegExp /Could not find config directory: .*\/config\/database.json/
     */
    public function testConfigPathFile() {
        $this->createConfigFile();
        $config = new Config($this->core, "s17", "csci1000");
        $config->loadMasterConfigs(FileUtils::joinPaths($this->temp_dir, 'config', 'database.json'));
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessageRegExp /Could not find database config: .*\/config\/database.json/
     */
    public function testMissingDatabaseJson() {
        $this->createConfigFile();
        unlink(FileUtils::joinPaths($this->temp_dir, 'config', 'database.json'));
        $config = new Config($this->core, "s17", "csci1000");
        $config->loadMasterConfigs($this->config_path);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessageRegExp /Could not find submitty config: .*\/config\/submitty.json/
     */
    public function testMissingSubmittyJson() {
        $this->createConfigFile();
        unlink(FileUtils::joinPaths($this->temp_dir, 'config', 'submitty.json'));
        $config = new Config($this->core, "s17", "csci1000");
        $config->loadMasterConfigs($this->config_path);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Could not find course config file: /invalid/path
     */
    public function testInvalidCourseConfigPath() {
        $config = new Config($this->core, "s17", "csci1000");
        $config->loadCourseIni("/invalid/path");
    }

    /**
     * @expectedException \app\exceptions\IniException
     * @expectedExceptionMessageRegExp /Error reading ini file 'database\.json': syntax error, unexpected '\{' in .*\/database\.json on line 1/
     */
    public function testInvalidCourseConfigIni() {
        $this->createConfigFile();
        $config = new Config($this->core, "s17", "csci1000");
        $config->loadCourseIni(FileUtils::joinPaths($this->config_path, "database.json"));
    }

    public function getRequiredSections() {
        return array(
            array('database_details'),
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
    
            $config = new Config($this->core, "s17", "csci0000");
            $config->loadCourseIni($this->course_ini_path);
            $this->fail("Should have thrown ConfigException");
        }
        catch (ConfigException $exception) {
            $this->assertEquals("Missing config section '{$section}' in ini file", $exception->getMessage());
        }
    }

    public function getRequiredSettings() {
        $settings = [
            'course_details' => [
                'course_name', 'course_home_url', 'default_hw_late_days', 'default_student_late_days',
                'zero_rubric_grades', 'upload_message', 'keep_previous_files', 'display_rainbow_grades_summary',
                'display_custom_message', 'course_email', 'vcs_base_url', 'vcs_type', 'private_repository',
                'forum_enabled', 'regrade_enabled', 'regrade_message', 'room_seating_gradeable_id',
            ],
        ];
        $return = array();
        foreach ($settings as $key => $value) {
            foreach ($value as $vv) {
                $return[] = [$key, $vv];
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
            $extra = [$section => [$setting => null]];
            $this->createConfigFile($extra);
    
            $config = new Config($this->core, "s17", "csci0000");
            $config->loadCourseIni($this->course_ini_path);
            $this->fail("Should have thrown ConfigException for {$section}.{$setting}");
        }
        catch (ConfigException $exception) {
            $this->assertEquals(
                "Missing config setting '{$section}.{$setting}' in configuration ini file",
                $exception->getMessage()
            );
        }

    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid Timezone identifier: invalid
     */
    public function testInvalidTimezone() {
        $extra = ['timezone' => "invalid"];
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000");
        $config->loadMasterConfigs($this->config_path);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid path for setting submitty_path: /invalid
     */
    public function testInvalidSubmittyPath() {
        $extra = ['submitty_data_dir' => '/invalid'];
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000");
        $config->loadMasterConfigs($this->config_path);
    }

    /**
     * @expectedException \app\exceptions\ConfigException
     * @expectedExceptionMessage Invalid path for setting submitty_log_path: /invalid
     */
    public function testInvalidLogPath() {
        $extra = ['site_log_path' => '/invalid'];
        $this->createConfigFile($extra);

        $config = new Config($this->core, "s17", "csci0000");
        $config->loadMasterConfigs($this->config_path);
    }
}
