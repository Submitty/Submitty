<?php

namespace tests\app\models;


use app\libraries\Core;
use app\models\Breadcrumb;

class BreadcrumbTester extends \PHPUnit\Framework\TestCase {
    public function testDefaults() {
        $core = $this->createMock(Core::class);
        $breadcrumb = new Breadcrumb($core, 'title');
        $this->assertEquals('title', $breadcrumb->getTitle());
        $this->assertNull($breadcrumb->getUrl());
        $this->assertNull($breadcrumb->getExternalUrl());
    }

    public function testUrl() {
        $core = $this->createMock(Core::class);
        $breadcrumb = new Breadcrumb($core, "title", "link");
        $this->assertEquals('title', $breadcrumb->getTitle());
        $this->assertEquals('link', $breadcrumb->getUrl());
        $this->assertNull($breadcrumb->getExternalUrl());
    }

    public function testExternalUrl() {
        $core = $this->createMock(Core::class);
        $breadcrumb = new Breadcrumb($core, 'title', null, 'link');
        $this->assertEquals('title', $breadcrumb->getTitle());
        $this->assertNull($breadcrumb->getUrl());
        $this->assertEquals('link', $breadcrumb->getExternalUrl());
    }

    public function testUrlAndExternal() {
        $core = $this->createMock(Core::class);
        $breadcrumb = new Breadcrumb($core, 'title', 'internal_link', 'external_link');
        $this->assertEquals('title', $breadcrumb->getTitle());
        $this->assertEquals('internal_link', $breadcrumb->getUrl());
        $this->assertEquals('external_link', $breadcrumb->getExternalUrl());
    }
}
