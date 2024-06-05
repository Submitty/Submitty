<?php

namespace tests\app\controllers;

use app\authentication\AbstractAuthentication;
use app\controllers\AuthenticationController;
use app\entities\VcsAuthToken;
use app\models\Team;
use app\models\User;
use app\models\gradeable\Gradeable;
use app\repositories\VcsAuthTokenRepository;
use tests\BaseUnitTest;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AuthenticationControllerTester extends BaseUnitTest {
    public function setUp(): void {
        // set up variables that logger needs
        $_COOKIE['submitty_token'] = 'test';
        $_SERVER['REMOTE_ADDR'] = 'test';
        $_SERVER['HTTP_USER_AGENT'] = 'test';
    }

    private function getAuthenticationCore($authenticate = false, $queries = [], $user = "") {
        $core = $this->createMockCore(['semester' => 'f18', 'course' => 'test'], null, $queries);
        $auth = $this->createMock(AbstractAuthentication::class);
        $auth->method('setUserId')->willReturn(null);
        $auth->method('setPassword')->willReturn(null);
        $auth->method('getUserId')->willReturn($user);
        $auth->method('authenticate')->willReturn($authenticate);
        $core->method('getAuthentication')->willReturn($auth);
        $core->method('authenticate')->willReturn($authenticate);
        return $core;
    }

    public function testVcsLoginMissingUserId() {
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $core = $this->createMockCore();
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Missing value for one of the fields'], $response);
    }

    public function testVcsLoginMissingPassword() {
        $_POST['user_id'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $core = $this->createMockCore();
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Missing value for one of the fields'], $response);
    }

    public function testVcsLoginMissingGradeableId() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['id'] = 'test';
        $core = $this->createMockCore();
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Missing value for one of the fields'], $response);
    }

    public function testVcsLoginMissingId() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $core = $this->createMockCore();
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Missing value for one of the fields'], $response);
    }


    public function testVcsLoginCourseNotLoaded() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $core = $this->createMockCore();
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Missing value for one of the fields'], $response);
    }

    public function testVcsLoginAuthenticationFail() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $core = $this->getAuthenticationCore();
        $repository = $this->createMock(VcsAuthTokenRepository::class);
        $repository->expects($this->once())
            ->method('getAllByUser')
            ->with($_POST['user_id'])
            ->willReturn([]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(VcsAuthToken::class)
            ->willReturn($repository);
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Could not login using that user id or password'], $response);
    }

    public function testVcsLoginUserNotInCourse() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $core = $this->getAuthenticationCore(true, ['getUserById' => null]);
        $repository = $this->createMock(VcsAuthTokenRepository::class);
        $repository->expects($this->once())
            ->method('getAllByUser')
            ->with($_POST['user_id'])
            ->willReturn([]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(VcsAuthToken::class)
            ->willReturn($repository);
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Could not find that user for that course'], $response);
    }

    public function testVcsLoginUserAccessGrading() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $user = $this->createMockModel(User::class);
        $user->method('accessFullGrading')->willReturn(true);
        $core = $this->getAuthenticationCore(true, ['getUserById' => $user]);
        $repository = $this->createMock(VcsAuthTokenRepository::class);
        $repository->expects($this->once())
            ->method('getAllByUser')
            ->with($_POST['user_id'])
            ->willReturn([]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(VcsAuthToken::class)
            ->willReturn($repository);
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(
            [
                'status' => 'success',
                'data' => [
                    'message' => 'Successfully logged in as test',
                    'authenticated' => true
                ]
            ],
            $response
        );
    }

    public function testVcsLoginNullGradeableWrongId() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'not_test';
        $user = $this->createMockModel(User::class);
        $core = $this->getAuthenticationCore(true, ['getUserById' => $user, 'getGradeableConfig' => null]);
        $repository = $this->createMock(VcsAuthTokenRepository::class);
        $repository->expects($this->once())
            ->method('getAllByUser')
            ->with($_POST['user_id'])
            ->willReturn([]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(VcsAuthToken::class)
            ->willReturn($repository);
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'This user cannot check out that repository.'
            ],
            $response
        );
    }

    public function testVcsLoginNullGradeableRightId() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $user = $this->createMockModel(User::class);
        $core = $this->getAuthenticationCore(true, ['getUserById' => $user, 'getGradeableConfig' => null]);
        $repository = $this->createMock(VcsAuthTokenRepository::class);
        $repository->expects($this->once())
            ->method('getAllByUser')
            ->with($_POST['user_id'])
            ->willReturn([]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(VcsAuthToken::class)
            ->willReturn($repository);
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(
            [
                'status' => 'success',
                'data' => [
                    'message' => 'Successfully logged in as test',
                    'authenticated' => true
                ],
            ],
            $response
        );
    }

    public function testVcsLoginTeamGradeableFail() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'test';
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('isTeamAssignment')->willReturn(true);
        $team = $this->createMockModel(Team::class);
        $team->method('hasMember')->with($this->equalTo('test'))->willReturn(false);
        $core = $this->getAuthenticationCore(
            true,
            [
                'getUserById' => $user,
                'getGradeableConfig' => $gradeable,
                'getTeamById' => $team,
            ]
        );
        $repository = $this->createMock(VcsAuthTokenRepository::class);
        $repository->expects($this->once())
            ->method('getAllByUser')
            ->with($_POST['user_id'])
            ->willReturn([]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(VcsAuthToken::class)
            ->willReturn($repository);
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'This user is not a member of that team.'
            ],
            $response
        );
    }

    public function testVcsLoginTeamGradeableSuccess() {
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $_POST['gradeable_id'] = 'test';
        $_POST['id'] = 'not_test';
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('isTeamAssignment')->willReturn(true);
        $team = $this->createMockModel(Team::class);
        $team->method('hasMember')->with($this->equalTo('test'))->willReturn(true);
        $core = $this->getAuthenticationCore(
            true,
            [
                'getUserById' => $user,
                'getGradeableConfig' => $gradeable,
                'getTeamById' => $team,
            ]
        );
        $repository = $this->createMock(VcsAuthTokenRepository::class);
        $repository->expects($this->once())
            ->method('getAllByUser')
            ->with($_POST['user_id'])
            ->willReturn([]);
        $core->getSubmittyEntityManager()
            ->expects($this->once())
            ->method('getRepository')
            ->with(VcsAuthToken::class)
            ->willReturn($repository);
        $controller = new AuthenticationController($core);
        $response = $controller->vcsLogin()->json_response->json;
        $this->assertEquals(
            [
                'status' => 'success',
                'data' => [
                    'message' => 'Successfully logged in as test',
                    'authenticated' => true
                ],
            ],
            $response
        );
    }

    public function testLoginMissingUserId() {
        unset($_POST['user_id']);
        $_POST['no_redirect'] = true;
        $_POST['password'] = 'test';
        $core = $this->getAuthenticationCore();
        $controller = new AuthenticationController($core);
        $response = $controller->checkLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Cannot leave user id or password blank'], $response);
    }

    public function testLoginMissingPassword() {
        unset($_POST['password']);
        $_POST['no_redirect'] = true;
        $_POST['user_id'] = 'test';
        $core = $this->getAuthenticationCore();
        $controller = new AuthenticationController($core);
        $response = $controller->checkLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => 'Cannot leave user id or password blank'], $response);
    }

    public function testLoginSuccess() {
        $_POST['no_redirect'] = true;
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $core = $this->getAuthenticationCore(true, [], 'test');
        $controller = new AuthenticationController($core);
        $response = $controller->checkLogin()->json_response->json;
        $this->assertEquals(
            [
                'status' => 'success',
                'data' => ['message' => "Successfully logged in as test", 'authenticated' => true]
            ],
            $response
        );
    }

    public function testLoginFailure() {
        $_POST['no_redirect'] = true;
        $_POST['user_id'] = 'test';
        $_POST['password'] = 'test';
        $core = $this->getAuthenticationCore(false);
        $controller = new AuthenticationController($core);
        $response = $controller->checkLogin()->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => "Could not login using that user id or password"], $response);
    }
}
