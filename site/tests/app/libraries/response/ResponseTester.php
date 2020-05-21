<?php

declare(strict_types=1);

namespace tests\app\libraries\response;

use app\libraries\Core;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\models\Config;
use PHPUnit\Framework\TestCase;

class ResponseTester extends TestCase {
    /** @var Core */
    private $core;

    public function setUp(): void {
        $this->core = new Core();
        $this->core->setTesting(true);
        $config = new Config($this->core);
        $config->setBaseUrl('http://example.com');
        $config->setDebug(true);
        $this->core->setConfig($config);
        $this->core->getOutput()->loadTwig();
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
    }

    private function validateWebResponse(): void {
        $expected = <<<'EOD'
<div class="content">
    You don't have access to this page. <br />
    Reason: <b>You&nbsp;don't&nbsp;have&nbsp;access&nbsp;to&nbsp;this&nbsp;page.</b><br />
    Please contact system administrators if you believe this is a mistake.<br />
    Click <a href = "http://example.com"> here </a> to back to homepage and see your courses list.
</div>

EOD;
        $this->assertEquals($expected, $this->core->getOutput()->getOutput());
    }

    private function validateJsonResponse(): void {
        $expected = <<<'EOD'
{
    "status": "success",
    "data": {
        "test": true
    }
}
EOD;
        $this->assertEquals($expected, $this->core->getOutput()->getOutput());
    }

    private function validateRedirectResponse(): void {
        $this->assertEquals(302, http_response_code());
    }

    public function testwebOnlyResponse() {
        $web_response = new WebResponse("Error", "errorPage", "You don't have access to this page.");
        $response = MultiResponse::webOnlyResponse($web_response);
        $this->assertNull($response->json_response);
        $this->assertNull($response->redirect_response);
        $this->assertEquals($web_response, $response->web_response);
        $response->render($this->core);
        $this->validateWebResponse();
    }

    public function testJsonOnlyResponse() {
        $json_response = JsonResponse::getSuccessResponse(['test' => true]);
        $response = MultiResponse::JsonOnlyResponse($json_response);
        $this->assertNull($response->web_response);
        $this->assertNull($response->redirect_response);
        $this->assertEquals($json_response, $response->json_response);
        $response->render($this->core);
        $this->validateJsonResponse();
    }

    /**
     * @runInSeparateProcess
     */
    public function testRedirectOnlyResponse() {
        $redirect_response = new RedirectResponse('http://example.com');
        $response = MultiResponse::RedirectOnlyResponse($redirect_response);
        $this->assertNull($response->web_response);
        $this->assertNull($response->json_response);
        $this->assertEquals($redirect_response, $response->redirect_response);
        $response->render($this->core);
        $this->validateRedirectResponse();
    }

    /**
     * @runInSeparateProcess
     */
    public function testWebAndRedirectResponseUsesRedirect() {
        $web_response = new WebResponse("Error", "errorPage", "You don't have access to this page.");
        $redirect_response = new RedirectResponse('http://example.com');
        $response = new MultiResponse(null, $web_response, $redirect_response);
        $response->render($this->core);
        $this->validateRedirectResponse();
    }

    public function testWebAndJsonResponseUsesWeb() {
        $web_response = new WebResponse("Error", "errorPage", "You don't have access to this page.");
        $json_response = JsonResponse::getSuccessResponse(['test' => true]);
        $response = new MultiResponse($json_response, $web_response, null);
        $response->render($this->core);
        $this->validateWebResponse();
    }

    /**
     * @runInSeparateProcess
     */
    public function testRedirectAndJsonResponseUsesRedirect() {
        $redirect_response = new RedirectResponse('http://example.com');
        $json_response = JsonResponse::getSuccessResponse(['test' => true]);
        $response = new MultiResponse($json_response, null, $redirect_response);
        $response->render($this->core);
        $this->validateRedirectResponse();
    }

    public function testWebAndJsonResponseUsesJsonIfNoRender() {
        $this->core->getOutput()->disableRender();
        $web_response = new WebResponse("Error", "errorPage", "You don't have access to this page.");
        $json_response = JsonResponse::getSuccessResponse(['test' => true]);
        $response = new MultiResponse($json_response, $web_response, null);
        $response->render($this->core);
        $this->validateJsonResponse();
    }

    public function testRedirectAndJsonResponseUsesJsonIfNoRender() {
        $this->core->getOutput()->disableRender();
        $redirect_response = new RedirectResponse('http://example.com');
        $json_response = JsonResponse::getSuccessResponse(['test' => true]);
        $response = new MultiResponse($json_response, null, $redirect_response);
        $response->render($this->core);
        $this->validateJsonResponse();
    }
}
