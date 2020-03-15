<?php

namespace app\views;

use app\models\User;

class PollView extends AbstractView {

    public function showPollsInstructor($polls) {
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageInstructor.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'polls' => $polls
          ]);
    }

    public function showPollsStudent($polls) {
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'polls' => $polls,
          ]);
    }

    public function showNewPollPage() {
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
          ]);
    }

    public function showPollStudent($poll) {
        return $this->core->getOutput()->renderTwigTemplate("polls/PollPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll
          ]);
    }
}