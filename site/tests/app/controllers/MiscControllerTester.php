<?php

namespace tests\app\controllers;

use app\controllers\MiscController;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\SessionManager;
use app\libraries\Utils;
use app\models\Config;
use app\models\User;
use tests\utils\NullOutput;

class MiscControllerTester extends \PHPUnit\Framework\TestCase {
    use \phpmock\phpunit\PHPMock;

    private $tmp_dir = '';

    public function tearDown(): void {
        if (file_exists($this->tmp_dir)) {
            FileUtils::recursiveRmdir($this->tmp_dir);
        }
    }

    public function userDataProvider() {
        $user_details = [
            'user_id' => 'test',
            'user_firstname' => 'Test',
            'user_lastname' => 'Person',
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false
        ];
        for ($i = 4; $i > 0; $i--) {
            $user_details['user_group'] = $i;
            yield [$user_details];
        }
    }

    /**
     * @dataProvider userDataProvider
     * @runInSeparateProcess
     */
    public function testReadFileSite($user_details): void {
        $this->getFunctionMock('app\controllers', 'header')
            ->expects($this->once())
            ->with('Content-type: text/css');

        $core = new Core();
        $user = new User($core, $user_details);
        $core->setUser($user);
        $core->setOutput(new NullOutput($core));
        $config = new Config($core);
        $core->setConfig($config);
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->tmp_dir);
        FileUtils::createDir(FileUtils::joinpaths($this->tmp_dir, 'site'));
        $override_path = FileUtils::joinPaths($this->tmp_dir, 'site', 'override.css');
        file_put_contents($override_path, "body {}");
        $config->setCoursePath($this->tmp_dir);
        $session_manager = $this->createMock(SessionManager::class);
        $session_manager->method('getCsrfToken')->willReturn('test');
        $core->setSessionManager($session_manager);

        $controller = new MiscController($core);
        ob_start();
        $result = $controller->readFile('site', $override_path, 'test');
        $file_contents = ob_get_contents();
        ob_end_clean();
        $this->assertTrue($result);
        $this->assertEquals('body {}', $file_contents);
    }

    /**
     * @dataProvider userDataProvider
     */
    public function testReadFileDirectoryTraversal($user_details): void {
        $core = new Core();
        $user = new User($core, $user_details);
        $core->setUser($user);
        $core->setOutput(new NullOutput($core));
        $config = new Config($core);
        $core->setConfig($config);
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->tmp_dir);
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, 'site'));
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, 'submissions'));
        $override_path = FileUtils::joinPaths($this->tmp_dir, 'site', 'override.css');
        file_put_contents(FileUtils::joinPaths($this->tmp_dir, 'submissions', 'test.py'), 'print(True)');
        file_put_contents($override_path, "body {}");
        $config->setCoursePath($this->tmp_dir);
        $session_manager = $this->createMock(SessionManager::class);
        $session_manager->method('getCsrfToken')->willReturn('test');
        $core->setSessionManager($session_manager);

        $controller = new MiscController($core);
        ob_start();
        $result = $controller->readFile(
            'site',
            FileUtils::joinPaths($this->tmp_dir, 'site', '..', 'submissions', 'test.py'),
            'test'
        );
        ob_end_clean();
        $this->assertFalse($result);
    }
}
