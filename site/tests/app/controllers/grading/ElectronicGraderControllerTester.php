<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\ElectronicGraderController;
use app\models\gradeable\Gradeable;
use app\models\GradingOrder;
use tests\BaseUnitTest;

class ElectronicGraderControllerTester extends BaseUnitTest {
    private $core;
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
    private function createMockGradingOrder() {
        $grading_order = $this->createMockModel(GradingOrder::class);
        return $grading_order;
    }
    public function testRandomizePeers() {
        $_POST['number_to_grade'] = 1000000;
        $_POST['gradeable_id'] = 'test';
        $gradeable = $this->createMockGradeable();
        $grading_order = $this->createMockGradingOrder();
        $core = $this->createMockCore();
        $controller = new ElectronicGraderController($core);
        $response = $controller->RandomizePeers('test');
       // $this->assertEquals(['status' => 'success'], $response);
       // $this->assertTrue(true,true);
    }
    public function testajaxRemoveEmpty() {
        $_POST['gradeable_id'] = 'test';
        $gradeable = $this->createMockGradeable();
        $core = $this->createMockCore();
        $controller = new ElectronicGraderController($core);
        $response = $controller->ajaxRemoveEmpty('test');
       // $this->assertEquals(['status' => 'success'], $response);
       // $this->assertTrue(true,true);
    }
}
