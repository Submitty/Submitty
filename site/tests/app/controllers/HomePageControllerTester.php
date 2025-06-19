<?php

namespace tests\app\controllers;

use app\controllers\HomePageController;
use app\libraries\Core;
use app\models\Course;
use app\models\User;
use app\entities\Term;
use app\repositories\TermRepository;
use tests\BaseUnitTest;

class HomePageControllerTester extends BaseUnitTest {
    public function createCore(array $config_values, string $user_id): Core {
        $core = $this->createMockCore($config_values, ['no_user' => true]);
        $user = $this->createMockModel(User::class);
        $user->method('getId')->willReturn($user_id);
        $core->method('getUser')->willReturn($user);
        return $core;
    }

    private function createCourse(Core $core, string $name, int $user_group = 3): Course {
        return new Course($core, [
            'term' => 'f24',
            'term_name' => 'Fall 24',
            'course' => $name,
            'user_group' => $user_group
        ]);
    }

    public function testGetCourses() {
        $core = $this->createCore(['course' => 'course_dropped', 'semester' => 'f24'], 'student');
        $repo = $this->createMock(TermRepository::class);
        $entityManager = $core->getSubmittyEntityManager();
        $entityManager->method('getRepository')
            ->with(Term::class)
            ->willReturn($repo);
        $course_1 = $this->createCourse($core, 'course1');
        $course_dropped = $this->createCourse($core, 'course_dropped');
        $course_2 = $this->createCourse($core, 'course2');
        $course_instructor = $this->createCourse($core, 'course_instructor', 1);
        $val_map = [
            ['student', false, false, [$course_1]],
            ['student', true, false, []],
            ['student', false, true, [$course_dropped]],
            ['other_student', false, false, [$course_2]],
            ['other_student', true, false, []],
            ['other_student', false, true, []],
            ['instructor', false, false, [$course_instructor]],
            ['instructor', true, false, []],
            ['instructor', false, true, []]
        ];
        $core->getQueries()->method('getCourseForUserId')->will($this->returnValueMap($val_map));
        $core->getQueries()->method('wasStudentEverInCourse')->willReturn(true);
        $controller = new HomePageController($core);
        $response = $controller->getCourses()->json_response->json['data'];
        $this->assertEqualsCanonicalizing(
            [
                'unarchived_courses' => [$course_1->getCourseInfo()],
                'archived_courses' => [],
                'dropped_courses' => [$course_dropped->getCourseInfo()],
                'self_registration_courses' => []
            ],
            $response
        );
        $response = $controller->getCourses(['other_student'])->json_response->json['data'];
        // should not be able to access other student course info
        $this->assertNotContains($course_2->getCourseInfo(), $response['unarchived_courses']);

        $core = $this->createCore(['course' => 'course_dropped', 'semester' => 'f24'], 'other_student');
        $core->getQueries()->method('getCourseForUserId')->will($this->returnValueMap($val_map));
        $core->getQueries()->method('wasStudentEverInCourse')->willReturn(true);
        $controller = new HomePageController($core);
        $response = $controller->getCourses()->json_response->json['data'];
        $this->assertEqualsCanonicalizing(
            [
                'unarchived_courses' => [$course_2->getCourseInfo()],
                'archived_courses' => [],
                'dropped_courses' => [],
                'self_registration_courses' => []
            ],
            $response
        );

        $instruc_map = [
            ['other_student', 'course_dropped', 'f24', false]
        ];
        $core->getQueries()->method('checkIsInstructorInCourse')->will($this->returnValueMap($instruc_map));
        $response = $controller->getCourses(null, true)->json_response->json['data'];
        $this->assertEqualsCanonicalizing(
            [
                'unarchived_courses' => [],
                'archived_courses' => [],
                'dropped_courses' => [],
                'self_registration_courses' => []
            ],
            $response
        );

        $core = $this->createCore(['course' => 'course_dropped', 'semester' => 'f24'], 'instructor');
        $core->getQueries()->method('getCourseForUserId')->will($this->returnValueMap($val_map));
        $core->getQueries()->method('wasStudentEverInCourse')->willReturn(true);
        $instruc_map = [
            ['instructor', 'course_instructor', 'f24', true]
        ];
        $core->getQueries()->method('checkIsInstructorInCourse')->will($this->returnValueMap($instruc_map));
        $controller = new HomePageController($core);
        $response = $controller->getCourses(null, true)->json_response->json['data'];
        $this->assertEqualsCanonicalizing(
            [
                'unarchived_courses' => [$course_instructor->getCourseInfo()],
                'archived_courses' => [],
                'dropped_courses' => [],
                'self_registration_courses' => []
            ],
            $response
        );
        $response = $controller->getCourses(null, 'true')->json_response->json['data'];
        $this->assertEqualsCanonicalizing(
            [
                'unarchived_courses' => [$course_instructor->getCourseInfo()],
                'archived_courses' => [],
                'dropped_courses' => [],
                'self_registration_courses' => []
            ],
            $response
        );
    }

    public function testGetGroups() {
        // attempt with no user
        $core = $this->createMockCore([], ['no_user' => true]);
        $controller = new HomePageController($core);
        $response = $controller->getGroups();
        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Error', $response->web_response->view_class);

        // attempt with non-faculty
        $core = $this->createMockCore();
        $controller = new HomePageController($core);
        $response = $controller->getGroups();
        $this->assertEquals('fail', $response->json_response->json['status']);
        $this->assertEquals('Error', $response->web_response->view_class);

        $core = $this->createMockCore([], ['access_faculty' => true]);
        $val_map = [
            ['testUser', ['a', 'c']],
            ['otherUser', ['b']]
        ];
        $core->getQueries()->method('getUserGroups')->will($this->returnValueMap($val_map));
        $controller = new HomePageController($core);
        $response = $controller->getGroups();
        $this->assertEquals($val_map[0][1], $response->json_response->json['data']);
        // verify non superusers cannot access other users
        $response = $controller->getGroups('otherUser');
        $this->assertEquals($val_map[0][1], $response->json_response->json['data']);
        // verify superuser can access other users
        $core->getUser()->method('getAccessLevel')->willReturn(User::LEVEL_SUPERUSER);
        $response = $controller->getGroups('otherUser');
        $this->assertEquals($val_map[1][1], $response->json_response->json['data']);
    }

    public function testShowHomepage() {
        $core = $this->createMockCore();
        $controller = new HomePageController($core);
        $response = $controller->showHomepage();
        $this->assertEquals('showHomePage', $response->web_response->view_function);
        $this->assertEqualsCanonicalizing([$core->getUser(), [], [], [], []], $response->web_response->parameters);
    }
}
