<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\ElectronicGraderController;
use app\models\gradeable\Gradeable;
use tests\BaseUnitTest;

class ElectronicGraderControllerTester extends BaseUnitTest {
    /**
     * Helper method to generate a gradeable.
     *
     * @param $upper_clamp
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockGradeable() {
        $gradeable = $this->createMockModel(Gradeable::class);
        return $gradeable;
    }
    public function testRandomizePeers() {
        $_POST['number_to_grade'] = 1000000;
        $_POST['gradeable_id'] = 'test';
        $gradeable = $this->createMockGradeable();
        $core = $this->createMockCore(['semester' => 'f18', 'course' => 'test']);
        $controller = new ElectronicGraderController($core);
        $response = $controller->RandomizePeers()->json_response->json;
        $this->assertEquals(['status' => 'success'], $response);
       // $this->assertTrue(true,true);
    }
}