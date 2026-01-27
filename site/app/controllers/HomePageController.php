<?php

namespace app\controllers;

use app\libraries\response\RedirectResponse;
use app\models\Course;
use app\models\User;
use app\libraries\Core;
use app\entities\Term;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\models\Notification;
use Symfony\Component\Routing\Annotation\Route;
use app\controllers\SelfRejoinController;

/**
 * Class HomePageController
 *
 * Controller to deal with the submitty home page. Once the user has been authenticated, but before they have
 * selected which course they want to access, they are forwarded to the home page.
 */
class HomePageController extends AbstractController {
    /** @var array<Course> */
    private array $courses = [];

    /**
     * HomePageController constructor.
     *
     * @param Core $core Core Submitty application object
     */
    public function __construct(Core $core) {
        parent::__construct($core);
        $user = $this->core->getUser();
        if ($user !== null) {
            $this->courses = $this->core->getQueries()->getCourseForUserId($user->getId());
        }
    }

    /**
     * @param string|null $user_id
     * @param bool|string $as_instructor
     * @return MultiResponse
     */
    #[Route("/api/courses", methods: ["GET"])]
    #[Route("/home/courses", methods: ["GET"])]
    public function getCourses($user_id = null, $as_instructor = false) {
        if ($as_instructor === 'true') {
            $as_instructor = true;
        }

        $user = $this->core->getUser();
        if (is_null($user_id) || $user->getAccessLevel() !== User::LEVEL_SUPERUSER) {
            $user_id = $user->getId();
        }

        $unarchived_courses = $this->courses;
        $archived_courses = $this->core->getQueries()->getCourseForUserId($user_id, true);
        $dropped_courses = $this->core->getQueries()->getCourseForUserId($user_id, false, true);
        $self_registration_courses = $this->core->getQueries()->getSelfRegistrationCourses($user_id);
        if ($as_instructor) {
            $archived_courses = array_filter($archived_courses, function (Course $course) use ($user_id) {
                return $this->core->getQueries()->checkIsInstructorInCourse($user_id, $course->getTitle(), $course->getTerm());
            });

            $unarchived_courses = array_filter($unarchived_courses, function (Course $course) use ($user_id) {
                return $this->core->getQueries()->checkIsInstructorInCourse($user_id, $course->getTitle(), $course->getTerm());
            });
        }

        $self_rejoin_tester = new SelfRejoinController($this->core);
        $dropped_courses = array_filter(
            $dropped_courses,
            function (Course $course) use ($self_rejoin_tester, $user_id) {
                return $self_rejoin_tester->canRejoinCourse($user_id, $course->getTitle(), $course->getTerm());
            }
        );

        $callback = function (Course $course) {
            return $course->getCourseInfo();
        };

        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getSuccessResponse([
                "unarchived_courses" => array_map($callback, $unarchived_courses),
                "archived_courses" => array_map($callback, $archived_courses),
                "dropped_courses" => array_map($callback, $dropped_courses),
                "self_registration_courses" => array_map($callback, $self_registration_courses)
            ])
        );
    }

    /**
     * @param null $user_id
     * @return MultiResponse
     */
    #[Route("/home/groups")]
    public function getGroups($user_id = null): MultiResponse {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        if (is_null($user_id) || $user->getAccessLevel() !== User::LEVEL_SUPERUSER) {
            $user_id = $user->getId();
        }

        $groups = $this->core->getQueries()->getUserGroups($user_id);

        return new MultiResponse(
            JsonResponse::getSuccessResponse($groups)
        );
    }

    #[Route("/home/go_to_course_notifications", methods: ["POST"])]
    public function goToCourseNotifications(): void {
        $courses = $this->courses;
        $course_title = $_POST['course'];
        foreach ($courses as $course) {
            if ($course->getTitle() === $course_title) {
                $term = $course->getTerm();
                $this->core->loadCourseConfig($term, $course_title);
                $this->core->loadCourseDatabase();
                $url = $this->core->buildCourseUrl(['notifications']);
                $this->core->redirect($url);
                return;
            }
        }
        $this->core->addErrorMessage("Course not found.");
        $this->core->redirect($this->core->buildUrl(['home']));
    }

    #[Route("/home/mark_seen", methods: ["POST"])]
    public function markNotificationAsSeen(): void {
        $courses = $this->courses;
        $user_id = $this->core->getUser()->getId();
        $original_config = clone $this->core->getConfig();
        $course_title = $_POST['course'];
        $notification_id = $_POST['notification_id'];
        foreach ($courses as $course) {
            if ($course->getTitle() === $course_title) {
                $term = $course->getTerm();
                $this->core->loadCourseConfig($term, $course_title);
                $this->core->loadCourseDatabase();
                $this->core->getQueries()->markNotificationAsSeen($user_id, $notification_id);
                break;
            }
        }
        $this->core->setConfig($original_config);
    }

    /**
     * Returns recent all recent notifications for a user,
     * and the total count of their unseen notifications.
     * @param int $unseen_count
     * @return array<Notification>
     */
    private function getHomeNotificationData(int &$unseen_count = 0): array {
        $user_id = $this->core->getUser()->getId();
        $courses = $this->courses;
        $results = [];
        $original_config = clone $this->core->getConfig();

        foreach ($courses as $course) {
            $term = $course->getTerm();
            $course_name = $course->getTitle();
            $this->core->loadCourseConfig($term, $course_name);
            $this->core->loadCourseDatabase();
            $course_db = $this->core->getCourseDB();
            $course_display_name = $course->getDisplayName();
            $results = array_merge($results, $this->core->getQueries()->getRecentUserNotifications($user_id, $term, $course_name, $course_db, $course_display_name));
            $unseen_count += (int) $this->core->getQueries()->getUnreadNotificationsCount($user_id, null);
        }

        usort($results, fn($a, $b) => $a->getElapsedTime() <=> $b->getElapsedTime());

        $this->core->setConfig($original_config);
        $this->core->loadCourseDatabase();
        $unseen_count = $unseen_count;
        return $results;
    }

    /**
     * Returns the counts of unseen notifications in each of the user's courses
     * @return JsonResponse
     */
    #[Route("/home/get_unseen_counts", methods: ["GET"])]
    public function getUnseenNotificationCounts(): JsonResponse {
        $user_id = $this->core->getUser()->getId();
        $courses = $this->courses;
        $results = [];
        $original_config = clone $this->core->getConfig();

        foreach ($courses as $course) {
            $term = $course->getTerm();
            $title = $course->getTitle();
            $this->core->loadCourseConfig($term, $title);
            $this->core->loadCourseDatabase();
            $count = $this->core->getQueries()->getUnreadNotificationsCount($user_id, null);
            $results[] = [
                "term" => $term,
                "title" => $title,
                "name" => $course->getDisplayName(),
                "count" => $count,
            ];
        }

        $this->core->setConfig($original_config);
        $this->core->loadCourseDatabase();

        return JsonResponse::getSuccessResponse($results);
    }

    /**
     * Mark notifications from 1 or multiple courses as seen
     * @return JsonResponse
     */
    #[Route("/home/mark_all_seen", methods: ["POST"])]
    public function markSeen(): JsonResponse {
        $user_id = $this->core->getUser()->getId();
        $courses = $_POST['courses'] ?? [];

        if (!is_array($courses)) {
            $courses = [];
        }
        $original_config = clone $this->core->getConfig();

        foreach ($courses as $course) {
            $term   = $course["term"];
            $course = $course["course"];
            $this->core->loadCourseConfig($term, $course);
            $this->core->loadCourseDatabase();
            $this->core->getQueries()->markNotificationAsSeen($user_id, -1);
        }

        $this->core->setConfig($original_config);
        $this->core->loadCourseDatabase();

        return JsonResponse::getSuccessResponse("Marked seen");
    }

    /**
     * Display the HomePageView to the student.
     *
     * @return MultiResponse
     */
    #[Route("/home")]
    public function showHomepage() {
        $courses = $this->getCourses()->json_response->json;
        $unseen_count = 0;
        $notifications = $this->getHomeNotificationData($unseen_count);
        return new MultiResponse(
            null,
            new WebResponse(
                ['HomePage'],
                'showHomePage',
                $this->core->getUser(),
                $courses["data"]["unarchived_courses"],
                $courses["data"]["dropped_courses"],
                $courses["data"]["archived_courses"],
                $courses["data"]["self_registration_courses"],
                $notifications,
                $unseen_count
            )
        );
    }

    #[Route("/home/courses/new", methods: ["POST"])]
    #[Route("/api/courses", methods: ["POST"])]
    public function createCourse() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        if (
            !isset($_POST['course_semester'])
            || !isset($_POST['course_title'])
            || !isset($_POST['head_instructor'])
            || !isset($_POST['group_name'])
            || $_POST['group_name'] === ""
        ) {
            $error = "Semester, course title, head instructor, or group name not set.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }

        $semester = trim($_POST['course_semester']);
        $course_title = trim(strtolower($_POST['course_title']));
        $head_instructor = $_POST['head_instructor'];

        if ($user->getAccessLevel() === User::LEVEL_FACULTY && $head_instructor !== $user->getId()) {
            $error = "You can only create course for yourself.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }

        if (empty($this->core->getQueries()->getSubmittyUser($head_instructor))) {
            $error = "Head instructor doesn't exist.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }

        if ($this->core->getQueries()->courseExists($_POST['course_semester'], $_POST['course_title'])) {
            $error = "Course with that semester/title already exists.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }

        $group_name = $_POST['group_name'];

        try {
            $group_check = $this->core->curlRequest(
                $this->core->getConfig()->getCgiUrl() . "group_check.cgi" . "?" . http_build_query(
                    [
                        'head_instructor' => $head_instructor,
                        'group_name' => $group_name
                    ]
                )
            );

            if (empty($group_check) || empty($group_name)) {
                $error = "Invalid group name.";
                $this->core->addErrorMessage($error);
                return new MultiResponse(
                    JsonResponse::getFailResponse($error),
                    null,
                    new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
                );
            }

            if (json_decode($group_check, true)['status'] === 'fail') {
                $error = "The instructor is not in the correct Linux group.\n Please contact sysadmin for more information.";
                $this->core->addErrorMessage($error);
                return new MultiResponse(
                    JsonResponse::getFailResponse($error),
                    null,
                    new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
                );
            }

            if (json_decode($group_check, true)['status'] === 'error') {
                $error = "The Linux group does not have the correct members for submitty use";
                $this->core->addErrorMessage($error);
                return new MultiResponse(
                    JsonResponse::getFailResponse($error),
                    null,
                    new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
                );
            }
        }
        catch (\Exception $e) {
            $error = "Server error.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getErrorResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home']))
            );
        }

        $json = [
            "job" => 'CreateCourse',
            'semester' => $semester,
            'course' => $course_title,
            'head_instructor' => $head_instructor,
            'group_name' => $group_name
        ];

        $json = json_encode($json, JSON_PRETTY_PRINT);
        file_put_contents('/var/local/submitty/daemon_job_queue/create_' . $semester . '_' . $course_title . '.json', $json);

        $this->core->addSuccessMessage("Course creation request successfully sent.\n Please refresh the page later.");
        return new MultiResponse(
            JsonResponse::getSuccessResponse(null),
            null,
            new RedirectResponse($this->core->buildUrl(['home']))
        );
    }

    #[Route("/home/courses/new", methods: ["GET"])]
    public function createCoursePage() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        if ($user->getAccessLevel() === User::LEVEL_SUPERUSER) {
            $faculty = $this->core->getQueries()->getAllFaculty();
        }

        $term_names = $this->core->getSubmittyEntityManager()
            ->createQueryBuilder()
            ->select('term.name')
            ->from(Term::class, 'term')
            ->orderBy('term.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return new MultiResponse(
            null,
            new WebResponse(
                ['HomePage'],
                'showCourseCreationPage',
                $faculty ?? null,
                $this->core->getUser()->getId(),
                $term_names,
                $this->core->getUser()->getAccessLevel() === User::LEVEL_SUPERUSER,
                $this->core->getCsrfToken(),
                $this->core->getQueries()->getAllCoursesForUserId($this->core->getUser()->getId())
            )
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/home/group/users")]
    public function getGroupUsers($group_name = null): MultiResponse {
        if (!$this->core->getUser()->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        $group_file = fopen("/etc/group", "r");
        $group_content = fread($group_file, filesize("/etc/group"));
        fclose($group_file);

        $groups = explode("\n", $group_content);
        foreach ($groups as $group) {
            if (str_starts_with($group, $group_name)) {
                $categories = explode(":", $group);
                $members = array_pop($categories);
                return new MultiResponse(
                    JsonResponse::getSuccessResponse($members)
                );
            }
        }

        return new MultiResponse(
            JsonResponse::getErrorResponse("Group not found")
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/term/new", methods: ["POST"])]
    public function addNewTerm() {
        if (!$this->core->getUser()->isSuperUser()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }
        $response = new MultiResponse();
        if (isset($_POST['term_id']) && isset($_POST['term_name']) && isset($_POST['start_date']) && isset($_POST['end_date'])) {
            $term_id = $_POST['term_id'];
            $term_name = $_POST['term_name'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $em = $this->core->getSubmittyEntityManager();
            $term = $em->find(Term::class, $term_id);

            if ($term !== null) {
                $this->core->addErrorMessage("Term id already exists.");
            }
            elseif ($end_date < $start_date) {
                $this->core->addErrorMessage("End date should be after Start date.");
            }
            else {
                $term = new Term(
                    $term_id,
                    $term_name,
                    $start_date,
                    $end_date,
                );
                $em->persist($term);
                $em->flush();
                $this->core->addSuccessMessage("Term added successfully.");
            }
            $url = $this->core->buildUrl(['home', 'courses', 'new']);
            $response = $response->RedirectOnlyResponse(new RedirectResponse($url));
        }
        return $response;
    }

    /**
     * @return MultiResponse|WebResponse
     */
    #[Route("/update", methods: ["GET"])]
    public function systemUpdatePage() {
        $user = $this->core->getUser();
        if (is_null($user) || $user->getAccessLevel() !== User::LEVEL_SUPERUSER) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        $this->core->getOutput()->addInternalJs('system-update.js');
        $this->core->getOutput()->addInternalCss('system-update.css');
        return new WebResponse(
            'HomePage',
            'showSystemUpdatePage',
            $this->core->getCsrfToken()
        );
    }
}
