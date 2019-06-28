<?php

namespace tests\app\libraries\routers;

use app\libraries\routers\WebRouter;
use tests\BaseUnitTest;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Annotations\AnnotationRegistry;

class WebRouterTester extends BaseUnitTest {

    /**
     * Loads annotations for routers.
     */
    public static function setUpBeforeClass(): void {
        $loader = require(__DIR__.'/../../../../vendor/autoload.php');
        AnnotationRegistry::registerLoader([$loader, 'loadClass']);
    }

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

    /**
     * @param string $url a url that is not accessible to the user
     * @dataProvider randomUrlProvider
     */
    public function testRedirectToLoginFromRandomUrl($url) {
        $core = $this->createMockCore();
        $request = Request::create($url);
        $router = new WebRouter($request, $core, false);
        $this->assertEquals("app\controllers\AuthenticationController", $router->parameters['_controller']);
        $this->assertEquals("loginForm", $router->parameters['_method']);
    }

    /**
     * @param string $url a url that is not accessible to the user
     * @dataProvider randomUrlProvider
     */
    public function testRedirectToHomeFromRandomUrl($url) {
        $core = $this->createMockCore();
        $request = Request::create($url);
        $router = new WebRouter($request, $core, true);
        $this->assertEquals("app\controllers\HomePageController", $router->parameters['_controller']);
        $this->assertEquals("showHomepage", $router->parameters['_method']);
    }

    public function testNoUser() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample'], ['no_user' => true]);
        $request = Request::create(
            "/s19/sample"
        );
        $router = new WebRouter($request, $core, true);
        $this->assertEquals("app\controllers\NavigationController", $router->parameters['_controller']);
        $this->assertEquals("noAccess", $router->parameters['_method']);
    }

    public function testParamAttackNotLoggedIn() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample']);
        $request = Request::create(
            "/home",
            'GET',
            ['_controller' => 'app\controllers\OtherController', '_method' => 'otherMethod']
        );
        $router = new WebRouter($request, $core, false);
        $this->assertEquals("app\controllers\AuthenticationController", $router->parameters['_controller']);
        $this->assertEquals("loginForm", $router->parameters['_method']);
        $this->assertFalse(isset($router->controller_name));
        $this->assertFalse(isset($router->method_name));
        $router->run();
        $this->assertFalse(isset($router->parameters['_controller']));
        $this->assertFalse(isset($router->parameters['_method']));
        $this->assertEquals("app\controllers\AuthenticationController", $router->controller_name);
        $this->assertEquals("loginForm", $router->method_name);
    }

    public function testParamAttackLoggedIn() {
        $core = $this->createMockCore(
            ['semester' => 's19', 'course' => 'sample', 'access_all' => true],
            [],
            ["getUnarchivedCoursesById" => array(), "getArchivedCoursesById" => array()],
            ["canI" => true]
        );
        $request = Request::create(
            "/home",
            'GET',
            ['_controller' => 'app\controllers\OtherController', '_method' => 'otherMethod']
        );
        $router = new WebRouter($request, $core, true);
        $this->assertEquals("app\controllers\HomePageController", $router->parameters['_controller']);
        $this->assertEquals("showHomepage", $router->parameters['_method']);
        $this->assertFalse(isset($router->controller_name));
        $this->assertFalse(isset($router->method_name));
        $router->run();
        $this->assertEquals("app\controllers\OtherController", $router->parameters['_controller']);
        $this->assertEquals("otherMethod", $router->parameters['_method']);
        $this->assertEquals("app\controllers\HomePageController", $router->controller_name);
        $this->assertEquals("showHomepage", $router->method_name);
    }

    public function randomUrlProvider() {
        return [
            ["/everywhere"],
            ["/s19"],
            ["/sample"],
            ["/s19/../../sample"],
            ["/../../s19/sample"],
            ["/authentication/login"],
            ["/index.php?semester=s19&course=sample"],
            ["/s19/sample/random/invalid/endpoint"]
        ];
    }
}