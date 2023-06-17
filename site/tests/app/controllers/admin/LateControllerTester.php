<?php

namespace tests\app\controllers\admin;

use app\models\User;
use app\libraries\Core;
use tests\BaseUnitTest;
use app\libraries\response\WebResponse;
use app\controllers\admin\LateController;

class LateControllerTester extends BaseUnitTest {
   /** @var LateController */
    protected $controller;

    /** @var Core */
    protected $core;

    /** @var User */
    protected $user;

    public function setUp(): void {
        parent::setUp();

        $this->core = $this->createMockCore([], [], [
            'getUsersWithLateDays'  => [],
            'getAllUsers'           => [],
        ]);

        $this->controller = new LateController($this->core);
    }

    public function testQueriesCalledinController() {
        $response = $this->controller->viewLateDays();

        $this->assertMethodCalled('getUsersWithLateDays');
        $this->assertMethodCalled('getAllUsers');
        $this->assertInstanceOf(WebResponse::class, $response->web_response);
    }
}
