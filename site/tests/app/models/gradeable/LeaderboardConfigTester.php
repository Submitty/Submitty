<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\gradeable\LeaderboardConfig;
use tests\BaseUnitTest;

class LeaderboardConfigTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    public function testGetters(): void {
        $config = new LeaderboardConfig($this->core, [
            'top_visible_students' => 5,
            'title' => 'Fastest Runtime',
            'description' => 'Ranked by lowest runtime',
            'tag' => 'runtime'
        ]);

        $this->assertEquals(5, $config->getTopVisibleStudents());
        $this->assertEquals('Fastest Runtime', $config->getTitle());
        $this->assertEquals('Ranked by lowest runtime', $config->getDescription());
        $this->assertEquals('runtime', $config->getTag());
    }

    public function testDescriptionDefaultsToEmptyString(): void {
        $config = new LeaderboardConfig($this->core, [
            'top_visible_students' => 0,
            'title' => 'All Students',
            'tag' => 'all'
        ]);

        $this->assertEquals('', $config->getDescription());
    }

    public function testEmptyDetailsThrowsException(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provided details were blank or null');
        new LeaderboardConfig($this->core, []);
    }
}
