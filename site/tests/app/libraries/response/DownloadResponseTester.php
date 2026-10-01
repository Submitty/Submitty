<?php

declare(strict_types=1);

namespace tests\app\libraries\response;

use app\libraries\Core;
use app\libraries\response\DownloadResponse;
use app\libraries\response\ResponseInterface;
use PHPUnit\Framework\TestCase;

class DownloadResponseTester extends TestCase {
    public function testGetDownloadResponseDefaults() {
        $response = DownloadResponse::getDownloadResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('', $response->contents);
        $this->assertEquals('downloaded_file', $response->title);
        $this->assertEquals('text/plain', $response->file_type);
    }

    public function testGetDownloadResponseSetsProperties() {
        $response = DownloadResponse::getDownloadResponse('file contents', 'my_file.txt', 'application/pdf');
        $this->assertEquals('file contents', $response->contents);
        $this->assertEquals('my_file.txt', $response->title);
        $this->assertEquals('application/pdf', $response->file_type);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRenderOutputsContentsVerbatim() {
        $core = new Core();
        $response = DownloadResponse::getDownloadResponse('file contents', 'my_file.txt', 'application/pdf');
        $response->render($core);
        // renderFile() disables the header/footer chrome, so getOutput() should
        // return exactly the file contents and nothing else.
        $this->assertEquals('file contents', $core->getOutput()->getOutput());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRenderOutputsEmptyContents() {
        $core = new Core();
        $response = DownloadResponse::getDownloadResponse();
        $response->render($core);
        $this->assertEquals('', $core->getOutput()->getOutput());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRenderDoesNotThrow() {
        $core = new Core();
        $response = DownloadResponse::getDownloadResponse('binary-ish data', 'archive.zip', 'application/zip');
        $response->render($core);
        $this->assertEquals('binary-ish data', $core->getOutput()->getOutput());
    }
}
