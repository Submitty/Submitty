<?php

namespace tests\app\controllers\admin;

use app\controllers\admin\AdminGradeableController;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Config;
use tests\utils\NullOutput;

class AdminGradeableControllerTester extends \PHPUnit\Framework\TestCase {
    private $test_dir;
    private $master_configs_dir;
    private $course_config;

    public function setUpConfig(): void {
        $this->test_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->test_dir);
        $this->master_configs_dir = FileUtils::joinPaths($this->test_dir, 'master');
        FileUtils::createDir($this->master_configs_dir);
        foreach (['autograding', 'access', 'site_errors', 'ta_grading'] as $path) {
            FileUtils::createDir(FileUtils::joinPaths($this->test_dir, $path));
        }

        $config_files = [
            'authentication' => '{"authentication_method": "PamAuthentication", "ldap_options": [], "saml_options": []}',
            'autograding_workers' => '{"primary":{"capabilities":["default"],"address":"localhost","username":"","num_autograding_workers":5,"enabled":true}}',
            'database' => '{"database_host":"\/var\/run\/postgresql","database_port":5432,"database_user":"submitty_dbuser","database_password":"submitty_dbuser","database_course_user":"submitty_course_dbuser","database_course_password": "submitty_course_dbuser","debugging_enabled":true}',
            'email' => '{"email_enabled":true,"email_user":"","email_password":"","email_sender":"submitty@vagrant","email_reply_to":"do-not-reply@vagrant","email_server_hostname":"localhost","email_server_port":25}',
            'secrets_submitty_php' => '{"session":"cGRZSDnVxdDjQwGyiq4ECnJyiZ8IQXEL1guSsJ1XlSKSEqisqvdCPhCRcYDEjpjm"}',
            'submitty_admin' => '{"submitty_admin_username":"submitty-admin","token":"token"}',
            'submitty' => '{"submitty_install_dir":' . json_encode($this->test_dir) . ',"submitty_repository":' . json_encode($this->test_dir) . ',"submitty_data_dir":' . json_encode($this->test_dir) . ',"autograding_log_path":' . json_encode($this->test_dir) . ',"site_log_path":' . json_encode($this->test_dir) . ',"submission_url":"http:\/\/localhost:1501","vcs_url":"","cgi_url":"http:\/\/localhost:1501\/cgi-bin","institution_name":"","username_change_text":"foo","institution_homepage":"" ,"sys_admin_email": "admin@example.com","sys_admin_url": "https:\/\/example.com\/admin","timezone":"America\/New_York","worker":false,"duck_special_effects":false,"user_create_account":false,"user_id_requirements":{"any_user_id":true,"require_name":false,"min_length":6,"max_length":25,"name_requirements":{"given_first":false,"given_name": 2,"family_name": 4},"require_email": false,"email_requirements": {"whole_email": false,"whole_prefix": false,"prefix_count": 6}, "accepted_emails":["gmail.com"]}}',
            'submitty_users' => '{"num_grading_scheduler_workers":5,"num_untrusted":60,"first_untrusted_uid":900,"first_untrusted_gid":900,"daemon_uid":1003,"daemon_gid":1006,"daemon_user":"submitty_daemon","course_builders_group":"submitty_course_builders","php_uid":1001,"php_gid":1004,"php_user":"submitty_php","cgi_user":"submitty_cgi","daemonphp_group":"submitty_daemonphp","daemoncgi_group":"submitty_daemoncgi","verified_submitty_admin_user":"submitty-admin"}',
            'version' => '{"installed_commit":"7da8417edd6ff46f1d56e1a938b37c054a7dd071","short_installed_commit":"7da8417ed","most_recent_git_tag":"v19.09.04"}'
        ];

        foreach ($config_files as $file => $value) {
            file_put_contents(FileUtils::joinPaths($this->master_configs_dir, $file . '.json'), $value);
        }

        $course_path = FileUtils::joinPaths($this->test_dir, 'courses', 'f19', 'sample');
        FileUtils::createDir($course_path, true);
        FileUtils::createDir(FileUtils::joinPaths($course_path, 'build'), true);
        FileUtils::createDir(FileUtils::joinPaths($course_path, 'config', 'complete_config'), true);

        $this->course_config = FileUtils::joinPaths($this->test_dir, 'course.json');
        file_put_contents(
            $this->course_config,
            '{"database_details":{"dbname":"submitty_f19_sample"},"course_details":{"course_name":"Submitty Sample","course_home_url":"","default_hw_late_days":0,"default_student_late_days":0,"zero_rubric_grades":false,"upload_message":"Hit Submit","display_rainbow_grades_summary":false,"display_custom_message":false,"course_email":"Please contact your TA or instructor to submit a grade inquiry.","vcs_base_url":"","vcs_type":"git","private_repository":"","forum_enabled":true,"forum_create_thread_message":"","grade_inquiry_message":"Grade Inquiry Message","seating_only_for_instructor":false,"room_seating_gradeable_id":"","auto_rainbow_grades":false, "queue_enabled": false, "queue_message":"Welcome to the OH/Lab queue", "queue_announcement_message":"announcement message", "seek_message_enabled":false, "seek_message_instructions":"", "polls_enabled": false, "chat_enabled": false}}'
        );
    }

    public function tearDown(): void {
        if (!empty($this->test_dir) && file_exists($this->test_dir)) {
            FileUtils::recursiveRmdir($this->test_dir);
        }
    }

    private function getCore(): Core {
        $core = new Core();
        $core->setOutput(new NullOutput($core));

        $config = new Config($core);
        $config->loadMasterConfigs($this->master_configs_dir);
        $config->loadCourseJson('f19', 'sample', $this->course_config);
        $core->setConfig($config);

        return $core;
    }

    public function testGetBuildLogsIncludesGeneratedConfigs(): void {
        $this->setUpConfig();
        $gradeable_id = 'example_gradeable';
        $course_path = FileUtils::joinPaths($this->test_dir, 'courses', 'f19', 'sample');
        $build_path = FileUtils::joinPaths($course_path, 'build', $gradeable_id);
        $complete_config_dir = FileUtils::joinPaths($course_path, 'config', 'complete_config');

        FileUtils::createDir($build_path, true);
        FileUtils::createDir($complete_config_dir, true);

        $build_output = "ERROR: Example build failure\n";
        $cmake_output = "cmake output\n";
        $preprocessed_config = "{\n    \"expanded\": true\n}\n";
        $generated_complete_config = "{\n    \"generated\": true\n}\n";

        file_put_contents(FileUtils::joinPaths($build_path, 'build_script_output.txt'), $build_output);
        file_put_contents(FileUtils::joinPaths($build_path, 'log_cmake_output.txt'), $cmake_output);
        file_put_contents(FileUtils::joinPaths($build_path, 'complete_config.json'), $preprocessed_config);
        file_put_contents(
            FileUtils::joinPaths($complete_config_dir, "complete_config_{$gradeable_id}.json"),
            $generated_complete_config
        );

        $controller = new AdminGradeableController($this->getCore());
        $response = $controller->getBuildLogs($gradeable_id);

        $this->assertEquals([
            'status' => 'success',
            'data' => [
                'build_output' => htmlentities($build_output),
                'cmake_output' => htmlentities($cmake_output),
                'preprocessed_config' => htmlentities($preprocessed_config),
                'generated_complete_config' => htmlentities($generated_complete_config),
            ]
        ], $response->json);
    }
}
