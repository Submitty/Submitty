<?php

declare(strict_types=1);

namespace tests\app\controllers\admin;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\controllers\admin\SqlToolboxController;
use app\entities\db\Table;
use app\exceptions\DatabaseException;
use app\libraries\database\AbstractDatabase;
use app\libraries\response\JsonResponse;
use app\views\admin\SqlToolboxView;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use ReflectionClass;
use tests\BaseUnitTest;

class SqlToolboxControllerTester extends BaseUnitTest {
   /** @var SqlToolboxController */
    protected $controller;

    /** @var Core */
    protected $core;

    public function setUp(): void {
        parent::setUp();

        $this->core = new Core();

        $this->controller = new SqlToolboxController($this->core);
    }

    private function setUpDatabase(): void {
        /** @var \app\libraries\database\AbstractDatabase&\PHPUnit\Framework\MockObject\MockObject */
        $database = $this->createMock(AbstractDatabase::class);
        $database->expects($this->once())->method('beginTransaction')->with();
        $database->expects($this->once())->method('rollback')->with();
        $this->core->setCourseDatabase($database);
    }

    public function tearDown(): void {
        unset($_POST['sql']);
    }

    public function testShowToolbox(): void {
        $reflection = new ReflectionClass(Table::class);
        $tables = [
            $reflection->newInstanceWithoutConstructor(),
            $reflection->newInstanceWithoutConstructor(),
        ];
        $prop = $reflection->getProperty('name');
        $prop->setAccessible(true);
        $prop->setValue($tables[0], 'bar');
        $prop->setAccessible(false);

        $prop = $reflection->getProperty('name');
        $prop->setAccessible(true);
        $prop->setValue($tables[1], 'foo');
        $prop->setAccessible(false);

        /** @var EntityManager&\PHPUnit\Framework\MockObject\MockObject $entity_manager */
        $entity_manager = $this->createMock(EntityManager::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['schema' => 'public'], ['name' => 'ASC'])
            ->willReturn($tables);
        $entity_manager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Table::class)
            ->willReturn($repository);

        $this->core->setCourseEntityManager($entity_manager);

        $response = $this->controller->showToolbox();
        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertSame(SqlToolboxView::class, $response->view_class);
        $this->assertSame('showToolbox', $response->view_function);
        $this->assertSame($response->parameters, [$tables]);
    }

    public function testRunQuery(): void {
        $this->setUpDatabase();

        $testData = [
            [1, 'Test Person', 'foo@example.com'],
            [2, 'Bar Person', 'bar@example.com'],
        ];

        /** @var \app\libraries\database\AbstractDatabase&\PHPUnit\Framework\MockObject\MockObject */
        $courseDb = $this->core->getCourseDB();
        $courseDb
                ->expects($this->exactly(2))
                ->method('query')
                ->withConsecutive(
                    ['SELECT COUNT(*) as total FROM (SELECT * FROM foo) as count_query'],
                    ['SELECT * FROM (SELECT * FROM foo) as results LIMIT 1000']
                );
            $courseDb
                ->expects($this->exactly(2))
                ->method('rows')
                ->willReturnOnConsecutiveCalls(
                    [['total' => 2]],
                    $testData
                );
        $_POST['sql'] = ' SELECT * FROM foo; ';

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $expected = [
                'status' => 'success',
                'data' => [
                    'results' => $testData,
                    'message' => 'Showing 2 of 2 total rows.'
                ]
            ];
        $this->assertSame($expected, $response->json);
    }

    public function testRunQueryTruncated(): void {
        $this->setUpDatabase();

        $testData = [];
        for ($i = 1; $i <= 1000; $i++) {
            $testData[] = [$i, "Person {$i}", "submitty{$i}@example.com"];
        }

        $courseDb = $this->core->getCourseDB();
        $courseDb
            ->expects($this->exactly(2))
            ->method('query')
            ->withConsecutive(
                ['SELECT COUNT(*) as total FROM (SELECT * FROM foo) as count_query'],
                ['SELECT * FROM (SELECT * FROM foo) as results LIMIT 1000']
            );
        $courseDb
            ->expects($this->exactly(2))
            ->method('rows')
            ->willReturnOnConsecutiveCalls(
                [['total' => 1500]],
                $testData
            );

        $_POST['sql'] = ' SELECT * FROM foo; ';

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $expected = [
            'status' => 'success',
            'data' => [
                'results' => $testData,
                'message' => 'Output was truncated. Showing 1000 of 1500 total rows.'
            ]
        ];
        $this->assertSame($expected, $response->json);
    }

    public function invalidQueryDataProvider(): array {
        return [
            ['INSERT INTO foo VALUES (1)'],
            ['UPDATE foo SET bar=1 WHERE baz=2'],
            ['DELETE FROM foo WHERE baz=2'],
            ['DROP TABLE foo'],
            ['CREATE TABLE foo (id int)'],
        ];
    }

    /**
     * @dataProvider invalidQueryDataProvider
     */
    public function testInvalidQuery(string $query): void {
        $_POST['sql'] = $query;
        $expected = [
            'status' => 'fail',
            'message' => 'Invalid query, can only run SELECT queries.'
        ];

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expected, $response->json);
    }

    public function testMulitpleQueryError(): void {
        $_POST['sql'] = 'SELECT * FROM foo; SELECT * FROM bar';
        $expected = [
            'status' => 'fail',
            'message' => 'Detected multiple queries, not running.',
        ];

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expected, $response->json);
    }

    public function testThrowDatabaseException(): void {
        $_POST['sql'] = 'SELECT * FROM INVALID';
        $expected = [
            'status' => 'fail',
            'message' => 'Error running query: foo',
        ];

        $this->setUpDatabase();
        /** @var \app\libraries\database\AbstractDatabase&\PHPUnit\Framework\MockObject\MockObject */
        $courseDb = $this->core->getCourseDB();
            $courseDb
                ->expects($this->once())
                ->method('query')
                ->with('SELECT COUNT(*) as total FROM (SELECT * FROM INVALID) as count_query')
                ->willThrowException(new DatabaseException('foo'));
        $response = $this->controller->runQuery();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expected, $response->json);
    }
}
