<?php

declare(strict_types=1);

namespace tests\app\models;

use app\entities\calendar\CalendarItem;
use app\libraries\GradeableType;
use app\models\Button;
use app\models\CalendarInfo;
use app\models\Course;
use app\models\DateTimeFormat;
use app\models\User;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradeableList;
use app\models\gradeable\GradeableUtils;
use phpmock\phpunit\PHPMock;
use tests\BaseUnitTest;

class CalendarInfoTester extends BaseUnitTest {
    use PHPMock;

    const PAST = '2000-01-01';
    const FUTURE = '9000-01-01';

    private $core;
    private $saved_cookies;

    public static function setUpBeforeClass(): void {
        // Define the mock before CalendarInfo first calls setcookie(), so that every test can mock it
        self::defineFunctionMock('app\models', 'setcookie');
    }

    public function setUp(): void {
        $this->saved_cookies = $_COOKIE;
        $_COOKIE = [];

        $this->core = $this->createMockCore();
        $this->core->getConfig()->method('getDateTimeFormat')->willReturn(new DateTimeFormat($this->core, 'MDY'));
        $this->core->getUser()->method('getUsableTimeZone')->willReturn(new \DateTimeZone('America/New_York'));
        $this->core->method('buildUrl')->willReturnCallback(function (array $parts) {
            return '/' . implode('/', $parts);
        });
    }

    public function tearDown(): void {
        $_COOKIE = $this->saved_cookies;
    }

    private function createCourse(string $term, string $title): Course {
        return new Course($this->core, ['term' => $term, 'term_name' => $term, 'course' => $title]);
    }

    private function createCalendarItem(int $id, string $date, string $text, string $type): CalendarItem {
        $item = $this->createMock(CalendarItem::class);
        $item->method('getId')->willReturn($id);
        $item->method('getDate')->willReturn(new \DateTime($date));
        $item->method('getText')->willReturn($text);
        $item->method('getTypeString')->willReturn($type);
        return $item;
    }

    /**
     * Creates a gradeable that GradeableList will place in the requested section
     */
    private function createGradeable(string $id, int $section, int $type = GradeableType::ELECTRONIC_FILE): Gradeable {
        // TA view start, submission open, submission due, grade start (and due), grade released
        $dates = [
            GradeableList::FUTURE => [self::FUTURE, self::FUTURE, self::FUTURE, self::FUTURE, self::FUTURE],
            GradeableList::BETA => [self::PAST, self::FUTURE, self::FUTURE, self::FUTURE, self::FUTURE],
            GradeableList::OPEN => [self::PAST, self::PAST, '9000-01-15 23:59:59', '9000-02-01', '9000-03-01'],
            GradeableList::CLOSED => [self::PAST, self::PAST, '2000-01-15 23:59:59', '9000-02-01', '9000-03-01'],
            GradeableList::GRADING => [self::PAST, self::PAST, self::PAST, '2000-02-01', '9000-03-01'],
            GradeableList::GRADED => [self::PAST, self::PAST, self::PAST, self::PAST, '2000-03-01'],
        ];
        [$ta_view_start, $submission_open, $submission_due, $grade_start, $grade_released] = $dates[$section];

        $timezone = new \DateTimeZone('America/New_York');
        $details = [
            'id' => $id,
            'title' => "Title of {$id}",
            'instructions_url' => '',
            'ta_instructions' => '',
            'type' => $type,
            'grader_assignment_method' => 0,
            'min_grading_group' => 3,
            'syllabus_bucket' => 'homework',
            'autograding_config_path' => '/path/to/autograding',
            'vcs' => false,
            'using_subdirectory' => false,
            'vcs_subdirectory' => '',
            'vcs_partial_path' => '',
            'vcs_host_type' => GradeableUtils::VCS_TYPE_NONE,
            'team_assignment' => false,
            'team_size_max' => 1,
            'ta_grading' => true,
            'student_view' => true,
            'student_view_after_grades' => false,
            'student_download' => true,
            'student_submit' => true,
            'has_due_date' => true,
            'has_release_date' => true,
            'peer_grading' => false,
            'peer_grade_set' => false,
            'late_submission_allowed' => true,
            'precision' => 0.5,
            'grade_inquiry_allowed' => true,
            'grade_inquiry_per_component_allowed' => true,
            'discussion_based' => false,
            'discussion_thread_ids' => '',
            'ta_view_start_date' => new \DateTime($ta_view_start, $timezone),
            'grade_start_date' => new \DateTime($grade_start, $timezone),
            'grade_due_date' => new \DateTime($grade_start, $timezone),
            'grade_released_date' => new \DateTime($grade_released, $timezone),
            'team_lock_date' => new \DateTime($submission_due, $timezone),
            'submission_open_date' => new \DateTime($submission_open, $timezone),
            'submission_due_date' => new \DateTime($submission_due, $timezone),
            'late_days' => 2,
            'grade_inquiry_start_date' => new \DateTime($grade_released, $timezone),
            'grade_inquiry_due_date' => new \DateTime($grade_released, $timezone),
            'allowed_minutes' => null,
            'depends_on' => null,
            'depends_on_points' => null,
            'allow_custom_marks' => true,
            'any_manual_grades' => false,
            'score_notifications_sent' => 0,
            'release_notifications_sent' => false,
        ];

        return new Gradeable($this->core, $details);
    }

    private function getKey(Course $course, Gradeable $gradeable): string {
        return serialize([$course->getTerm(), $course->getTitle(), $gradeable->getId()]);
    }

    /**
     * Expect setcookie() to be called once for each of the given cookie names and values, in order
     *
     * @param array<string, string> $cookies
     */
    private function expectCookies(array $cookies): void {
        $setcookie = $this->getFunctionMock('app\models', 'setcookie');
        $setcookie->expects($this->exactly(count($cookies)))
            ->willReturnCallback(function (string $name, string $value, int $expires) use (&$cookies) {
                $this->assertEquals(array_key_first($cookies), $name);
                $this->assertEquals(array_shift($cookies), $value);
                $this->assertGreaterThan(time(), $expires);
                return true;
            });
    }

    /**
     * @param Gradeable[] $gradeables keyed by the serialized [term, course, gradeable id]
     * @param array<string, Button|null> $submit_btns keyed the same as $gradeables
     * @param Course[] $courses
     * @param array<string, CalendarItem[]> $calendar_items keyed by course title
     */
    private function loadInfo(
        array $gradeables,
        array $submit_btns,
        array $courses,
        array $calendar_items,
        int $access_level = User::LEVEL_USER
    ): CalendarInfo {
        $this->core->getUser()->method('getAccessLevel')->willReturn($access_level);

        return CalendarInfo::loadGradeableCalendarInfo(
            $this->core,
            ['gradeables' => $gradeables, 'submit_btns' => $submit_btns],
            $courses,
            $calendar_items
        );
    }

    public function testEmptyBeforeLoading(): void {
        $info = new CalendarInfo($this->core);

        $this->assertEquals([], $info->getItemsByDate());
        $this->assertEquals([], $info->getItemsBySections());
        $this->assertEquals([], $info->getColors());
        $this->assertEquals('', $info->getEmptyMessage());
    }

    public function testLoadWithNothing(): void {
        $this->expectCookies([]);
        $info = $this->loadInfo([], [], [], []);

        $this->assertEquals([], $info->getItemsByDate());
        $this->assertEquals([], $info->getColors());
        $this->assertEquals(
            'There are currently no assignments posted.  Please check back later.',
            $info->getEmptyMessage()
        );
    }

    public function testSectionsAreAlwaysListed(): void {
        $this->expectCookies([]);
        $sections = $this->loadInfo([], [], [], [])->getItemsBySections();

        $this->assertEquals(
            ['open', 'items_being_graded', 'closed', 'graded', 'future', 'beta'],
            array_column($sections, 'section_id')
        );
        $this->assertEquals(
            ['OPEN', 'GRADING IN PROGRESS', 'PAST DUE', 'GRADES AVAILABLE', 'FUTURE', 'BETA'],
            array_column($sections, 'title')
        );
        $this->assertEquals('visible only to Instructors', $sections[4]['subtitle']);
        foreach ($sections as $section) {
            $this->assertEquals([], $section['gradeables']);
        }
    }

    public function testOpenGradeable(): void {
        $_COOKIE['calendar_color_csci1100f24'] = '#ff0000';
        $this->expectCookies([]);
        $course = $this->createCourse('f24', 'csci1100');
        $gradeable = $this->createGradeable('hw1', GradeableList::OPEN);
        $key = $this->getKey($course, $gradeable);
        $button = new Button($this->core, ['title' => 'SUBMIT']);

        $info = $this->loadInfo([$key => $gradeable], [$key => $button], [$course], ['csci1100' => []]);

        $expected = [
            'gradeable_id' => 'hw1',
            'title' => 'Title of hw1',
            'semester' => 'f24',
            'course' => 'csci1100',
            'url' => '/courses/f24/csci1100/gradeable/hw1',
            'onclick' => '',
            'submission' => '01/15/9000 @ 11:59 PM EST',
            'status' => (string) GradeableList::OPEN,
            'status_note' => 'SUBMIT',
            'submission_open' => true,
            'is_student' => true,
            'grading_open' => '02/01/9000 @ 12:00 AM EST',
            'grading_due' => '02/01/9000 @ 12:00 AM EST',
            'class' => 'btn-nav',
            'disabled' => false,
            'show_due' => true,
            'icon' => '',
            'color' => '#ff0000',
            'type' => 'gradeable',
        ];
        $this->assertEquals([$key => $expected], $info->getItemsBySections()[0]['gradeables']);
        $this->assertEquals(['9000-01-15' => [$expected]], $info->getItemsByDate());
        $this->assertEquals($info->getItemsByDate(), $info->getItemsByDateInCourses());
    }

    public function testGradeablesArePlacedInTheirSections(): void {
        $this->expectCookies(['calendar_color_csci1100f24' => 'var(--category-color-1)']);
        $course = $this->createCourse('f24', 'csci1100');
        $sections = [
            GradeableList::OPEN => 0,
            GradeableList::GRADING => 1,
            GradeableList::CLOSED => 2,
            GradeableList::GRADED => 3,
            GradeableList::FUTURE => 4,
            GradeableList::BETA => 5,
        ];
        $gradeables = [];
        $submit_btns = [];
        foreach (array_keys($sections) as $section) {
            $gradeable = $this->createGradeable("gradeable_{$section}", $section);
            $key = $this->getKey($course, $gradeable);
            $gradeables[$key] = $gradeable;
            $submit_btns[$key] = null;
        }

        $info = $this->loadInfo($gradeables, $submit_btns, [$course], ['csci1100' => []]);

        $items_by_sections = $info->getItemsBySections();
        foreach ($sections as $section => $position) {
            $this->assertEquals(
                ["gradeable_{$section}"],
                array_column($items_by_sections[$position]['gradeables'], 'gradeable_id'),
                "Section {$section} is at position {$position}"
            );
            $this->assertEquals((string) $section, array_values($items_by_sections[$position]['gradeables'])[0]['status']);
        }
        $this->assertCount(6, array_merge(...array_values($info->getItemsByDate())));
    }

    public function testSubmissionOpenFlag(): void {
        $this->expectCookies(['calendar_color_csci1100f24' => 'var(--category-color-1)']);
        $course = $this->createCourse('f24', 'csci1100');
        $open = $this->createGradeable('open', GradeableList::OPEN);
        $beta = $this->createGradeable('beta', GradeableList::BETA);
        $open_key = $this->getKey($course, $open);
        $beta_key = $this->getKey($course, $beta);

        $info = $this->loadInfo(
            [$open_key => $open, $beta_key => $beta],
            [$open_key => null, $beta_key => null],
            [$course],
            ['csci1100' => []]
        );

        $sections = $info->getItemsBySections();
        $this->assertTrue($sections[0]['gradeables'][$open_key]['submission_open']);
        $this->assertFalse($sections[5]['gradeables'][$beta_key]['submission_open']);
    }

    public function testNonElectronicGradeableHasNoSubmissionDate(): void {
        $this->expectCookies(['calendar_color_csci1100f24' => 'var(--category-color-1)']);
        $course = $this->createCourse('f24', 'csci1100');
        $gradeable = $this->createGradeable('lab1', GradeableList::GRADING, GradeableType::CHECKPOINTS);
        $key = $this->getKey($course, $gradeable);

        $info = $this->loadInfo([$key => $gradeable], [$key => null], [$course], ['csci1100' => []]);

        $item = $info->getItemsBySections()[1]['gradeables'][$key];
        $this->assertEquals('', $item['submission']);
        $this->assertEquals('02/01/2000 @ 12:00 AM EST', $item['grading_open']);
        $this->assertEquals(['' => [$item]], $info->getItemsByDate());
    }

    /**
     * @dataProvider submitButtonProvider
     */
    public function testSubmitButton(?array $button_details, string $status_note, bool $disabled): void {
        $this->expectCookies(['calendar_color_csci1100f24' => 'var(--category-color-1)']);
        $course = $this->createCourse('f24', 'csci1100');
        $gradeable = $this->createGradeable('hw1', GradeableList::OPEN);
        $key = $this->getKey($course, $gradeable);
        $button = $button_details === null ? null : new Button($this->core, $button_details);

        $info = $this->loadInfo([$key => $gradeable], [$key => $button], [$course], ['csci1100' => []]);

        $item = $info->getItemsBySections()[0]['gradeables'][$key];
        $this->assertEquals($status_note, $item['status_note']);
        $this->assertSame($disabled, $item['disabled']);
    }

    public static function submitButtonProvider(): array {
        return [
            'no button' => [null, 'N/A', false],
            'enabled button' => [['title' => 'RESUBMIT'], 'RESUBMIT', false],
            'disabled button' => [['title' => 'SUBMIT', 'disabled' => true], 'SUBMIT', true],
        ];
    }

    /**
     * @dataProvider accessLevelProvider
     */
    public function testIsStudent(int $access_level, bool $expected): void {
        $this->expectCookies(['calendar_color_csci1100f24' => 'var(--category-color-1)']);
        $course = $this->createCourse('f24', 'csci1100');
        $gradeable = $this->createGradeable('hw1', GradeableList::OPEN);
        $key = $this->getKey($course, $gradeable);

        $info = $this->loadInfo([$key => $gradeable], [$key => null], [$course], ['csci1100' => []], $access_level);

        $this->assertSame($expected, $info->getItemsBySections()[0]['gradeables'][$key]['is_student']);
    }

    public static function accessLevelProvider(): array {
        return [
            'student' => [User::LEVEL_USER, true],
            'faculty' => [User::LEVEL_FACULTY, false],
            'superuser' => [User::LEVEL_SUPERUSER, false],
        ];
    }

    public function testColorFromCookie(): void {
        $_COOKIE['calendar_color_csci1100f24'] = '#00ff00';
        $this->expectCookies([]);

        $info = $this->loadInfo([], [], [$this->createCourse('f24', 'csci1100')], ['csci1100' => []]);

        $this->assertEquals(['f24csci1100' => '#00ff00'], $info->getColors());
    }

    public function testDefaultColorsAreAssignedAndSaved(): void {
        $_COOKIE['calendar_color_csci1200f24'] = '#00ff00';
        $this->expectCookies([
            'calendar_color_csci1100f24' => 'var(--category-color-1)',
            'calendar_color_csci2300f24' => 'var(--category-color-2)',
        ]);
        $courses = [
            $this->createCourse('f24', 'csci1100'),
            $this->createCourse('f24', 'csci1200'),
            $this->createCourse('f24', 'csci2300'),
        ];

        $info = $this->loadInfo([], [], $courses, ['csci1100' => [], 'csci1200' => [], 'csci2300' => []]);

        // A course with a saved color does not use up one of the default colors
        $this->assertEquals([
            'f24csci1100' => 'var(--category-color-1)',
            'f24csci1200' => '#00ff00',
            'f24csci2300' => 'var(--category-color-2)',
        ], $info->getColors());
    }

    public function testDefaultColorsWrapAfterEight(): void {
        $courses = [];
        $calendar_items = [];
        $expected_cookies = [];
        for ($i = 1; $i <= 9; $i++) {
            $courses[] = $this->createCourse('f24', "course{$i}");
            $calendar_items["course{$i}"] = [];
            $expected_cookies["calendar_color_course{$i}f24"] = 'var(--category-color-' . ($i === 9 ? 1 : $i) . ')';
        }
        $this->expectCookies($expected_cookies);

        $info = $this->loadInfo([], [], $courses, $calendar_items);

        $this->assertEquals('var(--category-color-8)', $info->getColors()['f24course8']);
        $this->assertEquals('var(--category-color-1)', $info->getColors()['f24course9']);
    }

    public function testCalendarItems(): void {
        $_COOKIE['calendar_color_csci1100f24'] = '#ff0000';
        $_COOKIE['calendar_color_csci1200f24'] = '#0000ff';
        $this->expectCookies([]);
        $courses = [$this->createCourse('f24', 'csci1100'), $this->createCourse('f24', 'csci1200')];

        $info = $this->loadInfo([], [], $courses, [
            'csci1100' => [
                $this->createCalendarItem(1, '2024-10-01', 'Exam 1', 'ann'),
                $this->createCalendarItem(2, '2024-10-08', '<i>No class</i>', 'text'),
            ],
            'csci1200' => [$this->createCalendarItem(3, '2024-10-01', 'Quiz', 'text')],
        ]);

        $items = $info->getItemsByDate();
        $this->assertEquals(['2024-10-01', '2024-10-08'], array_keys($items));
        $this->assertEquals([
            [
                'title' => 'Exam 1',
                'status' => 'ann',
                'course' => 'csci1100',
                'semester' => 'f24',
                'icon' => '',
                'url' => '',
                'show_due' => false,
                'submission' => '',
                'status_note' => '',
                'color' => '#ff0000',
                'type' => 'item',
                'date' => '2024-10-01',
                'id' => 1,
            ],
            [
                'title' => 'Quiz',
                'status' => 'text',
                'course' => 'csci1200',
                'semester' => 'f24',
                'icon' => '',
                'url' => '',
                'show_due' => false,
                'submission' => '',
                'status_note' => '',
                'color' => '#0000ff',
                'type' => 'item',
                'date' => '2024-10-01',
                'id' => 3,
            ],
        ], $items['2024-10-01']);
        $this->assertEquals('&lt;i&gt;No class&lt;/i&gt;', $items['2024-10-08'][0]['title']);
    }
}
