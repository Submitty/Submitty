<?php

namespace tests\app\controllers;

use app\controllers\ApiTokenController;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\TokenManager;
use tests\BaseUnitTest;

class ApiTokenControllerTester extends BaseUnitTest {
    public function setUp(): void {
        parent::setUp();

        TokenManager::initialize("secret", "https://submitty.org");
    }

    public function testRetrievingApiToken() {
        $core = $this->createMockCore([], [], [
            "getSubmittyUserApiKey" => "user_api_key",
            "refreshUserApiKey" => null
        ]);

        $controller = new ApiTokenController($core);

        $response = $controller->fetchApiToken();

        $this->assertIsArray($response->parameters);
        $this->assertSame(1, count($response->parameters));

        $token_string = $response->parameters[0];

        $token = TokenManager::parseApiToken($token_string);

        $this->assertSame("user_api_key", $token->claims()->get("api_key"));

        $this->assertMethodCalled("refreshUserApiKey");
        $this->assertMethodCalled("getSubmittyUserApiKey");
        $this->assertInstanceOf(WebResponse::class, $response);
    }

    public function testInvalidatingApiToken() {
        $core = $this->createMockCore([], [], [
            "refreshUserApiKey" => null
        ]);

        $controller = new ApiTokenController($core);

        $response = $controller->invalidateApiToken();

        $this->assertMethodCalled("refreshUserApiKey");
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}
