<?php

declare(strict_types=1);

namespace tests\app\libraries\response;

use app\libraries\Core;
use app\libraries\response\JsonResponse;
use PHPUnit\Framework\TestCase;

class JsonResponseTester extends TestCase {
    public function testSuccessResponse() {
        $core = new Core();
        $response = JsonResponse::getSuccessResponse(['test' => true]);
        $response->render($core);
        $expected = <<<'EOD'
{
    "status": "success",
    "data": {
        "test": true
    }
}
EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }

    public function testSuccessResponseNoData() {
        $core = new Core();
        $response = JsonResponse::getSuccessResponse();
        $response->render($core);
        $expected = <<<'EOD'
{
    "status": "success",
    "data": null
}
EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }

    public function testFailResponse() {
        $core = new Core();
        $response = JsonResponse::getFailResponse('fail message');
        $response->render($core);
        $expected = <<<'EOD'
{
    "status": "fail",
    "message": "fail message"
}
EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }

    public function testFailResponseWithData() {
        $core = new Core();
        $response = JsonResponse::getFailResponse('fail message', ['test' => true]);
        $response->render($core);
        $expected = <<<'EOD'
{
    "status": "fail",
    "data": {
        "test": true
    },
    "message": "fail message"
}
EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }

    public function testErrorResponse() {
        $core = new Core();
        $response = JsonResponse::getErrorResponse('error message');
        $response->render($core);
        $expected = <<<'EOD'
{
    "status": "error",
    "message": "error message"
}
EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }

    public function testErrorResponseWithData() {
        $core = new Core();
        $response = JsonResponse::getErrorResponse('error message', ['test' => true]);
        $response->render($core);
        $expected = <<<'EOD'
{
    "status": "error",
    "data": {
        "test": true
    },
    "message": "error message"
}
EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }

    public function testErrorResponseWithDataAndCode() {
        $core = new Core();
        $response = JsonResponse::getErrorResponse('error message', ['test' => true], "E1234");
        $response->render($core);
        $expected = <<<'EOD'
{
    "status": "error",
    "data": {
        "test": true
    },
    "message": "error message",
    "code": "E1234"
}
EOD;
        $this->assertEquals($expected, $core->getOutput()->getOutput());
    }

    public function testSuccessResponseCode() {
        $core = new Core();
        $response = JsonResponse::getSuccessResponse(['test' => true]);
        $response->render($core);
        $this->assertEquals(200, http_response_code());
    }

    public function testFailResponseCode() {
        $core = new Core();
        $response = JsonResponse::getFailResponse('fail message', null, http_status_code: 401);
        $response->render($core);
        $this->assertEquals(401, http_response_code());
    }

    public function testErrorResponseCode() {
        $core = new Core();
        $response = JsonResponse::getErrorResponse('not found message', null, http_status_code: 404);
        $response->render($core);
        $this->assertEquals(404, http_response_code());
    }
}
