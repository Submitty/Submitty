<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\GradingClusterController;
use tests\BaseUnitTest;

class GradingClusterControllerTester extends BaseUnitTest {
    public function setUp(): void {
        parent::setUp();
        $_POST = [];
    }

    public function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    public function testInvalidCsrfToken(): void {
        $core = $this->createMockCore();
        // Omitting $_POST['csrf_token'] to trigger CSRF failure

        $controller = new GradingClusterController($core);
        $response = $controller->createClustering('test_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid CSRF token.', $response->json['message']);
    }

    public function testMissingAlgorithm(): void {
        $core = $this->createMockCore();
        $core->method('checkCsrfToken')->willReturn(true);
        $_POST['csrf_token'] = 'valid';
        // Omitting algorithm from $_POST

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('isClusteringAllowed')->willReturn(true);
        $controller->method('tryGetGradeable')->willReturn($mock_gradeable);

        $response = $controller->createClustering('test_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid or missing algorithm parameter.', $response->json['message']);
    }

    public function testGetClustersEmptyConfig(): void {
        $core = $this->createMockCore();

        $em = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $repository = $this->createMock(\app\repositories\grading_cluster\GradingClusterConfigRepository::class);
        $repository->method('findWithClustersAndMembers')->willReturn(null);
        $em->method('getRepository')->willReturn($repository);

        $core->method('getCourseEntityManager')->willReturn($em);

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('isClusteringAllowed')->willReturn(true);
        $controller->method('tryGetGradeable')->willReturn($mock_gradeable);

        $response = $controller->getClusters('test_gradeable');

        $this->assertEquals('success', $response->json['status']);
        $this->assertEquals('test_gradeable', $response->json['data']['gradeable_id']);
        $this->assertEmpty($response->json['data']['clusters']);
    }

    public function testCheckClusteringStatusDone(): void {
        $core = $this->createMockCore();
        $core->method('getConfig')->willReturn($this->createMock(\app\models\Config::class));
        $core->getConfig()->method('getTerm')->willReturn('f22');
        $core->getConfig()->method('getCourse')->willReturn('sample');
        $core->getConfig()->method('getSubmittyPath')->willReturn('/var/local/submitty');

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('isClusteringAllowed')->willReturn(true);
        $controller->method('tryGetGradeable')->willReturn($mock_gradeable);

        // Use a non-existent gradeable ID so files definitely don't exist
        $response = $controller->checkClusteringStatus('non_existent_gradeable_status');

        $this->assertEquals('success', $response->json['status']);
        $this->assertEquals('done', $response->json['data']['status']);
    }

    public function testCheckClusteringStatusInvalidGradeable(): void {
        $core = $this->createMockCore();
        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $controller->method('tryGetGradeable')->willReturn(false);

        $response = $controller->checkClusteringStatus('invalid_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid gradeable_id parameter.', $response->json['message']);
    }
}
