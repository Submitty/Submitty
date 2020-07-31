<?php

namespace tests\app\libraries\routers;

use app\libraries\routers\AccessControl;
use tests\BaseUnitTest;
use app\models\User;
use app\libraries\routers\WebRouter;
use Symfony\Component\HttpFoundation\Request;

class AccessControlTester extends BaseUnitTest {
    private $semester = 'test_semester';

    private $course = 'test_course';

    public function data() {
        $course_prefix = "/courses/{$this->semester}/{$this->course}";
        return [
            [$course_prefix . '/gradeable/open_homework/update', "GET", [], User::GROUP_INSTRUCTOR],
        ];
    }

    /**
     * @param string $endpoint
     * @param string $method
     * @param array $params
     * @param int $min_role
     * @param array $min_permission
     * @param bool $logged_in
     *
     * @dataProvider data
     * @throws \ReflectionException
     */
    public function testAccess(
        $endpoint,
        $method = "GET",
        $params = [],
        $min_role = User::LEVEL_USER,
        $min_permission = ['course.view'],
        $logged_in = true
    ) {
        for ($role = User::GROUP_STUDENT; $role > $min_role; $role--) {
            $core = $this->getAccessTestCore($role, $min_permission, $logged_in);
            $request = Request::create(
                $endpoint,
                $method,
                $params
            );

            $response = WebRouter::getWebResponse($request, $core);

            $this->assertEquals(
                [
                    'status' => 'fail',
                    'message' => "You don't have access to this endpoint."
                ],
                $response->json_response->json
            );
        }
    }

    private function getAccessTestCore($role, $permission, $logged_in) {
        switch ($role) {
            case User::GROUP_INSTRUCTOR:
                $core = $this->createMockCore(
                    [
                        'access_admin' => true,
                        'semester' => $this->semester,
                        'course' => $this->course,
                        'logged_in' => $logged_in
                    ],
                    [],
                    [],
                    $permission
                );
                break;
            case User::GROUP_FULL_ACCESS_GRADER:
                $core = $this->createMockCore(
                    [
                        'access_full_grading' => true,
                        'semester' => $this->semester,
                        'course' => $this->course,
                        'logged_in' => $logged_in
                    ],
                    [],
                    [],
                    $permission
                );
                break;
            case User::GROUP_LIMITED_ACCESS_GRADER:
                $core = $this->createMockCore(
                    [
                        'access_grading' => true,
                        'semester' => $this->semester,
                        'course' => $this->course,
                        'logged_in' => $logged_in
                    ],
                    [],
                    [],
                    $permission
                );
                break;
            case User::GROUP_STUDENT:
            default:
                $core = $this->createMockCore(
                    [
                        'semester' => $this->semester,
                        'course' => $this->course,
                        'logged_in' => $logged_in
                    ],
                    [],
                    [],
                    $permission
                );
                break;
        }
        return $core;
    }

    public function testInvalidProperty() {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Unknown property "foo" on annotation "app\libraries\routers\AccessControl"');
        new AccessControl(['foo' => 1]);
    }

    public function testInvalidRoleConstructor() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role: INVALID_ROLE');
        new AccessControl(['role' => 'INVALID_ROLE']);
    }

    public function testInvalidRoleMethod() {
        $access = new AccessControl([]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role: INVALID_ROLE');
        $access->setRole('INVALID_ROLE');
    }

    public function accessRoleProvider() {
        return [["INSTRUCTOR"], ["FULL_ACCESS_GRADER"], ["LIMITED_ACCESS_GRADER"], ["STUDENT"]];
    }

    /**
     * @dataProvider accessRoleProvider
     */
    public function testAccessControlConstructor($role) {
        $access = new AccessControl(['role' => $role, 'permission' => 'grading.simple']);
        $this->assertEquals($role, $access->getRole());
        $this->assertEquals('grading.simple', $access->getPermission());
    }

    /**
     * @dataProvider accessRoleProvider
     */
    public function testAccessControlSet($role) {
        $access = new AccessControl([]);
        $this->assertNull($access->getRole());
        $this->assertNull($access->getPermission());
        $access->setRole($role);
        $this->assertEquals($role, $access->getRole());
        $access->setPermission('test');
        $this->assertEquals('test', $access->getPermission());
    }
}
