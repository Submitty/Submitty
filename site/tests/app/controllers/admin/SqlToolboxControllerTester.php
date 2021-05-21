<?php

declare(strict_types=1);

namespace tests\app\controllers\admin;

use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\libraries\response\WebResponse;
use app\controllers\admin\SqlToolboxController;
use app\exceptions\DatabaseException;
use app\libraries\database\AbstractDatabase;
use app\libraries\response\JsonResponse;
use app\views\admin\SqlToolboxView;
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
        $tables = [
        [
            'table_name' => 'gradeable',
            'column_name' => 'g_title',
            'data_type' => 'character varying'
        ],
        [
            'table_name' => 'gradeable',
            'column_name' => 'g_gradeable_type',
            'data_type' => 'integer'
        ],
        [
            'table_name' => 'threads',
            'column_name' => 'title',
            'data_type' => 'character varying'
        ],
        [
            'table_name' => 'threads',
            'column_name' => 'is_visible',
            'data_type' => 'boolean'
        ]
        ];
        $mock_queries = $this->createMock(DatabaseQueries::class);
        $mock_queries->expects($this->once())->method('getCourseSchemaTables')->willReturn($tables);
        $this->core->setQueries($mock_queries);

        $organizedTables = [
            'gradeable' => ['g_title - character varying', 'g_gradeable_type - integer'],
            'threads' => ['title - character varying', 'is_visible - boolean']
        ];

        $response = $this->controller->showToolbox();
        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertSame(SqlToolboxView::class, $response->view_class);
        $this->assertSame('showToolbox', $response->view_function);
        $this->assertSame($response->parameters, [$organizedTables]);
    }

    public function testRunQuery(): void {
        $this->setUpDatabase();

        $testData = [
            [1, 'Test Person', 'foo@example.com'],
            [2, 'Bar Person', 'bar@example.com'],
        ];

        /** @var \app\libraries\database\AbstractDatabase&\PHPUnit\Framework\MockObject\MockObject */
        $courseDb = $this->core->getCourseDB();
        $courseDb->expects($this->once())->method('query')->with('SELECT * FROM foo;');
        $courseDb->expects($this->once())->method('rows')->with()->willReturn($testData);

        $_POST['sql'] = ' SELECT * FROM foo; ';

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $expected = [
            'status' => 'success',
            'data' => $testData,
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
        $courseDb->expects($this->once())->method('query')->with('SELECT * FROM INVALID')->willThrowException(new DatabaseException('foo'));

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expected, $response->json);
    }
}
