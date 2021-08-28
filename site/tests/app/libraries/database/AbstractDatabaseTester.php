<?php

namespace tests\app\libraries\database;

use app\exceptions\DatabaseException;
use app\libraries\database\AbstractDatabase;
use app\libraries\database\PostgresqlDatabase;
use app\libraries\database\SqliteDatabase;
use app\libraries\FileUtils;
use PDO;

class AbstractDatabaseTester extends \PHPUnit\Framework\TestCase {
    private $queries = [
        ["CREATE TABLE test(pid integer PRIMARY KEY, tcol text NOT NULL)", []],
        ["INSERT INTO test VALUES (?, ?)", [1, 'a']],
        ["INSERT INTO test VALUES (?, ?)", [2, 'b']]
    ];

    private function setupDatabase(AbstractDatabase $database) {
        if ($database->isConnected()) {
            foreach ($this->queries as $query) {
                $database->query($query[0], $query[1]);
            }
        }
    }

    public function testGetConnection() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->assertInstanceOf(PDO::class, $database->getConnection());
    }

    public function testThrowsOnNoConnection() {
        $database = new SqliteDatabase(['memory' => true]);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Database not yet connected');
        $database->getConnection();
    }

    public function testBasicDatabaseFeatures() {
        $database = new SqliteDatabase(['memory' => true]);

        $this->assertFalse($database->isConnected());
        $database->connect();
        $this->assertTrue($database->isConnected());

        $this->setupDatabase($database);
        $this->assertEquals(1, $database->getRowCount());
        $this->assertEquals(2, $database->getLastInsertId());

        $this->assertEquals(3, $database->getQueryCount());
        $this->assertNotEmpty($database->getQueries());
        $this->assertEquals($this->queries, $database->getQueries());

        $database->query("SELECT * FROM test ORDER BY pid");
        $this->assertEquals(2, $database->getRowCount());
        $expected = [
            0 => ['pid' => 1, 'tcol' => 'a'],
            1 => ['pid' => 2, 'tcol' => 'b']
        ];
        $results = $database->rows();
        $this->assertEquals(count($expected), count($results));
        for ($i = 0; $i < count($expected); $i++) {
            $this->assertSame($expected[$i], $results[$i]);
        }

        // Test that repeated call to rows() does not affect the stored ResultSet
        $this->assertEquals($results, $database->rows());

        // And then calls to row() does reduce the ResultSet
        for ($i = 0; $i < count($expected); $i++) {
            $this->assertEquals(count($expected) - $i, count($database->rows()));
            $this->assertEquals($expected[$i], $database->row());
        }
        $this->assertEquals([], $database->row());
        $this->assertEmpty($database->rows());
        $database->disconnect();
        $this->assertFalse($database->isConnected());
    }

    public function testQueryTrim() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);
        $database->query("
SELECT * FROM test");
        $this->assertEquals(2, $database->getRowCount());
        $database->disconnect();
    }

    public function testTransactions() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);
        $this->assertFalse($database->inTransaction());
        $database->beginTransaction();
        $this->assertTrue($database->inTransaction());
        $database->query("INSERT INTO test VALUES (?, ?)", [3, 'c']);
        $database->commit();

        $database->query("SELECT * FROM test ORDER BY pid DESC");
        $this->assertEquals(3, $database->getRowCount());
        $results = $database->rows();
        $this->assertSame(['pid' => 3, 'tcol' => 'c'], $results[0]);

        $database->disconnect();
    }

    public function testBadTransaction() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);

        $database->beginTransaction();
        $database->query("INSERT INTO test VALUES (?, ?)", [3, 'c']);
        try {
            $database->query("INSERT INTO test VALUES (?, ?)", [1, 'd']);
            $this->fail('Query should have thrown DatabaseException');
        }
        catch (DatabaseException $exception) {
            // Do nothing
        }
        $database->commit();
        $database->query("SELECT * FROM test");
        $this->assertEquals(3, $database->getRowCount());

        $database->disconnect();
    }

    public function testTransactionRollback() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);

        $database->beginTransaction();
        $database->query("INSERT INTO test VALUES (?, ?)", [3, 'c']);
        $database->rollback();
        $database->query("SELECT * FROM test");
        $this->assertEquals(2, $database->getRowCount());

        $database->disconnect();
    }

    public function testTransactionCommitOnDisconnect() {
        $db = FileUtils::joinPaths(sys_get_temp_dir(), uniqid() . ".sq3");
        $database = new SqliteDatabase(['path' => $db]);
        $database->connect();
        $this->setupDatabase($database);
        $database->query("SELECT * FROM test");
        $this->assertEquals(2, count($database->rows()));
        $database->beginTransaction();
        $database->query("INSERT INTO test VALUES (?, ?)", [3, 'c']);
        $database->disconnect();
        $database = new SqliteDatabase(['path' => $db]);
        $database->connect();
        $database->query("SELECT * FROM test");
        $this->assertEquals(2, count($database->rows()));
        $database->disconnect();
        unlink($db);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testUsername() {
        $database = new SqliteDatabase(['memory' => true, 'username' => 'test']);
        $database->connect();
        $this->setupDatabase($database);
        $database->disconnect();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testUsernameAndPassword() {
        $database = new SqliteDatabase(['memory' => true, 'username' => 'test', 'password' => 'test']);
        $database->connect();
        $this->setupDatabase($database);
        $database->disconnect();
    }

    public function testPrintQueries() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $database->query("CREATE TABLE test(pid integer PRIMARY KEY, tcol text NOT NULL)");
        $database->query("INSERT INTO test VALUES (?, ?)", [1, 'a']);
        $this->assertEquals([
            "CREATE TABLE test(pid integer PRIMARY KEY, tcol text NOT NULL)",
            "INSERT INTO test VALUES ('1', 'a')"
        ], $database->getPrintQueries());
        $database->disconnect();
    }

    public function testDatabaseRowIterator() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);
        $iterator = $database->queryIterator("SELECT * FROM test ORDER BY pid");
        $expected = [0 => ['pid' => 1, 'tcol' => 'a'], 1 => ['pid' => 2, 'tcol' => 'b']];
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
        $database->disconnect();
    }

    public function testDatabaseIteratorTrim() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);
        $iterator = $database->queryIterator("
SELECT * FROM test ORDER BY pid");
        $expected = [0 => ['pid' => 1, 'tcol' => 'a'], 1 => ['pid' => 2, 'tcol' => 'b']];
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
        $database->disconnect();
    }

    public function testIteratorDatabaseException() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->expectException(\app\exceptions\DatabaseException::class);
        $this->expectExceptionMessage('SQLSTATE[HY000]: General error: 1 no such table: test');
        $database->queryIterator('SELECT * FROM test ORDER BY pid');
        $this->fail("DatabaseException should have been thrown");
    }

    public function testInsertIterator() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);
        $result = $database->queryIterator('INSERT INTO test VALUES (?, ?)', [3, 'c']);
        $this->assertTrue($result);
        $iterator = $database->queryIterator('SELECT * FROM test ORDER BY pid');
        $expected = [0 => ['pid' => 1, 'tcol' => 'a'], 1 => ['pid' => 2, 'tcol' => 'b'], 2 => ['pid' => 3, 'tcol' => 'c']];
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
    }

    public function testIteratorCallback() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);
        $result = $database->queryIterator('INSERT INTO test VALUES (?, ?)', [3, 'c']);
        $this->assertTrue($result);
        $iterator = $database->queryIterator('SELECT * FROM test ORDER BY pid', [], function ($result) {
            return ['id' => $result['pid']];
        });
        $expected = [0 => ['id' => 1], 1 => ['id' => 2], 2 => ['id' => 3]];
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
    }

    public function testCloseIterator() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->setupDatabase($database);
        $result = $database->queryIterator('INSERT INTO test VALUES (?, ?)', [3, 'c']);
        $this->assertTrue($result);
        $iterator = $database->queryIterator('SELECT * FROM test ORDER BY pid');
        $this->assertTrue($iterator->valid());
        $iterator->close();
        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->next());
    }

    public function testTransform() {
        $columns = [
            'pid' => [
                'native_type' => 'integer',
                'table' => 'test',
                'flags' => [],
                'name' => 'pid',
                'pdo_type' => \PDO::PARAM_STR
            ],
            'tcol' => [
                'native_type' => 'string',
                'table' => 'test',
                'flags' => [],
                'name' => 'tcol',
                'pdo_type' => \PDO::PARAM_STR
            ],
            'bcol' => [
                'native_type' => 'boolean',
                'table' => 'test',
                'flags' => [],
                'name' => 'bcol',
                'pdo_type' => \PDO::PARAM_STR
            ]
        ];
        $result = [
            'pid' => '1',
            'tcol' => 'a',
            'bcol' => 'true'
        ];
        $database = new SqliteDatabase();
        $result = $database->transformResult($result, $columns);
        $this->assertSame(['pid' => 1, 'tcol' => 'a', 'bcol' => true], $result);
    }

    public function testInvalidDSN() {
        $database = new PostgresqlDatabase(['dbname' => 'invalid_db']);
        $this->expectException(\app\exceptions\DatabaseException::class);
        $database->connect();
    }

    public function testBadQuery() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $this->expectException(\app\exceptions\DatabaseException::class);
        $database->query("SELECT * FROM invalid_table");
    }

    public function testAutoConvertTypes() {
        $database = new SqliteDatabase(['memory' => true]);
        $database->connect();
        $database->query("CREATE TABLE test(val_bool bit, val_date datetime)");
        $now = new \DateTime();
        $database->query("INSERT INTO test VALUES (?, ?)", [true, $now]);
        $this->assertEquals(1, $database->getRowCount());
        $database->query("SELECT * FROM test");
        $this->assertEquals($database->rows()[0]["val_bool"], true);
        $this->assertEquals($database->rows()[0]["val_date"], $now->format("Y-m-d H:i:sO"));
        $database->disconnect();
    }

    public function booleanConverts() {
        return [
            [true, 1],
            [1, 0],
            [false, 0],
            [null, 0],
            ["a", 0]
        ];
    }

    /**
     * @dataProvider booleanConverts
     *
     * @param $value
     * @param $expected
     */
    public function testConvertBooleanFalseString($value, $expected) {
        $database = new SqliteDatabase(['memory' => true]);
        $this->assertEquals($expected, $database->convertBoolean($value));
    }
}
