<?php

namespace tests\app\libraries\routers;

use app\libraries\routers\WebRouter;
use tests\BaseUnitTest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @runTestsInSeparateProcesses
 */
class WebRouterTester extends BaseUnitTest {
    public function testLogin() {
        $core = $this->createMockCore(['logged_in' => false]);
        $request = Request::create(
            "/authentication/login"
        );
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals(null, $response->redirect_response);
        $this->assertEquals("Authentication", $response->web_response->view_class);
        $this->assertEquals("loginForm", $response->web_response->view_function);
    }

    public function testLogout() {
        $_COOKIE['submitty_token'] = "test";
        $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
        $_SERVER['HTTP_USER_AGENT'] = 'test';
        $core = $this->createMockCore(['logged_in' => true]);
        $request = Request::create(
            "/authentication/logout"
        );
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals('', $_COOKIE['submitty_session']);
        $this->assertEquals($core->buildUrl(['authentication', 'login']), $response->redirect_response->url);
    }

    public function testRedirectToLoginFromCourse() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample', 'logged_in' => false]);
        $request = Request::create(
            "/courses/s19/sample"
        );
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals(
            $core->buildUrl(['authentication', 'login']) . '?old=' . urlencode($request->getUriForPath('/courses/s19/sample')),
            $response->redirect_response->url
        );
    }

    public function testRedirectToHomeFromLogin() {
        $core = $this->createMockCore(['logged_in' => true]);
        $request = Request::create(
            "/authentication/login"
        );
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals($core->buildUrl(['home']), $response->redirect_response->url);
    }

    public function testParamAttackLoggedIn() {
        $core = $this->createMockCore(['logged_in' => true]);
        $request = Request::create(
            "/authentication/login",
            "GET",
            ['_controller' => 'app\controllers\OtherController', '_method' => 'otherMethod']
        );
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals($core->buildUrl(['home']), $response->redirect_response->url);
    }

    public function testParamAttackNotLoggedIn() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample', 'logged_in' => false]);
        $request = Request::create(
            "/courses/s19/sample",
            "GET",
            ['_controller' => 'app\controllers\OtherController', '_method' => 'otherMethod']
        );
        $response = WebRouter::getWebResponse($request, $core);
        /* `\' is represented as '%5C' which in turn itself is represented as '%255C' (coz, % encodes to %25) */
        $this->assertEquals(
            $core->buildUrl(['authentication', 'login']) . '?old=' . urlencode($request->getUriForPath('/courses/s19/sample') . '?_controller=app%5Ccontrollers%5COtherController&_method=otherMethod'),
            $response->redirect_response->url
        );
    }

    /**
     * @param string $url a url that is not accessible to the user
     * @dataProvider randomUrlProvider
     */
    public function testRedirectToLoginFromRandomUrl($url) {
        $core = $this->createMockCore(['logged_in' => false]);
        $request = Request::create($url);
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals($core->buildUrl(['authentication', 'login']), $response->redirect_response->url);
    }

    /**
     * @param string $url a url that is not accessible to the user
     * @dataProvider randomUrlProvider
     */
    public function testRedirectToHomeFromRandomUrl($url) {
        $core = $this->createMockCore(['logged_in' => true]);
        $request = Request::create($url);
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals($core->buildUrl(['home']), $response->redirect_response->url);
    }

    public function randomUrlProvider() {
        return [
            ["/everywhere"],
            ["/courses/s19"],
            ["/sample"],
            ["/courses/s19/../../sample"],
            ["/../../s19/sample"],
            ["/index.php?semester=s19&course=sample"],
            ["/courses/s19/sample/random/invalid/endpoint"],
            ["/aaa?_controller=otherController&_method=otherMethod"],
            ["/authentication/check_login"],
            ["/courses/s19/sample/course_materials/upload"]
        ];
    }

    public function testNoCsrfToken() {
        $core = $this->createMockCore(['csrf_token' => false, 'logged_in' => true]);
        $request = Request::create(
            "/user_profile/change_preferred_names",
            "POST"
        );
        $_SERVER["CONTENT_LENGTH"] = 0;
        $_POST = ["foo"];
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals(
            [
                "status" => "fail",
                "message" => "Invalid CSRF token."
            ],
            $response->json_response->json
        );
        $this->assertEquals(
            $core->buildUrl(),
            $response->redirect_response->url
        );
    }

    public function testWithCsrfToken() {
        $core = $this->createMockCore(['csrf_token' => true, 'logged_in' => true]);
        $request = Request::create(
            "/home/change_username",
            "POST"
        );
        $response = WebRouter::getWebResponse($request, $core);
        $this->assertEquals(
            $core->buildUrl(['home']),
            $response->redirect_response->url
        );
    }

    /**
     * @param string $url a url to an nonexistent API endpoint
     * @dataProvider randomUrlProvider
     */
    public function testApiNotFound($url) {
        $core = $this->createMockCore(['logged_in' => true]);
        $request = Request::create(
            "/api" . $url
        );
        $response = WebRouter::getApiResponse($request, $core);
        $this->assertEquals([
            'status' => "fail",
            'message' => "Endpoint not found."
        ], $response->json_response->json);
    }

    public function testApiWrongMethod() {
        $core = $this->createMockCore(['logged_in' => true]);
        $request = Request::create(
            "/api/token"
        );
        $response = WebRouter::getApiResponse($request, $core);
        $this->assertEquals([
            'status' => "fail",
            'message' => "Method not allowed."
        ], $response->json_response->json);
    }

    public function testApiNoToken() {
        $core = $this->createMockCore(['logged_in' => false]);
        $request = Request::create(
            "/api/courses"
        );
        $response = WebRouter::getApiResponse($request, $core);
        $this->assertEquals([
            'status' => "fail",
            'message' => "Unauthenticated access. Please log in."
        ], $response->json_response->json);
    }

    public function testApiNotFaculty() {
        $core = $this->createMockCore(['logged_in' => true, 'access_faculty' => false]);
        $request = Request::create(
            "/api/courses"
        );
        $response = WebRouter::getApiResponse($request, $core);
        $this->assertEquals([
            'status' => "fail",
            'message' => "API is open to faculty only."
        ], $response->json_response->json);
    }

    public function testCheckPostMaxSize() {
        $core = $this->createMockCore(['logged_in' => true], ['access_faculty' => true]);
        $request = Request::create(
            "/api/token",
            "POST"
        );
        $web_request = Request::create(
            "/user_profile/change_preferred_names",
            "POST"
        );

        $_SERVER["CONTENT_LENGTH"] = 9000000;
        $_POST = ["test"];
        $expected = [
            "status" => "fail",
            "message" => "POST request exceeds maximum size of 8M"
        ];

        $response = WebRouter::getApiResponse($request, $core);
        $this->assertEquals($expected, $response->json_response->json);
        $response = WebRouter::getWebResponse($web_request, $core);
        $this->assertEquals($expected, $response->json_response->json);

        $_SERVER["CONTENT_LENGTH"] = 0;
        $_POST = [];
        $response = WebRouter::getApiResponse($request, $core);
        $this->assertEquals($expected, $response->json_response->json);
        $response = WebRouter::getWebResponse($web_request, $core);
        $this->assertEquals($expected, $response->json_response->json);
    }
}
