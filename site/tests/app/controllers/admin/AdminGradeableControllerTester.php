<?php

declare(strict_types=1);

namespace tests\app\controllers\admin;

use app\controllers\admin\AdminGradeableController;
use app\exceptions\ValidationException;
use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\models\gradeable\Gradeable;
use PHPUnit\Framework\MockObject\MockObject;
use tests\BaseUnitTest;

class AdminGradeableControllerTester extends BaseUnitTest {
    /** @var Core&MockObject */
    private MockObject $core;
    private AdminGradeableController $controller;
    /** @var Gradeable&MockObject */
    private MockObject $gradeable;

    public function setUp(): void {
        parent::setUp();

        /** @var Core&MockObject $core */
        $core = $this->createMockCore();
        $this->core = $core;
        $this->controller = new AdminGradeableController($this->core);

        $this->gradeable = $this->getMockBuilder(Gradeable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setPeerGradersList', 'getDates', 'hasDueDate', 'hasReleaseDate'])
            ->getMock();

        $this->gradeable->method('getDates')->willReturn([]);
        $this->gradeable->method('hasDueDate')->willReturn(true);
        $this->gradeable->method('hasReleaseDate')->willReturn(true);
    }

    public function testUpdateGradeableWithJsonPeerGradersList2500Rows(): void {
        $rows = [];
        for ($i = 0; $i < 2500; $i++) {
            $rows[] = [
                'grader' => 'grader_' . $i,
                'student' => 'student_' . $i,
            ];
        }

        $json_str = json_encode($rows);

        $passed_val = null;
        $this->gradeable->expects(self::once())
            ->method('setPeerGradersList')
            ->willReturnCallback(function ($val) use (&$passed_val) {
                $passed_val = $val;
            });

        /** @var DatabaseQueries&MockObject $queries */
        $queries = $this->core->getQueries();
        $queries->expects(self::once())
            ->method('updateGradeable')
            ->with($this->gradeable);

        $details = [
            'peer_graders_list' => $json_str,
        ];

        $this->invokeMethod($this->controller, 'updateGradeable', $this->gradeable, $details);

        self::assertIsArray($passed_val);
        self::assertCount(2500, $passed_val);
        self::assertSame('grader_0', $passed_val[0]['grader']);
        self::assertSame('student_2499', $passed_val[2499]['student']);
    }

    public function testUpdateGradeableWithArrayPeerGradersList(): void {
        $rows = [
            ['grader' => 'g1', 'student' => 's1'],
            ['grader' => 'g2', 'student' => 's2'],
        ];

        $passed_val = null;
        $this->gradeable->expects(self::once())
            ->method('setPeerGradersList')
            ->willReturnCallback(function ($val) use (&$passed_val) {
                $passed_val = $val;
            });

        /** @var DatabaseQueries&MockObject $queries */
        $queries = $this->core->getQueries();
        $queries->expects(self::once())
            ->method('updateGradeable')
            ->with($this->gradeable);

        $details = [
            'peer_graders_list' => $rows,
        ];

        $this->invokeMethod($this->controller, 'updateGradeable', $this->gradeable, $details);

        self::assertIsArray($passed_val);
        self::assertCount(2, $passed_val);
    }

    public function testUpdateGradeableWithInvalidJsonPeerGradersList(): void {
        $this->expectException(ValidationException::class);

        $details = [
            'peer_graders_list' => '{invalid-json',
        ];

        $this->invokeMethod($this->controller, 'updateGradeable', $this->gradeable, $details);
    }

    public function testUpdateGradeableWithNonArrayJsonPeerGradersList(): void {
        $this->expectException(ValidationException::class);

        $details = [
            'peer_graders_list' => '"just a string"',
        ];

        $this->invokeMethod($this->controller, 'updateGradeable', $this->gradeable, $details);
    }
}
