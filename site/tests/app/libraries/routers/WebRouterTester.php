<?php

namespace tests\app\libraries\routers;

use app\libraries\routers\WebRouter;
use tests\BaseUnitTest;
use Symfony\Component\HttpFoundation\Request;

class WebRouterTester extends BaseUnitTest {
    public function testLogin() {
        $core = $this->createMockCore();
        $request = Request::create(
            "/authentication/login"
        );
        $router = new WebRouter($request, $core, false);
        $this->assertEquals($router->parameters['_controller'], "app\controllers\AuthenticationController");
        $this->assertEquals($router->parameters['_method'], "loginForm");
    }
}