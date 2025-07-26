<?php

declare(strict_types=1);

namespace tests\app\Controllers;

use app\libraries\Core;
use app\controllers\TermController;
use app\entities\Term;
use app\models\User;
use tests\BaseUnitTest;

class TermTester extends BaseUnitTest {
    public function testTerms() {
        $core = $this->createMockCore(Core::class);
        $core->getSubmittyEntityManager();
        $entity_manager
            ->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function ($term): bool {
                    $this->assertInstanceOf(Term::class, $term);
                    $this->assertEquals($term->getId(), 'id');
                    $this->assertEquals($term->getName(), 'name');
                    $this->assertEquals($term->getStartDate(), '06/25/25');
                    $this->assertEquals($term->getEndDate(), '07/18/25');
                    return true;
                })
            );
        $entity_manager
            ->expects($this->once())
            ->method('flush');
        // Testing create terms
        $term = new Term(
            'id',
            'name',
            date('06/25/25'),
            date('7/18/25')
        );
        $em->persist($term);
        $em->flush();
        // Testing getTermStartDate
        $detail = [
            'user_id' => "aphacker",
            'anon_id' => "anon_id",
            'user_numeric_id' => '987654321',
            'user_password' => "aphacker",
            'user_givenname' => "Alyss",
            'user_preferred_givenname' => "Allison",
            'user_familyname' => "Hacker",
            'user_preferred_familyname' => "Hacks",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "aphacker@example.com",
            'user_email_secondary' => "aphacker@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];
    }
}
