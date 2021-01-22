<?php

namespace app\views;

use app\models\User;
use app\libraries\FileUtils;

class PollView extends AbstractView {

    public function showPollsInstructor($todays_polls, $older_polls, $future_polls, $dropdown_states) {
        $this->core->getOutput()->addBreadcrumb("Polls");
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
        $this->core->getOutput()->addBreadcrumb("Polls");
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
        $this->core->getOutput()->addBreadcrumb("Polls", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("New Poll");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
          ]);
    }

    public function showPollStudent($poll) {
        $this->core->getOutput()->addBreadcrumb("Polls", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("View Poll");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/PollPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'user_id' => $this->core->getUser()->getId(),
          ]);
    }

    public function editPoll($poll) {
        $this->core->getOutput()->addBreadcrumb("Polls", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("Edit Poll");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll
          ]);
    }

    public function viewResults($poll, $results) {
        $this->core->getOutput()->addBreadcrumb("Polls", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("View Results");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('plotly', 'plotly.js'));
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/ViewPollResults.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'results' => $results
          ]);
    }
}
