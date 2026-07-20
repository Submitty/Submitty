<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\GradingClusterController;
use app\entities\grading_cluster\GradingClusterAlgorithm;
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
        $_POST['csrf_token'] = 'valid';
        // Omitting algorithm from $_POST

        $controller = new GradingClusterController($core);
        $response = $controller->createClustering('test_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid or missing algorithm parameter.', $response->json['message']);
    }

    public function testNoActiveSubmitters(): void {
        $core = $this->createMockCore();
        $_POST['csrf_token'] = 'valid';
        $_POST['algorithm'] = GradingClusterAlgorithm::DummySplit->value;

        $queries = $this->createMock(\app\libraries\database\DatabaseQueries::class);
        $queries->method('getActiveSubmittersForGradeable')->willReturn([]);
        $core->method('getQueries')->willReturn($queries);

        $em = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $core->method('getCourseEntityManager')->willReturn($em);

        $controller = new GradingClusterController($core);
        $response = $controller->createClustering('test_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('No active submissions found for this gradeable.', $response->json['message']);
    }

    public function testGetClustersEmptyConfig(): void {
        $core = $this->createMockCore();

        $em = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $repository = $this->createMock(\app\repositories\grading_cluster\GradingClusterConfigRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repository);

        $core->method('getCourseEntityManager')->willReturn($em);

        $controller = new GradingClusterController($core);
        $response = $controller->getClusters('test_gradeable');

        $this->assertEquals('success', $response->json['status']);
        $this->assertEquals('test_gradeable', $response->json['data']['gradeable_id']);
        $this->assertEmpty($response->json['data']['clusters']);
    }
}
