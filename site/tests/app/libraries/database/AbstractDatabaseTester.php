<?php

namespace tests\app\libraries\database;

use app\exceptions\DatabaseException;
use app\libraries\database\AbstractDatabase;
use app\libraries\database\PostgresqlDatabase;
use app\libraries\database\SqliteDatabase;
use app\libraries\FileUtils;

class AbstractDatabaseTester extends \PHPUnit\Framework\TestCase {
    private $queries = array(
        array("CREATE TABLE test(pid integer PRIMARY KEY, tcol text NOT NULL)", array()),
        array("INSERT INTO test VALUES (?, ?)", array(1, 'a')),
        array("INSERT INTO test VALUES (?, ?)", array(2, 'b'))
    );

    /**
     * @param AbstractDatabase $database
     */
    private function setupDatabase($database) {
        if ($database->isConnected()) {
            foreach ($this->queries as $query) {
                $database->query($query[0], $query[1]);
            }
        }
    }

    public function testBasicDatabaseFeatures() {
        $database = new SqliteDatabase(array('memory' => true));

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
        $expected = array(
            0 => array('pid' => 1, 'tcol' => 'a'),
            1 => array('pid' => 2, 'tcol' => 'b')
        );
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
        $this->assertEquals(array(), $database->row());
        $this->assertEmpty($database->rows());
        $database->disconnect();
        $this->assertFalse($database->isConnected());
    }

    public function testQueryTrim() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);
        $database->query("
SELECT * FROM test");
        $this->assertEquals(2, $database->getRowCount());
        $database->disconnect();
    }

    public function testTransactions() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);
        $this->assertFalse($database->inTransaction());
        $database->beginTransaction();
        $this->assertTrue($database->inTransaction());
        $database->query("INSERT INTO test VALUES (?, ?)", array(3, 'c'));
        $database->commit();

        $database->query("SELECT * FROM test ORDER BY pid DESC");
        $this->assertEquals(3, $database->getRowCount());
        $results = $database->rows();
        $this->assertSame(array('pid' => 3, 'tcol' => 'c'), $results[0]);

        $database->disconnect();
    }

    public function testBadTransaction() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);

        $database->beginTransaction();
        $database->query("INSERT INTO test VALUES (?, ?)", array(3, 'c'));
        try {
            $database->query("INSERT INTO test VALUES (?, ?)", array(1, 'd'));
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
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);

        $database->beginTransaction();
        $database->query("INSERT INTO test VALUES (?, ?)", array(3, 'c'));
        $database->rollback();
        $database->query("SELECT * FROM test");
        $this->assertEquals(2, $database->getRowCount());

        $database->disconnect();
    }

    public function testTransactionCommitOnDisconnect() {
        $db = FileUtils::joinPaths(sys_get_temp_dir(), uniqid().".sq3");
        $database = new SqliteDatabase(array('path' => $db));
        $database->connect();
        $this->setupDatabase($database);
        $database->query("SELECT * FROM test");
        $this->assertEquals(2, count($database->rows()));
        $database->beginTransaction();
        $database->query("INSERT INTO test VALUES (?, ?)", array(3, 'c'));
        $database->disconnect();
        $database = new SqliteDatabase(array('path' => $db));
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
        $database = new SqliteDatabase(array('memory' => true, 'username' => 'test'));
        $database->connect();
        $this->setupDatabase($database);
        $database->disconnect();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testUsernameAndPassword() {
        $database = new SqliteDatabase(array('memory' => true, 'username' => 'test', 'password' => 'test'));
        $database->connect();
        $this->setupDatabase($database);
        $database->disconnect();
    }

    public function testPrintQueries() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $database->query("CREATE TABLE test(pid integer PRIMARY KEY, tcol text NOT NULL)");
        $database->query("INSERT INTO test VALUES (?, ?)", array(1, 'a'));
        $this->assertEquals("1) CREATE TABLE test(pid integer PRIMARY KEY, tcol text NOT NULL)<br />2) INSERT INTO test VALUES ('1', 'a')<br />", $database->getPrintQueries());
        $database->disconnect();
    }

    public function testDatabaseRowIterator() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);
        $iterator = $database->queryIterator("SELECT * FROM test ORDER BY pid");
        $expected = array(0 => array('pid' => 1, 'tcol' => 'a'), 1 => array('pid' => 2, 'tcol' => 'b'));
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
        $database->disconnect();
    }

    public function testDatabaseIteratorTrim() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);
        $iterator = $database->queryIterator("
SELECT * FROM test ORDER BY pid");
        $expected = array(0 => array('pid' => 1, 'tcol' => 'a'), 1 => array('pid' => 2, 'tcol' => 'b'));
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
        $database->disconnect();
    }

    /**
     * @expectedException \app\exceptions\DatabaseException
     * @expectedExceptionMessage SQLSTATE[HY000]: General error: 1 no such table: test
     */
    public function testIteratorDatabaseException() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $database->queryIterator('SELECT * FROM test ORDER BY pid');
        $this->fail("DatabaseException should have been thrown");
    }

    public function testInsertIterator() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);
        $result = $database->queryIterator('INSERT INTO test VALUES (?, ?)', array(3, 'c'));
        $this->assertTrue($result);
        $iterator = $database->queryIterator('SELECT * FROM test ORDER BY pid');
        $expected = array(0 => array('pid' => 1, 'tcol' => 'a'), 1 => array('pid' => 2, 'tcol' => 'b'), 2 => array('pid' => 3, 'tcol' => 'c'));
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
    }

    public function testIteratorCallback() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);
        $result = $database->queryIterator('INSERT INTO test VALUES (?, ?)', array(3, 'c'));
        $this->assertTrue($result);
        $iterator = $database->queryIterator('SELECT * FROM test ORDER BY pid', array(), function($result) {
            return array('id' => $result['pid']);
        });
        $expected = array(0 => array('id' => 1), 1 => array('id' => 2), 2 => array('id' => 3));
        $cnt = 0;
        foreach ($iterator as $idx => $item) {
            $this->assertSame($expected[$idx], $item);
            $cnt++;
        }
        $this->assertEquals(count($expected), $cnt);
    }

    public function testCloseIterator() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $this->setupDatabase($database);
        $result = $database->queryIterator('INSERT INTO test VALUES (?, ?)', array(3, 'c'));
        $this->assertTrue($result);
        $iterator = $database->queryIterator('SELECT * FROM test ORDER BY pid');
        $this->assertTrue($iterator->valid());
        $iterator->close();
        $this->assertFalse($iterator->valid());
        $this->assertNull($iterator->next());
    }

    public function testTransform() {
        $columns = array(
            'pid' => array(
                'native_type' => 'integer',
                'table' => 'test',
                'flags' => array(),
                'name' => 'pid',
                'pdo_type' => \PDO::PARAM_STR
            ),
            'tcol' => array(
                'native_type' => 'string',
                'table' => 'test',
                'flags' => array(),
                'name' => 'tcol',
                'pdo_type' => \PDO::PARAM_STR
            ),
            'bcol' => array(
                'native_type' => 'boolean',
                'table' => 'test',
                'flags' => array(),
                'name' => 'bcol',
                'pdo_type' => \PDO::PARAM_STR
            )
        );
        $result = array(
            'pid' => '1',
            'tcol' => 'a',
            'bcol' => 'true'
        );
        $database = new SqliteDatabase();
        $result = $database->transformResult($result, $columns);
        $this->assertSame(array('pid' => 1, 'tcol' => 'a', 'bcol' => true), $result);
    }

    /**
     * @expectedException \app\exceptions\DatabaseException
     */
    public function testInvalidDSN() {
        $database = new PostgresqlDatabase(array('dbname' => 'invalid_db'));
        $database->connect();
    }

    /**
     * @expectedException \app\exceptions\DatabaseException
     */
    public function testBadQuery() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $database->query("SELECT * FROM invalid_table");
    }

    public function booleanConverts() {
        return array(
            array(true, 1),
            array(1, 0),
            array(false, 0),
            array(null, 0),
            array("a", 0)
        );
    }

    /**
     * @dataProvider booleanConverts
     *
     * @param $value
     * @param $expected
     */
    public function testConvertBooleanFalseString($value, $expected) {
        $database = new SqliteDatabase(array('memory' => true));
        $this->assertEquals($expected, $database->convertBoolean($value));
    }
}
