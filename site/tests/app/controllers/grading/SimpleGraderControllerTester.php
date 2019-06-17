<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\SimpleGraderController;
use app\models\User;
use app\models\gradeable\Gradeable;
use tests\BaseUnitTest;

class SimpleGraderControllerTester extends BaseUnitTest {

    public function testSaveMissingGradeableId() {
        $_REQUEST['g_id'] = null;
        $core = $this->createMockCore();
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Did not pass in g_id or user_id'
            ],
            $response
        );
    }

    public function testSaveMissingUserId() {
        $_REQUEST['user_id'] = null;
        $core = $this->createMockCore();
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Did not pass in g_id or user_id'
            ],
            $response
        );
    }

    public function testSaveWrongCsrfToken() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $core = $this->createMockCore(['csrf_token' => false]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Invalid CSRF token'
            ],
            $response
        );
    }

    public function testSaveInvalidGradeable() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $core = $this->createMockCore(['csrf_token' => true], ['no_user' => true], ['getGradeableConfig' => null]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Invalid gradeable ID'
            ],
            $response
        );
    }

    public function testSaveInvalidUser() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $gradeable = $this->createMockModel(Gradeable::class);
        $core = $this->createMockCore(['csrf_token' => true], ['no_user' => $gradeable], ['getGradeableConfig' => true]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Invalid user ID'
            ],
            $response
        );
    }

    public function testSaveMissingScores() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $_POST['scores'] = '';
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockModel(Gradeable::class);
        $core = $this->createMockCore(['csrf_token' => true], [], ['getGradeableConfig' => $gradeable, 'getUserById' => $user]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "Didn't submit any scores"
            ],
            $response
        );
    }

    public function testSaveNoAccess() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $_POST['scores'] = "123";
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockModel(Gradeable::class);
        $core = $this->createMockCore(['csrf_token' => true], [], ['getGradeableConfig' => $gradeable, 'getUserById' => $user]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "You do not have permission to do this."
            ],
            $response
        );
    }
}
