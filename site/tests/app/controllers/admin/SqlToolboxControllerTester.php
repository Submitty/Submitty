<?php

namespace tests\app\controllers\admin;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\controllers\admin\SqlToolboxController;
use app\exceptions\DatabaseException;
use app\libraries\database\AbstractDatabase;
use app\libraries\response\JsonResponse;
use app\views\admin\SqlToolboxView;
use PHPUnit\Framework\Exception;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\MockObject\IncompatibleReturnValueException;
use PDOException;
use SebastianBergmann\RecursionContext\InvalidArgumentException as RecursionContextInvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
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

    public function testShowToolbox() {
        $response = $this->controller->showToolbox();
        $this->assertInstanceOf(WebResponse::class, $response);
        $this->assertSame(SqlToolboxView::class, $response->view_class);
        $this->assertSame('showToolbox', $response->view_function);
        $this->assertEmpty($response->parameters);
    }

    public function testRunQuery() {
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

    public function invalidQueryDataProvider() {
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
    public function testInvalidQuery($query) {
        $_POST['sql'] = $query;
        $expected = [
        'status' => 'fail',
        'message' => 'Invalid query, can only run SELECT queries.'
        ];

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expected, $response->json);
    }

    public function testMulitpleQueryError() {
        $_POST['sql'] = 'SELECT * FROM foo; SELECT * FROM bar';
        $expected = [
        'status' => 'fail',
        'message' => 'Detected multiple queries, not running.',
        ];

        $response = $this->controller->runQuery();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($expected, $response->json);
    }

    public function testThrowDatabaseException() {
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
