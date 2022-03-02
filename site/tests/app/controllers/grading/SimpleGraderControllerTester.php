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

    public function testSaveMissingUserId() {
        $_POST['user_id'] = null;
        $core = $this->createMockCore();
        $controller = new SimpleGraderController($core);
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Did not pass in user_id'
            ],
            $response->json_response->json
        );
    }

    public function testSaveInvalidGradeable() {
        $_POST['user_id'] = 'test';
        $_POST['csrf_token'] = 'test';
        $core = $this->createMockCore(['csrf_token' => true], ['no_user' => true], ['getGradeableConfig' => null]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Invalid gradeable ID'
            ],
            $response->json_response->json
        );
    }

    public function testSaveInvalidUser() {
        $_POST['user_id'] = 'test';
        $_POST['csrf_token'] = 'test';
        $gradeable = $this->createMockModel(Gradeable::class);
        $core = $this->createMockCore(['csrf_token' => true], ['no_user' => $gradeable], ['getGradeableConfig' => true]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => 'Invalid user ID'
            ],
            $response->json_response->json
        );
    }

    public function testSaveMissingScores() {
        $_POST['user_id'] = 'test';
        $_POST['csrf_token'] = 'test';
        $_POST['scores'] = '';
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockModel(Gradeable::class);
        $core = $this->createMockCore(['csrf_token' => true], [], ['getGradeableConfig' => $gradeable, 'getUserById' => $user]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "Didn't submit any scores"
            ],
            $response->json_response->json
        );
    }

    public function testSaveNoAccess() {
        $_POST['user_id'] = 'test';
        $_POST['csrf_token'] = 'test';
        $_POST['scores'] = "123";
        $user = $this->createMockModel(User::class);
        $gradeable = $this->createMockModel(Gradeable::class);
        $core = $this->createMockCore(['csrf_token' => true], [], ['getGradeableConfig' => $gradeable, 'getUserById' => $user], ['canI' => false]);
        $controller = new SimpleGraderController($core);
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "You do not have permission to do this."
            ],
            $response->json_response->json
        );
    }

    public function testExceedUpperClamp() {
        $_POST['user_id'] = 'test';
        $_POST['csrf_token'] = 'test';
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
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "Save error: score must be a number less than the upper clamp"
            ],
            $response->json_response->json
        );
    }

    public function testStaleData() {
        $_POST['user_id'] = 'test';
        $_POST['csrf_token'] = 'test';
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
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'fail',
                'message' => "Save error: displayed stale data (1) does not match database (2)"
            ],
            $response->json_response->json
        );
    }

    public function testSuccess() {
        $_POST['user_id'] = 'test';
        $_POST['csrf_token'] = 'test';
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
        $response = $controller->save('test');
        $this->assertEquals(
            [
                'status' => 'success',
                'data' => [
                    '0' => 5
                ]
            ],
            $response->json_response->json
        );
    }
}
