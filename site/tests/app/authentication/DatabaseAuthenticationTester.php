<?php

namespace tests\app\authentication;

use app\authentication\DatabaseAuthentication;
use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\models\User;
use tests\BaseUnitTest;

class DatabaseAuthenticationTester extends BaseUnitTest {
    public function testNoUsername() {
        $core = $this->createMock(Core::class);
        $auth = new DatabaseAuthentication($core);
        $this->assertFalse($auth->authenticate());
    }

    public function testNoPassword() {
        $core = $this->createMock(Core::class);
        $auth = new DatabaseAuthentication($core);
        $auth->setUserId('test');
        $this->assertFalse($auth->authenticate());
    }

    public function testValidLogin() {
        $user = $this->createMockModel(User::class);
        $user->method('getPassword')->willReturn(password_hash('test', PASSWORD_DEFAULT));
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getSubmittyUser')->willReturn($user);
        $core = $this->createMock(Core::class);
        $core->method('getQueries')->willReturn($queries);

        $auth = new DatabaseAuthentication($core);
        $auth->setUserId('test');
        $this->assertEquals('test', $auth->getUser()->getId());
        $auth->setPassword('test');
        $this->assertTrue($auth->authenticate());
    }

    public function testInvalidUser() {
        $queries = $this->createMock(DatabaseQueries::class);
        $queries->method('getSubmittyUser')->willReturn(null);
        $core = $this->createMock(Core::class);
        $core->method('getQueries')->willReturn($queries);

        $auth = new DatabaseAuthentication($core);
        $auth->setUserId('test');
        $auth->setPassword('test');
        $this->assertFalse($auth->authenticate());
    }
}
