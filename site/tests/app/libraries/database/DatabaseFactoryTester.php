<?php

namespace tests\app\libraries\database;

use app\libraries\database\DatabaseFactory;
use app\libraries\database\DatabaseQueries;
use app\libraries\database\PostgresqlDatabase;
use app\libraries\database\SqliteDatabase;
use tests\BaseUnitTest;

class DatabaseFactoryTester extends BaseUnitTest {
    public function postgresqlDrivers() {
        return [
            ['pgsql'],
            ['postgresql'],
            ['postgres']
        ];
    }

    /**
     * @param string $driver
     * @dataProvider postgresqlDrivers
     */
    public function testDatabaseFactoryPostgresql($driver) {
        $factory = new DatabaseFactory($driver);
        $this->assertInstanceOf(PostgresqlDatabase::class, $factory->getDatabase([]));
        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(DatabaseQueries::class, $factory->getQueries($this->createMockCore()));
    }

    public function testDatabaseFactorySqlite() {
        $factory = new DatabaseFactory('sqlite');
        $this->assertInstanceOf(SqliteDatabase::class, $factory->getDatabase([]));
        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(DatabaseQueries::class, $factory->getQueries($this->createMockCore()));
    }

    public function testDatabaseFactoryInvalid() {
         $factory = new DatabaseFactory('invalid');
         $this->expectException(\app\exceptions\NotImplementedException::class);
         $this->expectExceptionMessage('Database not implemented for driver: invalid');
         $factory->getDatabase([]);
    }
}
