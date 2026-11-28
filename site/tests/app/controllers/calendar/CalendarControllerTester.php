<?php

declare(strict_types=1);

namespace tests\app\controllers\calendar;

use app\controllers\calendar\CalendarController;
use tests\BaseUnitTest;

class CalendarControllerTester extends BaseUnitTest {
    public function testResetFeedLink(): void {
        $core = $this->createMockCore(['base_url' => 'http://example.com/'], [], ['refreshUserApiKey' => null]);
        $core->method('buildUrl')->willReturn('http://example.com/calendar');
        $response = (new CalendarController($core))->resetFeedLink();
        $this->assertMethodCalled('refreshUserApiKey');
        $this->assertSame('http://example.com/calendar', $response->url);
    }
}
