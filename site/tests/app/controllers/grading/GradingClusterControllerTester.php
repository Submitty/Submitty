<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\GradingClusterController;
use app\entities\grading_cluster\GradingCluster;
use app\entities\grading_cluster\GradingClusterAlgorithm;
use app\entities\grading_cluster\GradingClusterConfig;
use app\libraries\Core;
use app\models\gradeable\Gradeable;
use app\repositories\grading_cluster\GradingClusterRepository;
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

    public function testRenameClusterMissingCsrfToken(): void {
        $core = $this->createMockCore();
        $core->getCourseEntityManager()->expects($this->never())->method('flush');

        $controller = new GradingClusterController($core);
        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid CSRF token.', $response->json['message']);
    }

    public function testRenameClusterInvalidCsrfToken(): void {
        $core = $this->createMockCore(['csrf_token' => false]);
        $core->getCourseEntityManager()->expects($this->never())->method('flush');
        $_POST = ['csrf_token' => 'invalid', 'cluster_name' => 'New name'];

        $controller = new GradingClusterController($core);
        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid CSRF token.', $response->json['message']);
    }

    public function testRenameClusterInvalidGradeable(): void {
        $core = $this->createMockCore();
        $core->getCourseEntityManager()->expects($this->never())->method('flush');
        $_POST = ['csrf_token' => 'valid', 'cluster_name' => 'New name'];
        $controller = $this->createRenameController($core, true, false);

        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid gradeable_id parameter.', $response->json['message']);
    }

    public function testRenameClusterClusteringDisabled(): void {
        $core = $this->createMockCore();
        $core->getCourseEntityManager()->expects($this->never())->method('flush');
        $_POST = ['csrf_token' => 'valid', 'cluster_name' => 'New name'];
        $controller = $this->createRenameController($core, false);

        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Clustering is not enabled for this gradeable.', $response->json['message']);
    }

    /**
     * @dataProvider invalidClusterNameProvider
     */
    public function testRenameClusterInvalidName(?string $name, string $message): void {
        $core = $this->createMockCore();
        $entity_manager = $core->getCourseEntityManager();
        $entity_manager->expects($this->never())->method('getRepository');
        $entity_manager->expects($this->never())->method('flush');
        $_POST['csrf_token'] = 'valid';
        if ($name !== null) {
            $_POST['cluster_name'] = $name;
        }
        $controller = $this->createRenameController($core);

        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals($message, $response->json['message']);
    }

    public static function invalidClusterNameProvider(): array {
        return [
            'missing' => [null, 'Cluster name cannot be empty.'],
            'empty' => ['', 'Cluster name cannot be empty.'],
            'whitespace' => [" \t\n ", 'Cluster name cannot be empty.'],
            'too long' => [str_repeat('A', 256), 'Cluster name cannot exceed 255 characters.'],
        ];
    }

    /**
     * @dataProvider invalidClusterProvider
     */
    public function testRenameClusterInvalidCluster(?string $gradeable_id): void {
        $core = $this->createMockCore();
        $core->getCourseEntityManager()->expects($this->never())->method('flush');
        $cluster = null;
        if ($gradeable_id !== null) {
            $config = new GradingClusterConfig($gradeable_id, GradingClusterAlgorithm::DummySplit);
            $cluster = $this->createCluster($config, 12, 'Cluster A');
        }
        $this->mockClusterLookup($core, $cluster);
        $_POST = ['csrf_token' => 'valid', 'cluster_name' => 'New name'];
        $controller = $this->createRenameController($core);

        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Invalid cluster.', $response->json['message']);
        if ($cluster !== null) {
            $this->assertEquals('Cluster A', $cluster->getClusterName());
        }
    }

    public static function invalidClusterProvider(): array {
        return [
            'not found' => [null],
            'another gradeable' => ['other_gradeable'],
        ];
    }

    /**
     * @dataProvider reservedClusterNameProvider
     */
    public function testRenameClusterReservedName(string $name): void {
        $core = $this->createMockCore();
        $core->getCourseEntityManager()->expects($this->never())->method('flush');
        $config = new GradingClusterConfig('test_gradeable', GradingClusterAlgorithm::DummySplit);
        $cluster = $this->createCluster($config, 12, 'Cluster A');
        $this->mockClusterLookup($core, $cluster);
        $_POST = ['csrf_token' => 'valid', 'cluster_name' => $name];
        $controller = $this->createRenameController($core);

        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('This cluster name is reserved.', $response->json['message']);
        $this->assertEquals('Cluster A', $cluster->getClusterName());
    }

    public static function reservedClusterNameProvider(): array {
        return [
            ['Unclustered'],
            ['unclustered'],
            ['UNCLUSTERED'],
            ['  Unclustered  '],
            ['NULL'],
            ['null'],
            ['NuLl'],
        ];
    }

    /**
     * @dataProvider duplicateClusterNameProvider
     */
    public function testRenameClusterDuplicateName(string $name): void {
        $core = $this->createMockCore();
        $core->getCourseEntityManager()->expects($this->never())->method('flush');
        $config = new GradingClusterConfig('test_gradeable', GradingClusterAlgorithm::DummySplit);
        $cluster = $this->createCluster($config, 12, 'Cluster A');
        $this->createCluster($config, 13, 'Cluster B');
        $this->mockClusterLookup($core, $cluster);
        $_POST = ['csrf_token' => 'valid', 'cluster_name' => $name];
        $controller = $this->createRenameController($core);

        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('error', $response->json['status']);
        $this->assertEquals('Another cluster already uses this name.', $response->json['message']);
        $this->assertEquals('Cluster A', $cluster->getClusterName());
    }

    public static function duplicateClusterNameProvider(): array {
        return [
            ['Cluster B'],
            ['cluster b'],
            ['CLUSTER B'],
            ['  cLuStEr B  '],
        ];
    }

    /**
     * @dataProvider validClusterNameProvider
     */
    public function testRenameClusterSuccess(string $name, string $expected_name): void {
        $core = $this->createMockCore();
        $core->getCourseEntityManager()->expects($this->once())->method('flush');
        $config = new GradingClusterConfig('test_gradeable', GradingClusterAlgorithm::DummySplit);
        $cluster = $this->createCluster($config, 12, 'Cluster A');
        $this->createCluster($config, 13, null);
        $this->mockClusterLookup($core, $cluster);
        $_POST = ['csrf_token' => 'valid', 'cluster_name' => $name];
        $controller = $this->createRenameController($core);

        $response = $controller->renameCluster('test_gradeable', 12);

        $this->assertEquals('success', $response->json['status']);
        $this->assertEquals(['cluster_id' => 12, 'cluster_name' => $expected_name], $response->json['data']);
        $this->assertEquals($expected_name, $cluster->getClusterName());
    }

    public static function validClusterNameProvider(): array {
        return [
            'new name' => ['New name', 'New name'],
            'trim whitespace' => ['  New name  ', 'New name'],
            'keep own name' => ['Cluster A', 'Cluster A'],
            'change capitalization' => ['CLUSTER A', 'CLUSTER A'],
            'maximum length' => [str_repeat('A', 255), str_repeat('A', 255)],
            'multibyte maximum length' => [str_repeat("\u{00E9}", 255), str_repeat("\u{00E9}", 255)],
        ];
    }

    private function createRenameController(Core $core, bool $clustering_enabled = true, bool $gradeable_exists = true): GradingClusterController {
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

    private function createCluster(GradingClusterConfig $config, int $id, ?string $name): GradingCluster {
        $cluster = $this->getMockBuilder(GradingCluster::class)
            ->setConstructorArgs([$config, $name])
            ->onlyMethods(['getId'])
            ->getMock();
        $cluster->method('getId')->willReturn($id);
        return $cluster;
    }

    private function mockClusterLookup(Core $core, ?GradingCluster $cluster): void {
        $repository = $this->createMock(GradingClusterRepository::class);
        $repository->expects($this->once())->method('find')->with(12)->willReturn($cluster);
        $core->getCourseEntityManager()->expects($this->once())
            ->method('getRepository')->with(GradingCluster::class)->willReturn($repository);
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
