<?php

declare(strict_types=1);

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
        $this->assertEquals("http://example.com", $response->url);
        $this->assertEquals(302, http_response_code());
    }
}
