<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\SimpleGraderController;
use app\models\User;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\TaGradedGradeable;
use app\models\gradeable\Component;
use app\models\gradeable\GradedComponent;
use tests\BaseUnitTest;

class SimpleGraderControllerTester extends BaseUnitTest {

    /**
     * Helper method to generate a gradeable.
     *
     * @param $upper_clamp
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockGradeable($upper_clamp) {
        $gradeable = $this->createMockModel(Gradeable::class);
        $component = $this->createMockModel(Component::class);

        $gradeable->method('getComponents')->willReturn([$component]);
        $component->method('getId')->willReturn(0);
        $component->method('getUpperClamp')->willReturn($upper_clamp);

        return $gradeable;
    }

    /**
     * Helper method to generate a graded gradeable.
     *
     * @param $total_score
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockGradedGradeable($total_score) {
        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $ta_graded_gradeable = $this->createMockModel(TaGradedGradeable::class);
        $graded_component = $this->createMockModel(GradedComponent::class);

        $graded_component->method('getTotalScore')->willReturn($total_score);
        $graded_component->method('setGrader')->willReturn(true);
        $graded_component->method('setScore')->willReturn(true);
        $graded_component->method('setGradeTime')->willReturn(true);
        $graded_gradeable->method('getOrCreateTaGradedGradeable')->willReturn($ta_graded_gradeable);
        $ta_graded_gradeable->method('getOrCreateGradedComponent')->willReturn($graded_component);
        $ta_graded_gradeable->method('setOverallComment')->willReturn(true);

        return $graded_gradeable;
    }

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
        $core = $this->createMockCore(['csrf_token' => true], [], ['getGradeableConfig' => $gradeable, 'getUserById' => $user], ['canI' => false]);
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

    public function testExceedUpperClamp() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $_POST['scores'] = [5];
        $_POST['old_scores'] = [1];
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockGradeable(4);
        $graded_gradeable = $this->createMockGradedGradeable(1);
        $core = $this->createMockCore(
            ['csrf_token' => true],
            [],
            ['getGradeableConfig' => $gradeable, 'getUserById' => $user, 'getGradedGradeable' => $graded_gradeable],
            ['canI' => true]
        );
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "Save error: score must be a number less than the upper clamp"
            ],
            $response
        );
    }

    public function testStaleData() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $_POST['scores'] = [5];
        $_POST['old_scores'] = [1];
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockGradeable(6);
        $graded_gradeable = $this->createMockGradedGradeable(2);
        $core = $this->createMockCore(
            ['csrf_token' => true],
            [],
            ['getGradeableConfig' => $gradeable, 'getUserById' => $user, 'getGradedGradeable' => $graded_gradeable],
            ['canI' => true]
        );
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "Save error: displayed stale data (1) does not match database (2)"
            ],
            $response
        );
    }

    public function testSuccess() {
        $_REQUEST['g_id'] = 'test';
        $_REQUEST['user_id'] = 'test';
        $_REQUEST['csrf_token'] = 'test';
        $_POST['scores'] = [5];
        $_POST['old_scores'] = [2];
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockGradeable(6);
        $graded_gradeable = $this->createMockGradedGradeable(2);
        $core = $this->createMockCore(
            ['csrf_token' => true],
            [],
            ['getGradeableConfig' => $gradeable, 'getUserById' => $user, 'getGradedGradeable' => $graded_gradeable],
            ['canI' => true]
        );
        $controller = new SimpleGraderController($core);
        $response = $controller->save();
        $this->assertEquals(
            [
                'status' => 'success',
                'data' => null
            ],
            $response
        );
    }
}
