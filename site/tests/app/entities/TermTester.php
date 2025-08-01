<?php

declare(strict_types=1);

namespace tests\app\entities;

use app\libraries\Core;
use app\entities\Term;
use app\models\User;
use tests\BaseUnitTest;

class TermTester extends BaseUnitTest {
    public function testTerms() {
        $core = $this->createMockCore(Core::class);
        $entity_manager = $core->getSubmittyEntityManager();
        $entity_manager->expects($this->once())
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
        $entity_manager->expects($this->once())
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
    }
}
