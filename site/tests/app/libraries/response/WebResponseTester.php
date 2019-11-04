<?php

declare(strict_types=1);

namespace tests\app\libraries\response;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\models\Config;
use PHPUnit\Framework\TestCase;

class WebResponseTester extends TestCase {
    public function testWebResponse() {
        $core = new Core();
        $config = new Config($core);
        $config->setBaseUrl('http://example.com');
        $config->setDebug(true);
        $core->setConfig($config);
        $core->getOutput()->loadTwig();
        $core->getOutput()->useHeader(false);
        $core->getOutput()->useFooter(false);

        $response = new WebResponse("Error", "errorPage", "You don't have access to this page.");
        $response->render($core);
        $expected = <<<'EOD'
<div class="content">
    You don't have access to this page. <br />
    Reason: <b>You&nbsp;don't&nbsp;have&nbsp;access&nbsp;to&nbsp;this&nbsp;page.</b><br />
    Please contact system administrators if you believe this is a mistake.<br />
    Click <a href = "http://example.com"> here </a> to back to homepage and see your courses list.
</div>

EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }
}
