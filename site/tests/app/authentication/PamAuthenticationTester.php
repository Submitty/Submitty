<?php

namespace tests\app\authentication;

use app\authentication\PamAuthentication;
use app\exceptions\CurlException;
use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Config;
use tests\BaseUnitTest;

class PamAuthenticationTester extends BaseUnitTest {
    /** @var string */
    private $tmp_path;
    public function setUp() {
        $this->tmp_path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_path, 'tmp', 'pam'), 0777, true);
    }

    /**
     * We have to do this tearDown as a shutdown function due to the fact that the PamAuthentication
     */
    public function tearDown() {
        FileUtils::recursiveChmod($this->tmp_path, 0777);
        $iter = new \FilesystemIterator(FileUtils::joinPaths($this->tmp_path, 'tmp', 'pam'), \FilesystemIterator::SKIP_DOTS);
        $this->assertEquals(0, iterator_count($iter));
        $this->assertTrue(FileUtils::recursiveRmdir($this->tmp_path));
    }

    private function getMockCore($curl_response) {
        $config = $this->createMockModel(Config::class);
        $config->method('getSubmittyPath')->willReturn($this->tmp_path);
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getSubmittyUser')->willReturn(true);
        $core = $this->createMock(Core::class);
        $core->method('getConfig')->willReturn($config);
        $core->method('getQueries')->willReturn($queries);
        $core->method('curlRequest')->willReturn($curl_response);
        return $core;
    }

    public function testSuccessfulPamAuthentication() {
        $core = $this->getMockCore('{"authenticated": true}');

        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $this->assertEquals('test', $pam->getUserId());
        $pam->setPassword('test');
        $this->assertTrue($pam->authenticate());
    }


    public function testFailedPamAuthentication() {
        $core = $this->getMockCore('{"authenticated": false}');

        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $pam->setPassword('test');
        $this->assertFalse($pam->authenticate());
    }

    public function testNonBooleanAuthenticatedValue() {
        $core = $this->getMockCore('{"authenticated": "string_value"}');

        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $pam->setPassword('test');
        $this->assertFalse($pam->authenticate());
    }

    public function testNoUserId() {
        $core = $this->createMock(Core::class);
        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $this->assertFalse($pam->authenticate());
    }

    public function testNoPassword() {
        $core = $this->createMock(Core::class);
        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $this->assertFalse($pam->authenticate());
    }

    public function testInvalidUserId() {
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getSubmittyUser')->willReturn(null);
        $core = $this->createMock(Core::class);
        $core->method('getQueries')->willReturn($queries);
        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $pam->setPassword('test');
        $this->assertFalse($pam->authenticate());
    }

    /**
     * @expectedException \app\exceptions\AuthenticationException
     * @expectedExceptionMessage Error attempting to authenticate against PAM: Invalid HTTP Code 0.
     */
    public function testCurlThrow() {
        $config = $this->createMockModel(Config::class);
        $config->method('getSubmittyPath')->willReturn($this->tmp_path);
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getSubmittyUser')->willReturn(true);
        $core = $this->createMock(Core::class);
        $core->method('getConfig')->willReturn($config);
        $core->method('getQueries')->willReturn($queries);
        $ch = curl_init();
        $core->method('curlRequest')->willThrowException(new CurlException($ch, ''));
        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $pam->setPassword('test');
        $pam->authenticate();
    }

    /**
     * @expectedException \app\exceptions\AuthenticationException
     * @expectedExceptionMessage Could not create tmp user PAM file.
     */
    public function testCannotCreateFile() {
        chmod(FileUtils::joinPaths($this->tmp_path, 'tmp', 'pam'), 0555);
        $config = $this->createMockModel(Config::class);
        $config->method('getSubmittyPath')->willReturn($this->tmp_path);
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getSubmittyUser')->willReturn(true);
        $core = $this->createMock(Core::class);
        $core->method('getConfig')->willReturn($config);
        $core->method('getQueries')->willReturn($queries);
        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $pam->setPassword('test');
        $pam->authenticate();
    }

    /**
     * @expectedException \app\exceptions\AuthenticationException
     * @expectedExceptionMessage Error JSON response for PAM: Syntax error
     */
    public function testInvalidJsonResponse() {
        $core = $this->getMockCore('{invalid_json: true}');

        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $pam->setPassword('test');
        $pam->authenticate();
    }

    /**
     * @expectedException \app\exceptions\AuthenticationException
     * @expectedExceptionMessage Missing response in JSON for PAM
     */
    public function testNoAuthenticatedKey() {
        $core = $this->getMockCore('{"key": true}');

        /** @noinspection PhpParamsInspection */
        $pam = new PamAuthentication($core);
        $pam->setUserId('test');
        $pam->setPassword('test');
        $pam->authenticate();
    }
}
