<?php

namespace tests\app\models;

use app\models\GradingOrder;
use app\models\gradeable\Submitter;
use app\models\User;

class GradingOrderTester extends \PHPUnit\Framework\TestCase {
    private function submitter(string $id, bool $withdrawn = false, bool $team = false): Submitter {
        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()
            ->addMethods(['getRegistrationType'])->getMock();
        $user->method('getRegistrationType')->willReturn($withdrawn ? 'withdrawn' : 'registered');
        $submitter = $this->createMock(Submitter::class);
        $submitter->method('getId')->willReturn($id);
        $submitter->method('isTeam')->willReturn($team);
        $submitter->method('getUser')->willReturn($user);
        return $submitter;
    }

    public function testClusterNavigationRespectsWithdrawnFilter(): void {
        $a = $this->submitter('a');
        $same = $this->submitter('same');
        $withdrawn = $this->submitter('withdrawn', true);
        $no_submission = $this->submitter('no_submission');
        $b = $this->submitter('b');
        $order = (new \ReflectionClass(GradingOrder::class))->newInstanceWithoutConstructor();
        foreach (['section_submitters' => [[$a, $same, $withdrawn, $no_submission, $b]],
                  'has_submission' => ['a' => true, 'same' => true, 'withdrawn' => true, 'b' => true]] as $name => $value) {
            $property = new \ReflectionProperty(GradingOrder::class, $name);
            $property->setAccessible(true);
            $property->setValue($order, $value);
        }
        $clusters = ['a' => 1, 'same' => 1, 'withdrawn' => 2, 'b' => 3];
        $this->assertSame($b, $order->getNextSubmitterByCluster($a, -1, $clusters, true));
        $this->assertSame($same, $order->getPrevSubmitterByCluster($b, -1, $clusters, true));
        $this->assertSame($withdrawn, $order->getNextSubmitterByCluster($a, -1, $clusters, false));
        $this->assertSame($withdrawn, $order->getPrevSubmitterByCluster($b, -1, $clusters, false));
        $this->assertNull($order->getNextSubmitterByCluster($b, -1, $clusters, true));
        $this->assertNull($order->getPrevSubmitterByCluster($a, -1, $clusters, true));
    }

    public function testUnclusteredTeamAndUnavailableCandidates(): void {
        $a = $this->submitter('a');
        $team = $this->submitter('team', true, true);
        $order = (new \ReflectionClass(GradingOrder::class))->newInstanceWithoutConstructor();
        foreach (['section_submitters' => [[$a, $team]],
                  'has_submission' => ['a' => true, 'team' => true]] as $name => $value) {
            $property = new \ReflectionProperty(GradingOrder::class, $name);
            $property->setAccessible(true);
            $property->setValue($order, $value);
        }
        $this->assertSame($team, $order->getNextSubmitterByCluster($a, -1, [], true));
        $this->assertSame($a, $order->getPrevSubmitterByCluster($team, -1, [], true));
        $this->assertNull($order->getNextSubmitterByCluster($team, -1, [], true));
    }
}
