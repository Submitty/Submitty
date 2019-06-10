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
        $this->assertEquals("app\controllers\AuthenticationController", $router->parameters['_controller']);
        $this->assertEquals("loginForm", $router->parameters['_method']);
    }

    public function testLogout() {
        $core = $this->createMockCore();
        $request = Request::create(
            "/authentication/logout"
        );
        $router = new WebRouter($request, $core, true);
        $this->assertEquals("app\controllers\AuthenticationController", $router->parameters['_controller']);
        $this->assertEquals("logout", $router->parameters['_method']);
    }

    public function testRedirectToLoginFromCourse() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample']);
        $request = Request::create(
            "/s19/sample"
        );
        $router = new WebRouter($request, $core, false);
        $this->assertEquals("app\controllers\AuthenticationController", $router->parameters['_controller']);
        $this->assertEquals("loginForm", $router->parameters['_method']);
    }

    /* UNCOMMENT ONCE THE ROUTER IS READY */
    /*
        public function testRedirectToLoginFromEverywhere() {
            $core = $this->createMockCore();
            $request = Request::create(
                "/everywhere"
            );
            $router = new WebRouter($request, $core, false);
            $this->assertEquals("app\controllers\AuthenticationController", $router->parameters['_controller']);
            $this->assertEquals("loginForm", $router->parameters['_method']);
        }

        public function testRedirectToHomeFromEverywhere() {
            $core = $this->createMockCore();
            $request = Request::create(
                "/everywhere"
            );
            $router = new WebRouter($request, $core, true);
            $this->assertEquals("app\controllers\HomePageController", $router->parameters['_controller']);
            $this->assertEquals("showHomepage", $router->parameters['_method']);
        }
    */

    public function testNoUser() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample'], ['no_user' => true]);
        $request = Request::create(
            "/s19/sample"
        );
        $router = new WebRouter($request, $core, true);
        $this->assertEquals("app\controllers\NavigationController", $router->parameters['_controller']);
        $this->assertEquals("noAccess", $router->parameters['_method']);
    }

}