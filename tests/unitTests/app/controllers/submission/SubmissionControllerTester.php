<?php

namespace tests\unitTests\app\controllers\submission;

use app\controllers\student\SubmissionController;
use app\models\Gradeable;
use app\models\GradeableList;
use tests\unitTests\BaseUnitTest;

class SubmissionControllerTester extends BaseUnitTest {
    public function testUploadZipBomb() {
        $_REQUEST['action'] = 'upload';
        $_POST['csrf_token'] = null;
        $_REQUEST['gradeable_id'] = 'test';

        $core = $this->mockCore();

        $gradeable = $this->createMock(Gradeable::class);
        $g_list = $this->createMock(GradeableList::class);
        $g_list->method('getSubmittableElectronicGradeables')->willReturn(array('test' => $gradeable));
        $core->method('loadModel')->willReturn($g_list);
        /** @noinspection PhpParamsInspection */
        $controller = new SubmissionController($core);
        $return = $controller->run();

        $this->assertFalse($return['error'], "Error: ".$return['message']);
        $this->assertTrue($return['success']);
    }
}