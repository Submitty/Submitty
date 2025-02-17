<?php

namespace app\controllers\api;

use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use app\exceptions\AuthorizationException;


class CourseController extends AbstractController {
    public function routes() {
        $routes = [
            'POST' => [
                '/api/courses/unregister' => 'unregisterFromCourse'
            ]
        ];
        return $routes;
    }

    // unregisters user from a course
    public function unregisterFromCourse() {
        // first, getting information about the course and user
        $request_data = $this->core->getRequest()->getContent();
        $data = json_decode($request_data, true);
        
        if (!isset($data['semester']) || !isset($data['course'])) {
            return JsonResponse::error('Missing parameters');
        }
        
        $semester = $data['semester'];
        $course = $data['course'];
        $user_id = $this->core->getUser()->getId();
        
        try {
            // seeing if user is registered for the course
            $course_info = $this->core->getQueries()->getCourseForUser($semester, $course, $user_id);
            
            if (empty($course_info) || $course_info['registration_section'] !== null) {
                return JsonResponse::error('You are not registered in this course.');
            }
            
            // unregistering user
            $this->core->getQueries()->removeUserFromCourse($semester, $course, $user_id);
            
            return JsonResponse::success();
        }
        catch (\Exception $e) {
            $this->core->addErrorMessage('An error occurred while unregistering: ' . $e->getMessage());
            return JsonResponse::error('An error occurred');
        }
    }
}
?>
