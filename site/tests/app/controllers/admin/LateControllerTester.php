<?php namespace tests\app\controllers\admin;

use app\models\User;
use app\libraries\Core;
use tests\BaseUnitTest;
use app\libraries\response\WebResponse;
use app\controllers\admin\LateController;

class LateControllerTester extends BaseUnitTest
{
   /** @var LateController */
    protected $controller;

    /** @var Core */
    protected $core;

    /** @var User */
    protected $user;

    /** @var bool[] */
    protected $methodsCalled;

    public function setUp(): void
    {
        parent::setUp();

        $this->core = $this->createMockCore([], [], [
            'getUsersWithLateDays'  => [],
            'getAllUsers'           => [],
        ]);

        $this->controller = new LateController($this->core);
    }

    /** @test */
    public function it_tests_queries_being_made_in_view_late_days()
    {
        $response = $this->controller->viewLateDays();

        $this->assertMethodCalled('getUsersWithLateDays');
        $this->assertMethodCalled('getAllUsers');
        $this->assertInstanceOf(WebResponse::class, $response->web_response);
    }

}
