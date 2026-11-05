<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\GradingClusterController;
use app\entities\grading_cluster\GradingClusterConfig;
use app\libraries\Core;
use app\models\gradeable\Gradeable;
use app\repositories\grading_cluster\GradingClusterConfigRepository;
use phpmock\phpunit\PHPMock;
use tests\BaseUnitTest;

class GradingClusterControllerTester extends BaseUnitTest {
    use PHPMock;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::defineFunctionMock('app\\controllers\\grading', 'file_exists');
    }

    public function setUp(): void {
        parent::setUp();
        $_POST = [];
    }

    public function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    public function testDeleteClusteringMissingCsrfToken(): void {
        $core = $this->createMockCore();
        $repository = $this->mockDeleteRepository($core);
        $repository->expects($this->never())->method('deleteByGradeableId');

        $controller = new GradingClusterController($core);
        $response = $controller->deleteClustering('test_gradeable');

        $this->assertSame('error', $response->json['status']);
        $this->assertSame('Invalid CSRF token.', $response->json['message']);
    }

    public function testDeleteClusteringInvalidCsrfToken(): void {
        $core = $this->createMockCore(['csrf_token' => false]);
        $repository = $this->mockDeleteRepository($core);
        $repository->expects($this->never())->method('deleteByGradeableId');
        $_POST['csrf_token'] = 'invalid';

        $controller = new GradingClusterController($core);
        $response = $controller->deleteClustering('test_gradeable');

        $this->assertSame('error', $response->json['status']);
        $this->assertSame('Invalid CSRF token.', $response->json['message']);
    }

    public function testDeleteClusteringInvalidGradeable(): void {
        $core = $this->createMockCore();
        $repository = $this->mockDeleteRepository($core);
        $repository->expects($this->never())->method('deleteByGradeableId');
        $_POST['csrf_token'] = 'valid';
        $controller = $this->createDeleteController($core, true, false);

        $response = $controller->deleteClustering('test_gradeable');

        $this->assertSame('error', $response->json['status']);
        $this->assertSame('Invalid gradeable_id parameter.', $response->json['message']);
    }

    public function testDeleteClusteringClusteringDisabled(): void {
        $core = $this->createMockCore();
        $repository = $this->mockDeleteRepository($core);
        $repository->expects($this->never())->method('deleteByGradeableId');
        $_POST['csrf_token'] = 'valid';
        $controller = $this->createDeleteController($core, false);

        $response = $controller->deleteClustering('test_gradeable');

        $this->assertSame('error', $response->json['status']);
        $this->assertSame('Clustering is not enabled for this gradeable.', $response->json['message']);
    }

    /**
     * @dataProvider activeClusteringJobProvider
     */
    public function testDeleteClusteringActiveJob(string $prefix): void {
        $core = $this->createMockCore([
            'semester' => 'f22',
            'course' => 'sample',
            'tmp_path' => '/var/local/submitty',
        ]);
        $repository = $this->mockDeleteRepository($core);
        $repository->expects($this->never())->method('deleteByGradeableId');
        $_POST['csrf_token'] = 'valid';
        $controller = $this->createDeleteController($core);

        $queue_path = '/var/local/submitty/daemon_job_queue/';
        $job_name = 'clustering__f22__sample__test_gradeable.json';
        $checked_paths = [];
        $this->getFunctionMock('app\\controllers\\grading', 'file_exists')
            ->expects($this->exactly($prefix === '' ? 1 : 2))
            ->willReturnCallback(function (string $path) use ($queue_path, $job_name, $prefix, &$checked_paths): bool {
                $checked_paths[] = $path;
                return $path === $queue_path . $prefix . $job_name;
            });

        $response = $controller->deleteClustering('test_gradeable');

        $this->assertSame('error', $response->json['status']);
        $this->assertSame('Clusters are still being generated. Try again after the job finishes.', $response->json['message']);
        $expected_paths = [$queue_path . $job_name];
        if ($prefix !== '') {
            $expected_paths[] = $queue_path . $prefix . $job_name;
        }
        $this->assertSame($expected_paths, $checked_paths);
    }

    public static function activeClusteringJobProvider(): array {
        return [
            'queued job' => [''],
            'running job' => ['PROCESSING_'],
        ];
    }

    public function testDeleteClusteringSuccess(): void {
        $core = $this->createMockCore([
            'semester' => 'f22',
            'course' => 'sample',
            'tmp_path' => '/var/local/submitty',
        ]);
        $repository = $this->mockDeleteRepository($core);
        $repository->expects($this->once())->method('deleteByGradeableId')->with('test_gradeable');
        $_POST['csrf_token'] = 'valid';
        $controller = $this->createDeleteController($core);

        $checked_paths = [];
        $this->getFunctionMock('app\\controllers\\grading', 'file_exists')
            ->expects($this->exactly(2))
            ->willReturnCallback(function (string $path) use (&$checked_paths): bool {
                $checked_paths[] = $path;
                return false;
            });

        $response = $controller->deleteClustering('test_gradeable');

        $this->assertSame('success', $response->json['status']);
        $this->assertSame('Clusters deleted successfully.', $response->json['data']);
        $this->assertSame([
            '/var/local/submitty/daemon_job_queue/clustering__f22__sample__test_gradeable.json',
            '/var/local/submitty/daemon_job_queue/PROCESSING_clustering__f22__sample__test_gradeable.json',
        ], $checked_paths);
    }

    private function createDeleteController(Core $core, bool $clustering_enabled = true, bool $gradeable_exists = true): GradingClusterController {
        $core->getConfig()->method('isSubmissionClusteringEnabled')->willReturn($clustering_enabled);
        $gradeable = false;
        if ($gradeable_exists) {
            $gradeable = $this->createMock(Gradeable::class);
            $gradeable->method('getId')->willReturn('test_gradeable');
        }
        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $controller->method('tryGetGradeable')->with('test_gradeable', false)->willReturn($gradeable);
        return $controller;
    }

    private function mockDeleteRepository(Core $core): GradingClusterConfigRepository {
        $repository = $this->createMock(GradingClusterConfigRepository::class);
        $core->getCourseEntityManager()->method('getRepository')
            ->with(GradingClusterConfig::class)->willReturn($repository);
        return $repository;
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
        $core->getConfig()->method('isSubmissionClusteringEnabled')->willReturn(true);
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('getId')->willReturn('test_gradeable');
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
        $core->getConfig()->method('isSubmissionClusteringEnabled')->willReturn(true);
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('getId')->willReturn('test_gradeable');
        $controller->method('tryGetGradeable')->willReturn($mock_gradeable);

        $response = $controller->getClusters('test_gradeable');

        $this->assertEquals('success', $response->json['status']);
        $this->assertEquals('test_gradeable', $response->json['data']['gradeable_id']);
        $this->assertEmpty($response->json['data']['clusters']);
    }

    public function testCheckClusteringStatusDone(): void {
        $core = $this->createMockCore();
        $core->method('getConfig')->willReturn($this->createMockModel(\app\models\Config::class));
        $core->getConfig()->method('getTerm')->willReturn('f22');
        $core->getConfig()->method('getCourse')->willReturn('sample');
        $core->getConfig()->method('getSubmittyPath')->willReturn('/var/local/submitty');

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $core->getConfig()->method('isSubmissionClusteringEnabled')->willReturn(true);
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('getId')->willReturn('non_existent_gradeable_status');
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

    public function testCreateClusteringClusteringDisabled(): void {
        $core = $this->createMockCore();
        $core->method('checkCsrfToken')->willReturn(true);
        $_POST['csrf_token'] = 'valid';

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('getId')->willReturn('test_gradeable');
        $controller->method('tryGetGradeable')->willReturn($mock_gradeable);

        $core->getConfig()->method('isSubmissionClusteringEnabled')->willReturn(false);

        $response = $controller->createClustering('test_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Clustering is not enabled for this gradeable.', $response->json['message']);
    }

    public function testGetClustersClusteringDisabled(): void {
        $core = $this->createMockCore();

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('getId')->willReturn('test_gradeable');
        $controller->method('tryGetGradeable')->willReturn($mock_gradeable);

        $core->getConfig()->method('isSubmissionClusteringEnabled')->willReturn(false);

        $response = $controller->getClusters('test_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Clustering is not enabled for this gradeable.', $response->json['message']);
    }

    public function testCheckClusteringStatusClusteringDisabled(): void {
        $core = $this->createMockCore();

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $mock_gradeable = $this->createMock(\app\models\gradeable\Gradeable::class);
        $mock_gradeable->method('getId')->willReturn('test_gradeable');
        $controller->method('tryGetGradeable')->willReturn($mock_gradeable);

        $core->getConfig()->method('isSubmissionClusteringEnabled')->willReturn(false);

        $response = $controller->checkClusteringStatus('test_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Clustering is not enabled for this gradeable.', $response->json['message']);
    }

    public function testCreateClusteringInvalidGradeable(): void {
        $core = $this->createMockCore();
        $core->method('checkCsrfToken')->willReturn(true);
        $_POST['csrf_token'] = 'valid';

        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $controller->method('tryGetGradeable')->willReturn(false);

        $response = $controller->createClustering('invalid_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid gradeable_id parameter.', $response->json['message']);
    }

    public function testGetClustersInvalidGradeable(): void {
        $core = $this->createMockCore();
        $controller = $this->getMockBuilder(GradingClusterController::class)
            ->setConstructorArgs([$core])
            ->onlyMethods(['tryGetGradeable'])
            ->getMock();
        $controller->method('tryGetGradeable')->willReturn(false);

        $response = $controller->getClusters('invalid_gradeable');

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid gradeable_id parameter.', $response->json['message']);
    }
}
