<?php

namespace tests\app\libraries;

use app\libraries\Core;

class CoreTester extends \PHPUnit\Framework\TestCase {
    /**
     * This function should always return false unless we've mocked it so that we can bypass something that
     * is a huge pain to otherwise get around (generally writing tests in phpt).
     */
    public function testIsTesting() {
        $core = new Core();
        $this->assertFalse($core->isTesting());
    }

    public function testErrorDatabaseBeforeConfig() {
        $core = new Core();
        $this->expectException(\Exception::class);
        /** @noinspection PhpUnhandledExceptionInspection */
        $core->loadMasterDatabase();
    }
}
