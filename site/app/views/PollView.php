<?php

namespace app\views;

use app\models\User;

class PollView extends AbstractView {

    public function showPollsInstructor() {
        return $this->core->getOutput()->renderTwigTemplate("polls/PollPageInstructor.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'polls' => $this->core->getQueries()->getPolls(),
          ]);
    }

    public function showPollsStudent() {
        return $this->core->getOutput()->renderTwigTemplate("polls/PollPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'polls' => $this->core->getQueries()->getPolls(),
          ]);
    }

    public function showNewPollPage() {
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken()
          ]);
    }
}