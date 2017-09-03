<?php

namespace tests\unitTests\app\libraries\database;

use app\libraries\database\DatabaseFactory;
use app\libraries\database\DatabaseQueries;
use app\libraries\database\PostgresqlDatabase;
use app\libraries\database\PostgresqlDatabaseQueries;
use app\libraries\database\SqliteDatabase;
use tests\unitTests\BaseUnitTest;

class DatabaseFactoryTester extends BaseUnitTest {
    public function postgresqlDrivers() {
        return array(
            array('pgsql'),
            array('postgresql'),
            array('postgres')
        );
    }

    /**
     * @param string $driver
     * @dataProvider postgresqlDrivers
     */
    public function testDatabaseFactoryPostgresql($driver) {
        $factory = new DatabaseFactory($driver);
        $this->assertInstanceOf(PostgresqlDatabase::class, $factory->getDatabase(array()));
        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(PostgresqlDatabaseQueries::class, $factory->getQueries($this->createMockCore()));
    }

    public function testDatabaseFactorySqlite() {
        $factory = new DatabaseFactory('sqlite');
        $this->assertInstanceOf(SqliteDatabase::class, $factory->getDatabase(array()));
        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(DatabaseQueries::class, $factory->getQueries($this->createMockCore()));
    }

    /**
     * @expectedException \app\exceptions\NotImplementedException
     * @expectedExceptionMessage Database not implemented for driver: invalid
     */
    public function testDatabaseFactoryInvalid() {
         $factory = new DatabaseFactory('invalid');
         $factory->getDatabase(array());
    }
}
