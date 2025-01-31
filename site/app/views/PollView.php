<?php

namespace app\views;

use app\entities\poll\Poll;
use app\libraries\Core;
use app\libraries\Output;
use app\libraries\FileUtils;
use app\libraries\PollUtils;
use app\libraries\Utils;

class PollView extends AbstractView {
    public function __construct(Core $core, Output $output) {
        parent::__construct($core, $output);

        $this->core->getOutput()->addBreadcrumb("Submini Polls", $this->core->buildCourseUrl(['polls']));
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addInternalJs('polls.js');
        $this->core->getOutput()->enableMobileViewport();
    }

    /**
     *
     * @param Poll[] $todays_polls
     * @param Poll[] $older_polls
     * @param Poll[] $tomorrow_polls
     * @param Poll[] $future_polls
     */
    public function showPollsInstructor(array $todays_polls, array $older_polls, array $tomorrow_polls, array $future_polls, array $response_counts, array $dropdown_states) {
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageInstructor.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'todays_polls' => $todays_polls,
            'older_polls' => $older_polls,
            'tomorrow_polls' => $tomorrow_polls,
            'future_polls' => $future_polls,
            'dropdown_states' => $dropdown_states,
            'response_counts' => $response_counts,
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse()
          ]);
    }

    /**
     * @param Poll[] $todays_polls
     * @param Poll[] $older_polls
     */
    public function showPollsStudent(array $todays_polls, array $older_polls) {
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'todays_polls' => $todays_polls,
            'older_polls' => $older_polls,
            'user_id' => $this->core->getUser()->getId(),
          ]);
    }

    public function showPoll(Poll $poll, array $response_counts) {
        $this->core->getOutput()->addBreadcrumb("View Poll");
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('plotly', 'plotly.js'));
        $this->core->getOutput()->enableMobileViewport();

        $image_path = $poll->getImagePath();
        $file_data = null;
        if ($image_path !== null) {
            $file_data = base64_encode(file_get_contents($image_path));
            $file_data = 'data: ' . mime_content_type($image_path) . ';charset=utf-8;base64,' . $file_data;
        }

        $response_option_ids = [];
        /** @var \app\entities\poll\Response $response */
        foreach ($poll->getUserResponses() as $response) {
            $response_option_ids[] = $response->getOption()->getId();
        }

        return $this->core->getOutput()->renderTwigTemplate("polls/ViewPoll.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'response_counts' => $response_counts,
            'file_data' => $file_data,
            'user_admin' => $this->core->getUser()->accessAdmin(),
            'is_single_response' => PollUtils::isSingleResponse($poll->getQuestionType()),
            'end_time' => $poll->getEndTime()?->format('Y-m-d\TH:i:s'),
            'timer_enabled' => $poll->isTimerEnabled(),
            'user_id' => $this->core->getUser()->getId()
        ]);
    }

    public function pollForm(?Poll $poll = null) {
        $this->core->getOutput()->addBreadcrumb($poll !== null ? "Edit Poll" : 'New Poll');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $is_survey = $poll?->isSurvey() ?? false;
        return $this->core->getOutput()->renderTwigTemplate("polls/PollForm.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'max_size' => Utils::returnBytes(ini_get('upload_max_filesize')),
            'is_survey' => $is_survey,
          ]);
    }
}
