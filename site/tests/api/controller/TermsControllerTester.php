<?php

namespace tests\api\controllers;

use app\controllers\api\TermsController;
use app\entities\Term;
use app\libraries\Core;
use app\models\User;
use Doctrine\ORM\EntityManager;
use tests\BaseUnitTest;

class TermsControllerTester extends BaseUnitTest {
    /** @var Core */
    private $core;
 
    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityManager */
    private $em;
 
    public function setUp(): void {
        $_POST = [];
    }
 
    public function tearDown(): void {
        $_POST = [];
    }
 
    /**
     * Builds a mocked Core with a mocked EntityManager attached.
     *
     * @param bool $is_super_user
     * @param Term|null $existing_term
     */
    private function buildCore(bool $is_super_user, ?Term $existing_term = null): Core {
        $this->em = $this->createMock(EntityManager::class);
        $this->em->method('find')->willReturn($existing_term);
        $this->em->expects($existing_term === null && $is_super_user ? $this->once() : $this->never())
            ->method('persist');
        $this->em->expects($existing_term === null && $is_super_user ? $this->once() : $this->never())
            ->method('flush');
 
        $user = $this->createMock(User::class);
        $user->method('isSuperUser')->willReturn($is_super_user);
 
        $core = $this->createMock(Core::class);
        $core->method('getUser')->willReturn($user);
        $core->method('getSubmittyEntityManager')->willReturn($this->em);
        $core->method('buildUrl')->willReturn('/home/courses/new');
        $core->method('addErrorMessage')->willReturn(null);
        $core->method('addSuccessMessage')->willReturn(null);
 
        return $core;
    }
 
    public function testAddNewTermFailsIfNotSuperUser(): void {
        $core = $this->buildCore(false);
        $controller = new TermsController($core);
 
        $_POST = [
            'term_id' => 's26',
            'term_name' => 'Spring 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-05-31',
        ];
 
        $response = $controller->addNewTerm();
 
        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals("You don't have access to this endpoint.", $response->json_response->json['message']);
    }
 
    public function testAddNewTermFailsWithMissingFields(): void {
        $core = $this->buildCore(true);
        $controller = new TermsController($core);
 
        $_POST = [
            'term_name' => 'Missing Term Data',
        ];
 
        $response = $controller->addNewTerm();
 
        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals(
            'Term ID, term name, start date, or end date not set.',
            $response->json_response->json['message']
        );
    }
 
    public function testAddNewTermFailsIfTermAlreadyExists(): void {
        $existing = new Term('s26', 'Spring 2026', new \DateTime('2026-01-01'), new \DateTime('2026-05-31'));
        $core = $this->buildCore(true, $existing);
        $controller = new TermsController($core);
 
        $_POST = [
            'term_id' => 's26',
            'term_name' => 'Spring 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-05-31',
        ];
 
        $response = $controller->addNewTerm();
 
        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Term with that ID already exists.', $response->json_response->json['message']);
    }
 
    public function testAddNewTermFailsIfEndDateBeforeStartDate(): void {
        $core = $this->buildCore(true);
        $controller = new TermsController($core);
 
        $_POST = [
            'term_id' => 'bad_date',
            'term_name' => 'Bad Date Term',
            'start_date' => '2021-05-31',
            'end_date' => '2021-01-01',
        ];
 
        $response = $controller->addNewTerm();
 
        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('End date should be after Start date.', $response->json_response->json['message']);
    }
 
    public function testAddNewTermFailsIfTermLengthExceeds360Days(): void {
        $core = $this->buildCore(true);
        $controller = new TermsController($core);
 
        $_POST = [
            'term_id' => 'long_term',
            'term_name' => 'Too Long Term',
            'start_date' => '2022-01-01',
            'end_date' => '2023-06-01',
        ];
 
        $response = $controller->addNewTerm();
 
        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertMatchesRegularExpression(
            '/^Term length cannot exceed 360 days \(this term spans \d+ days\)\.$/',
            $response->json_response->json['message']
        );
    }
 
    public function testAddNewTermSucceeds(): void {
        $core = $this->buildCore(true);
        $controller = new TermsController($core);
 
        $_POST = [
            'term_id' => 's26',
            'term_name' => 'Spring 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-05-31',
        ];
 
        $response = $controller->addNewTerm();
 
        $this->assertEquals('success', $response->json_response->json['status']);
        $this->assertEquals('s26', $response->json_response->json['data']['term_id']);
        $this->assertEquals('Spring 2026', $response->json_response->json['data']['term_name']);
        $this->assertEquals('2026-01-01', $response->json_response->json['data']['start_date']);
        $this->assertEquals('2026-05-31', $response->json_response->json['data']['end_date']);
    }
}
