<?php

namespace app\views;

use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\PollModel;
use app\libraries\PollUtils;

class PollView extends AbstractView {
    public function showPollsInstructor($todays_polls, $older_polls, $future_polls, $dropdown_states) {
        $this->core->getOutput()->addBreadcrumb("Submini Polls");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addInternalJs('polls-dropdown.js');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageInstructor.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'todays_polls' => $todays_polls,
            'older_polls' => $older_polls,
            'future_polls' => $future_polls,
            'dropdown_states' => $dropdown_states,
            'semester' => $this->core->getConfig()->getSemester(),
            'course' => $this->core->getConfig()->getCourse()
          ]);
    }

    public function showPollsStudent($todays_polls, $older_polls) {
        $this->core->getOutput()->addBreadcrumb("Submini Polls");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addInternalJs('polls-dropdown.js');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'todays_polls' => $todays_polls,
            'older_polls' => $older_polls,
            'user_id' => $this->core->getUser()->getId(),
          ]);
    }

    public function showNewPollPage() {
        $this->core->getOutput()->addBreadcrumb("Submini Polls", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("New Poll");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addInternalJs('polls.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'max_size' => Utils::returnBytes(ini_get('upload_max_filesize')),
            'poll_type' => 'single-response'
          ]);
    }

    public function viewPoll($poll) {
        $this->core->getOutput()->addBreadcrumb("Submini Polls", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("View Poll");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('plotly', 'plotly.js'));
        $this->core->getOutput()->enableMobileViewport();
        $image_path = $poll->getImagePath();
        $file_data = null;
        if ($image_path !== null) {
            $file_data = base64_encode(file_get_contents($image_path));
            $file_data = 'data: ' . mime_content_type($image_path) . ';charset=utf-8;base64,' . $file_data;
        }
        $poll_type = PollUtils::isSingleResponse($poll->getQuestionType()) ? "single-response" : "multiple-response";
        $results = $this->core->getQueries()->getResults($poll->getId());
        return $this->core->getOutput()->renderTwigTemplate("polls/ViewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'poll_type' => $poll_type,
            'user_id' => $this->core->getUser()->getId(),
            'user_admin' => $this->core->getUser()->accessAdmin(),
            'file_data' => $file_data,
            'results' => $results
          ]);
    }

    public function editPoll(PollModel $poll) {
        $this->core->getOutput()->addBreadcrumb("Submini Polls", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("Edit Poll");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addInternalJs('polls.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->enableMobileViewport();
        $poll_type = PollUtils::isSingleResponse($poll->getQuestionType()) ? "single-response" : "multiple-response";
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'max_size' => Utils::returnBytes(ini_get('upload_max_filesize')),
            'poll_type' => $poll_type
          ]);
    }
}
