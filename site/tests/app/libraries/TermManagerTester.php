<?php

declare(strict_types=1);

namespace tests\app\libraries;

use app\libraries\Core;
use app\repositories\TermRepository;
use app\libraries\TermManager;
use app\entities\Term;
use app\models\User;
use app\libraries\DateUtils;
use tests\BaseUnitTest;

class TermManagerTester extends BaseUnitTest {

    public function testTerms() {
        $core = $this->createMockCore(Core::class);
        $repo = $this->createMock(TermRepository::class);
        $entityManager = $core->getSubmittyEntityManager();
        $entityManager->method('getRepository')
            ->with(Term::class)
            ->willReturn($repo);
        // Testing create terms
        TermManager::createNewTerm($core, 'id', 'NAME', '06/25/25', '07/18/25');
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
        $user = new User($core, $detail);
        $this->assertEquals(TermManager::getTermStartDate($core, 'id', $user), DateUtils::convertTimeStamp($user, '06/25/25', 'Y-m-d H:i:s'));
    }

}