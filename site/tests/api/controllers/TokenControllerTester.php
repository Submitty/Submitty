<?php

namespace tests\api\controllers;

use app\authentication\AbstractAuthentication;
use app\controllers\api\TokenController;
use app\libraries\Core;
use tests\BaseUnitTest;

class TokenControllerTester extends BaseUnitTest {
    public function setUp(): void {
        $_POST = [];
    }

    public function tearDown(): void {
        $_POST = [];
    }

    /**
     * Builds a mocked Core with a mocked Authentication attached.
     *
     * @param mixed $authenticate_jwt_return value returned by Core::authenticateJwt()
     * @param mixed $invalidate_jwt_return value returned by Core::invalidateJwt()
     */
    private function buildCore($authenticate_jwt_return = null, $invalidate_jwt_return = null): Core {
        $auth = $this->createMock(AbstractAuthentication::class);
        $auth->method('setUserId')->willReturn(null);
        $auth->method('setPassword')->willReturn(null);

        $core = $this->createMock(Core::class);
        $core->method('getAuthentication')->willReturn($auth);
        $core->method('authenticateJwt')->willReturn($authenticate_jwt_return);
        $core->method('invalidateJwt')->willReturn($invalidate_jwt_return);

        return $core;
    }

    public function testGetTokenFailsWithMissingUserId(): void {
        $core = $this->buildCore();
        $controller = new TokenController($core);

        $_POST = ['password' => 'instructor'];

        $response = $controller->getToken();

        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Cannot leave user id or password blank', $response->json_response->json['message']);
    }

    public function testGetTokenFailsWithMissingPassword(): void {
        $core = $this->buildCore();
        $controller = new TokenController($core);

        $_POST = ['user_id' => 'instructor'];

        $response = $controller->getToken();

        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Cannot leave user id or password blank', $response->json_response->json['message']);
    }

    public function testGetTokenFailsWithMissingUserIdAndPassword(): void {
        $core = $this->buildCore();
        $controller = new TokenController($core);

        $_POST = [];

        $response = $controller->getToken();

        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Cannot leave user id or password blank', $response->json_response->json['message']);
    }

    public function testGetTokenFailsWhenAuthenticationFails(): void {
        $core = $this->buildCore(false);
        $controller = new TokenController($core);

        $_POST = ['user_id' => 'instructor', 'password' => 'bad_password'];

        $response = $controller->getToken();

        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Could not login using that user id or password', $response->json_response->json['message']);
    }

    public function testGetTokenSucceeds(): void {
        $core = $this->buildCore('a.jwt.token');
        $controller = new TokenController($core);

        $_POST = ['user_id' => 'instructor', 'password' => 'instructor'];

        $response = $controller->getToken();

        $this->assertEquals('success', $response->json_response->json['status']);
        $this->assertEquals('a.jwt.token', $response->json_response->json['data']['token']);
    }

    public function testInvalidateTokenFailsWithMissingUserId(): void {
        $core = $this->buildCore();
        $controller = new TokenController($core);

        $_POST = ['password' => 'instructor'];

        $response = $controller->invalidateToken();

        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Cannot leave user id or password blank', $response->json_response->json['message']);
    }

    public function testInvalidateTokenFailsWithMissingPassword(): void {
        $core = $this->buildCore();
        $controller = new TokenController($core);

        $_POST = ['user_id' => 'instructor'];

        $response = $controller->invalidateToken();

        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Cannot leave user id or password blank', $response->json_response->json['message']);
    }

    public function testInvalidateTokenFailsWhenAuthenticationFails(): void {
        $core = $this->buildCore(null, false);
        $controller = new TokenController($core);

        $_POST = ['user_id' => 'instructor', 'password' => 'bad_password'];

        $response = $controller->invalidateToken();

        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Could not login using that user id or password', $response->json_response->json['message']);
    }

    public function testInvalidateTokenSucceeds(): void {
        $core = $this->buildCore(null, true);
        $controller = new TokenController($core);

        $_POST = ['user_id' => 'instructor', 'password' => 'instructor'];

        $response = $controller->invalidateToken();

        $this->assertEquals('success', $response->json_response->json['status']);
    }
}
