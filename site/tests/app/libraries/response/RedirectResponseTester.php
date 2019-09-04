<?php declare(strict_types=1);

namespace tests\app\libraries\response;

use app\libraries\Core;
use app\libraries\response\RedirectResponse;
use PHPUnit\Framework\TestCase;

class RedirectResponseTester extends TestCase {
    /**
     * @runInSeparateProcess
     */
    public function testRedirectResponse() {
        $core = new Core();
        $core->setTesting(true);
        $response = new RedirectResponse('http://example.com');
        $response->render($core);
        $this->assertTrue(function_exists('xdebug_get_headers'), 'Make sure the xdebug extension is loaded');
        $headers = xdebug_get_headers();
        $this->assertCount(1, $headers);
        $this->assertEquals("Location: http://example.com", $headers[0]);
        $this->assertEquals(302, http_response_code());
    }
}
