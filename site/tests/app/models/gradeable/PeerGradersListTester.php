<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\models\gradeable\Gradeable;
use app\models\User;
use PHPUnit\Framework\MockObject\MockObject;
use tests\BaseUnitTest;

class PeerGradersListTester extends BaseUnitTest {
    /** @var Core&MockObject */
    private MockObject $core;
    /** @var Gradeable&MockObject */
    private MockObject $gradeable;
    /** @var DatabaseQueries&MockObject */
    private MockObject $queries;

    public function setUp(): void {
        parent::setUp();

        /** @var Core&MockObject $core */
        $core = $this->createMockCore();
        $this->core = $core;

        /** @var DatabaseQueries&MockObject $queries */
        $queries = $this->core->getQueries();
        $this->queries = $queries;

        $this->gradeable = $this->getMockBuilder(Gradeable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $this->gradeable->method('getId')->willReturn('test_gradeable');

        // Bind core to gradeable
        $reflection = new \ReflectionClass(Gradeable::class);
        $core_prop = $reflection->getProperty('core');
        $core_prop->setAccessible(true);
        $core_prop->setValue($this->gradeable, $this->core);
    }

    public function testSetPeerGradersListWith2500Rows(): void {
        $rows = [];
        for ($i = 0; $i < 2500; $i++) {
            $rows[] = [
                'grader' => 'grader_' . $i,
                'student' => 'student_' . $i,
            ];
        }

        $mock_user = $this->createMock(User::class);
        $this->queries->method('getUserById')->willReturn($mock_user);

        $inserted_query = '';
        $this->queries->expects(self::once())
            ->method('clearPeerGradingAssignment')
            ->with('test_gradeable');

        $this->queries->expects(self::once())
            ->method('insertBulkPeerGradingAssignment')
            ->willReturnCallback(function ($query_string) use (&$inserted_query) {
                $inserted_query = $query_string;
            });

        $this->queries->expects(self::once())
            ->method('getPeerGradingAssignment')
            ->with('test_gradeable')
            ->willReturn($rows);

        $this->core->expects(self::never())->method('addErrorMessage');

        $this->gradeable->setPeerGradersList($rows);

        self::assertNotEmpty($inserted_query);
        self::assertStringContainsString("('grader_0', 'student_0','test_gradeable')", $inserted_query);
        self::assertStringContainsString("('grader_2499', 'student_2499','test_gradeable')", $inserted_query);
    }



    public function testSetPeerGradersListInvalidUsers(): void {
        $rows = [
            ['grader' => 'valid_grader', 'student' => 'invalid_student']
        ];

        $this->queries->method('getUserById')->willReturnCallback(function ($id) {
            return $id === 'valid_grader' ? $this->createMock(User::class) : null;
        });

        $this->queries->expects(self::never())->method('clearPeerGradingAssignment');
        $this->queries->expects(self::never())->method('insertBulkPeerGradingAssignment');

        $this->core->expects(self::once())
            ->method('addErrorMessage')
            ->with(self::stringContains('The given user id is not valid:  invalid_student'));

        $this->gradeable->setPeerGradersList($rows);
    }

    public function testSetPeerGradersListSelfGrading(): void {
        $rows = [
            ['grader' => 'user1', 'student' => 'user1']
        ];

        $mock_user = $this->createMock(User::class);
        $this->queries->method('getUserById')->willReturn($mock_user);

        $this->queries->expects(self::never())->method('clearPeerGradingAssignment');
        $this->queries->expects(self::never())->method('insertBulkPeerGradingAssignment');

        $this->core->expects(self::once())
            ->method('addErrorMessage')
            ->with(self::stringContains('The given users have self gradings:  user1'));

        $this->gradeable->setPeerGradersList($rows);
    }
}
