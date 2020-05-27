<?php

namespace tests\app\models;

use app\exceptions\ConfigException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Config;

class ConfigTester extends \PHPUnit\Framework\TestCase {
    private $core;

    private $temp_dir = null;
    private $config_path = null;
    private $course_json_path = null;

    public function setUp(): void {
        $this->core = $this->createMock(Core::class);
    }

    public function tearDown(): void {
        if ($this->temp_dir !== null && is_dir($this->temp_dir)) {
            FileUtils::recursiveRmdir($this->temp_dir);
        }
    }

    /**
     * This test ensures that the default value of the DEBUG flag within the config model is always false. This
     * means that if the value is not found within the json file, we don't have to worry about accidently
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
        FileUtils::createDir($course_path, true, 0777);
        FileUtils::createDir(FileUtils::joinPaths($course_path, "config"));
        FileUtils::createDir($log_path);
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'access'));
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'autograding'));
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'site_errors'));
        FileUtils::createDir(FileUtils::joinPaths($log_path, 'ta_grading'));

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
            "cgi_url" => "",
            "institution_name" => "RPI",
            "sys_admin_email" => "admin@example.com",
            "sys_admin_url" => "https://example.com/admin",
            "username_change_text" => "Submitty welcomes all students.",
            "course_code_requirements" => "Please follow your school's convention for course code.",
            "institution_homepage" => "https://rpi.edu",
            'system_message' => "Some system message",
            "duck_special_effects" => false
        ];
        $config = array_replace($config, $extra);
        FileUtils::writeJsonFile(FileUtils::joinPaths($this->config_path, "submitty.json"), $config);

        $config = [
            'session' => 'LIW0RT5XAxOn2xjVY6rrLTcb6iacl4IDNRyPw58M0Kn0haQbHtNvPfK18xpvpD93'
        ];
        $config = array_replace($config, $extra);
        FileUtils::writeJsonFile(FileUtils::joinPaths($this->config_path, "secrets_submitty_php.json"), $config);

        $this->course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
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
                'display_rainbow_grades_summary' => false,
                'display_custom_message' => false,
                'course_email' => 'Please contact your TA or instructor to submit a grade inquiry.',
                'vcs_base_url' => '',
                'vcs_type' => 'git',
                'private_repository' => '',
                'forum_enabled' => true,
                'forum_create_thread_message' => '',
                'regrade_enabled' => false,
                'seating_only_for_instructor' => false,
                'regrade_message' => 'Warning: Frivolous grade inquiries may lead to grade deductions or lost late days',
                'room_seating_gradeable_id' => "",
                'auto_rainbow_grades' => false,
                'queue_enabled' => true,
                'queue_contact_info' => true,
                'queue_message' => ''
            ),
            'feature_flags' => [

            ]
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
        FileUtils::writeJsonFile($this->course_json_path, $config);

        // Create psuedo email json
        $config = array(
            'email_enabled' => true,
            'email_user' => '',
            'email_password' => '',
            'email_sender' => 'submitty@myuniversity.edu',
            'email_reply_to' => 'submitty_do_not_reply@myuniversity.edu',
            'email_server_hostname' => 'localhost',
            'email_server_port' => 25
        );
        $config = array_replace($config, $extra);
        FileUtils::writeJsonFile(FileUtils::joinPaths($this->config_path, "email.json"), $config);

        // Create version json
        $config = array(
            "installed_commit" => "d150131c19e3e8084b25cddcc32e6c40a8e93a2b",
            "short_installed_commit" => "d150131c",
            "most_recent_git_tag" => "v19.07.00"
        );
        $config = array_replace($config, $extra);
        FileUtils::writeJsonFile(FileUtils::joinPaths($this->config_path, "version.json"), $config);
    }

    public function testConfig() {
        $this->createConfigFile();

        $config = new Config($this->core);

        $config->loadMasterConfigs($this->config_path);
        $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
        $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
        $config->loadCourseJson("s17", "csci0000", $course_json_path);

        $this->assertFalse($config->isDebug());
        $this->assertEquals("s17", $config->getSemester());
        $this->assertEquals("csci0000", $config->getCourse());
        $this->assertEquals("http://example.com/", $config->getBaseUrl());
        $this->assertEquals("http://example.com/cgi-bin/", $config->getCgiUrl());
        $this->assertEquals($this->temp_dir, $config->getSubmittyPath());
        $this->assertEquals($this->temp_dir . "/courses/s17/csci0000", $config->getCoursePath());
        $this->assertEquals($this->temp_dir . "/logs", $config->getLogPath());

        $this->assertEquals(FileUtils::joinPaths($this->temp_dir, "tmp", "cgi"), $config->getCgiTmpPath());
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
        $this->assertEquals("RPI", $config->getInstitutionName());
        $this->assertEquals("admin@example.com", $config->getSysAdminEmail());
        $this->assertEquals("https://example.com/admin", $config->getSysAdminUrl());
        $this->assertEquals("https://rpi.edu", $config->getInstitutionHomepage());
        $this->assertEquals("Submitty welcomes all students.", $config->getUsernameChangeText());
        $this->assertEquals("Please follow your school's convention for course code.", $config->getCourseCodeRequirements());
        $this->assertEquals("Some system message", $config->getSystemMessage());

        $this->assertEquals(array_merge($db_params, array('dbname' => 'submitty_s17_csci0000')), $config->getCourseDatabaseParams());
        $this->assertEquals("Test Course", $config->getCourseName());
        $this->assertEquals("", $config->getCourseHomeUrl());
        $this->assertEquals(0, $config->getDefaultHwLateDays());
        $this->assertEquals(0, $config->getDefaultStudentLateDays());
        $this->assertFalse($config->shouldZeroRubricGrades());
        $this->assertEquals(FileUtils::joinPaths($this->temp_dir, 'config'), $config->getConfigPath());

        $this->assertEquals("", $config->getUploadMessage());
        $this->assertFalse($config->displayCustomMessage());
        $this->assertFalse($config->displayRainbowGradesSummary());
        $this->assertEquals(
            FileUtils::joinPaths($this->temp_dir, "courses", "s17", "csci0000", "config", "config.json"),
            $config->getCourseJsonPath()
        );
        $this->assertEquals('', $config->getRoomSeatingGradeableId());
        $this->assertFalse($config->displayRoomSeating());
        $this->assertEquals('LIW0RT5XAxOn2xjVY6rrLTcb6iacl4IDNRyPw58M0Kn0haQbHtNvPfK18xpvpD93', $config->getSecretSession());

        $expected = array(
            'debug' => false,
            'semester' => 's17',
            'course' => 'csci0000',
            'base_url' => 'http://example.com/',
            'cgi_url' => 'http://example.com/cgi-bin/',
            'submitty_path' => $this->temp_dir,
            'course_path' => $this->temp_dir . '/courses/s17/csci0000',
            'submitty_log_path' => $this->temp_dir . '/logs',
            'log_exceptions' => true,
            'cgi_tmp_path' => FileUtils::joinPaths($this->temp_dir, "tmp", "cgi"),
            'database_driver' => 'pgsql',
            'submitty_database_params' => $db_params,
            'course_database_params' => array_merge($db_params, array('dbname' => 'submitty_s17_csci0000')),
            'course_name' => 'Test Course',
            'config_path' => FileUtils::joinPaths($this->temp_dir, 'config'),
            'course_json_path' => $this->temp_dir . '/courses/s17/csci0000/config/config.json',
            'authentication' => 'PamAuthentication',
            'timezone' => 'DateTimeZone',
            'course_home_url' => '',
            'default_hw_late_days' => 0,
            'default_student_late_days' => 0,
            'zero_rubric_grades' => false,
            'duck_banner_enabled' => false,
            'upload_message' => '',
            'display_rainbow_grades_summary' => false,
            'display_custom_message' => false,
            'course_email' => 'Please contact your TA or instructor to submit a grade inquiry.',
            'vcs_base_url' => 'http://example.com/{$vcs_type}/s17/csci0000/',
            'vcs_type' => 'git',
            'modified' => false,
            'hidden_details' => null,
            'regrade_message' => 'Warning: Frivolous grade inquiries may lead to grade deductions or lost late days',
            'course_json' => [
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
                    'display_rainbow_grades_summary' => false,
                    'display_custom_message' => false,
                    'course_email' => 'Please contact your TA or instructor to submit a grade inquiry.',
                    'vcs_base_url' => '',
                    'vcs_type' => 'git',
                    'private_repository' => '',
                    'forum_enabled' => true,
                    'forum_create_thread_message' => '',
                    'regrade_enabled' => false,
                    'seating_only_for_instructor' => false,
                    'regrade_message' => 'Warning: Frivolous grade inquiries may lead to grade deductions or lost late days',
                    'room_seating_gradeable_id' => "",
                    'auto_rainbow_grades' => false,
                    'queue_enabled' => true,
                    'queue_contact_info' => true,
                    'queue_message' => ''
                ],
                'feature_flags' => []
            ],
            'course_loaded' => true,
            'forum_enabled' => true,
            'forum_create_thread_message' => '',
            'institution_homepage' => 'https://rpi.edu',
            'institution_name' => 'RPI',
            "sys_admin_email" => "admin@example.com",
            "sys_admin_url" => "https://example.com/admin",
            'private_repository' => '',
            'regrade_enabled' => false,
            'seating_only_for_instructor' => false,
            'room_seating_gradeable_id' => '',
            'username_change_text' => 'Submitty welcomes all students.',
            'course_code_requirements' => "Please follow your school's convention for course code.",
            'vcs_url' => 'http://example.com/{$vcs_type}/',
            'wrapper_files' => [],
            'system_message' => 'Some system message',
            'secret_session' => 'LIW0RT5XAxOn2xjVY6rrLTcb6iacl4IDNRyPw58M0Kn0haQbHtNvPfK18xpvpD93',
            'email_enabled' => true,
            'auto_rainbow_grades' => false,
            'latest_commit' => 'd150131c',
            'latest_tag' => 'v19.07.00',
            'verified_submitty_admin_user' => null,
            'queue_enabled' => true,
            'queue_contact_info' => true,
            'queue_message' => '',
            'feature_flags' => [],
            'submitty_install_path' => $this->temp_dir,
        );
        $actual = $config->toArray();

        ksort($expected);
        ksort($actual);

        $this->assertEquals($expected, $actual);
    }

    public function testHiddenCourseUrl() {
        $extra = array('hidden_details' => array('course_url' => 'http://example.com/course'));
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
        $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
        $config->loadCourseJson("s17", "csci0000", $course_json_path);
        $this->assertEquals("http://example.com/course/", $config->getBaseUrl());
        $this->assertEquals("http://example.com/course", $config->getHiddenDetails()['course_url']);
    }

    public function testDefaultTimezone() {
        $extra = ['timezone' => null];
        $this->createConfigFile($extra);
        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $this->assertEquals("America/New_York", $config->getTimezone()->getName());
    }

    public function testDebugTrue() {
        $extra = ['debugging_enabled' => true];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $this->assertTrue($config->isDebug());
    }

    public function testDatabaseDriver() {
        $extra = ['driver' => 'sqlite'];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $this->assertEquals("sqlite", $config->getDatabaseDriver());
    }

    public function testNonEmptyVcsUrl() {
        $extra = ['vcs_url' => 'https://some.vcs.url.com'];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $this->assertEquals("https://some.vcs.url.com/", $config->getVcsUrl());
    }

    public function testNonEmptyCgiUrl() {
        $extra = ['cgi_url' => 'https://some.cgi.url.com'];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $this->assertEquals("https://some.cgi.url.com/", $config->getCgiUrl());
    }

    public function testCourseSeating() {
        $extra = ['course_details' => ['room_seating_gradeable_id' => 'test_id']];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
        $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
        $config->loadCourseJson("s17", "csci0000", $course_json_path);
        $this->assertEquals("test_id", $config->getRoomSeatingGradeableId());
        $this->assertTrue($config->displayRoomSeating());
    }

    public function testInvalidMasterConfigPath() {
        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessage('Could not find config directory: /invalid/path');
        $config->loadMasterConfigs('/invalid/path');
    }

    public function testConfigPathFile() {
        $this->createConfigFile();
        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessageMatches('/Could not find config directory: .*\/config\/database.json/');
        $config->loadMasterConfigs(FileUtils::joinPaths($this->temp_dir, 'config', 'database.json'));
    }

    public function testMissingDatabaseJson() {
        $this->createConfigFile();
        unlink(FileUtils::joinPaths($this->temp_dir, 'config', 'database.json'));
        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessageMatches('/Could not find database config: .*\/config\/database.json/');
        $config->loadMasterConfigs($this->config_path);
    }

    public function testMissingSubmittyJson() {
        $this->createConfigFile();
        unlink(FileUtils::joinPaths($this->temp_dir, 'config', 'submitty.json'));
        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessageMatches('/Could not find submitty config: .*\/config\/submitty.json/');
        $config->loadMasterConfigs($this->config_path);
    }

    public function testInvalidCourseConfigPath() {
        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessage('Could not find course config file: /invalid/path');
        $config->loadCourseJson("s17", "csci0000", "/invalid/path");
    }

    public function testInvalidCourseConfigJson() {
        $this->createConfigFile();
        $config = new Config($this->core);
        file_put_contents(FileUtils::joinPaths($this->temp_dir, "test.txt"), "afds{}fasdf");
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessage('Error parsing the config file: Syntax error');
        $config->loadCourseJson("s17", "csci1000", FileUtils::joinPaths($this->temp_dir, "test.txt"));
    }

    public function testMissingEmailJson() {
        $this->createConfigFile();
        unlink(FileUtils::joinPaths($this->temp_dir, 'config', 'email.json'));
        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessageMatches('/Could not find email config: .*\/config\/email.json/');
        $config->loadMasterConfigs($this->config_path);
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

            $config = new Config($this->core);
            $config->loadMasterConfigs($this->config_path);
            $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
            $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
            $config->loadCourseJson("s17", "csci0000", $course_json_path);
            $this->fail("Should have thrown ConfigException");
        }
        catch (ConfigException $exception) {
            $this->assertEquals("Missing config section '{$section}' in json file", $exception->getMessage());
        }
    }

    public function getRequiredSettings() {
        $settings = [
            'course_details' => [
                'course_name', 'course_home_url', 'default_hw_late_days', 'default_student_late_days',
                'zero_rubric_grades', 'upload_message', 'display_rainbow_grades_summary',
                'display_custom_message', 'course_email', 'vcs_base_url', 'vcs_type', 'private_repository',
                'forum_enabled', 'forum_create_thread_message', 'regrade_enabled', 'seating_only_for_instructor',
                'regrade_message', 'room_seating_gradeable_id', 'queue_enabled', 'queue_contact_info',
                'queue_message'
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

            $config = new Config($this->core);
            $config->loadMasterConfigs($this->config_path);
            $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
            $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
            $config->loadCourseJson("s17", "csci0000", $course_json_path);
            $this->fail("Should have thrown ConfigException for {$section}.{$setting}");
        }
        catch (ConfigException $exception) {
            $this->assertEquals(
                "Missing config setting '{$section}.{$setting}' in configuration json file",
                $exception->getMessage()
            );
        }
    }

    public function testInvalidTimezone() {
        $extra = ['timezone' => "invalid"];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessage('Invalid Timezone identifier: invalid');
        $config->loadMasterConfigs($this->config_path);
    }

    public function testInvalidSubmittyPath() {
        $extra = ['submitty_data_dir' => '/invalid'];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessage('Invalid path for setting submitty_path: /invalid');
        $config->loadMasterConfigs($this->config_path);
    }

    public function testInvalidLogPath() {
        $extra = ['site_log_path' => '/invalid'];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $this->expectException(\app\exceptions\ConfigException::class);
        $this->expectExceptionMessage('Invalid path for setting submitty_log_path: /invalid');
        $config->loadMasterConfigs($this->config_path);
    }

    public function testMissingSecretsFile() {
        $this->createConfigFile();
        unlink(FileUtils::joinPaths($this->temp_dir, 'config', 'secrets_submitty_php.json'));
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/^Could not find secrets config: .*\/config\/secrets_submitty_php\.json$/');
        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
    }

    public function testNullSecret() {
        $extra = ['session' => null];
        $this->createConfigFile($extra);
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Missing secret var: session");
        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
    }

    public function testWeakSecret() {
        $extra = ['session' => 'weak'];
        $this->createConfigFile($extra);
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Secret session is too weak. It should be at least 32 bytes.');

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
    }

    public function testFeatureFlagTrueDebug() {
        $extra = ['debugging_enabled' => true];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
        $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
        $config->loadCourseJson("s17", "csci0000", $course_json_path);

        $this->assertTrue($config->isDebug());
        $this->assertTrue($config->checkFeatureFlagEnabled('non_existing_name'));
    }

    public function testFeatureFlagEnabled() {
        $extra = ['feature_flags' => ['feature_1' => true, 'feature_2' => false]];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
        $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
        $config->loadCourseJson("s17", "csci0000", $course_json_path);

        $this->assertFalse($config->isDebug());
        $this->assertFalse($config->checkFeatureFlagEnabled('non_existing_name'));
        $this->assertTrue($config->checkFeatureFlagEnabled('feature_1'));
        $this->assertFalse($config->checkFeatureFlagEnabled('feature_2'));
    }

    public function testNonexistingFeatureFlagConfig() {
        $extra = ['feature_flags' => null];
        $this->createConfigFile($extra);

        $config = new Config($this->core);
        $config->loadMasterConfigs($this->config_path);
        $course_path = FileUtils::joinPaths($config->getSubmittyPath(), "courses", "s17", "csci0000");
        $course_json_path = FileUtils::joinPaths($course_path, "config", "config.json");
        $config->loadCourseJson("s17", "csci0000", $course_json_path);

        $this->assertFalse($config->isDebug());
        $this->assertFalse($config->checkFeatureFlagEnabled('non_existing_name'));
        $this->assertFalse($config->checkFeatureFlagEnabled('feature_1'));
    }
}
