<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class PollModel extends AbstractModel {
    protected $id;
    protected $name;
    protected $question;
    protected $responses;
    protected $answers;
    protected $open;
    protected $user_response;
    protected $release_date;

    public function __construct(Core $core, $id, $name, $question, array $responses, array $answers, $open, array $user_responses, $release_date) {
        parent::__construct($core);
        $this->id = $id;
        $this->name = $name;
        $this->question = $question;
        $this->responses = $responses;
        $this->answers = $answers;
        $this->open = $open;
        $this->user_responses = $user_responses;
        $this->release_date = $release_date;
    }

    public function getID() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getQuestion() {
        return $this->question;
    }

    public function getResponses() {
        return $this->responses;
    }

    public function getAnswers() {
        return $this->answers;
    }

    public function isOpen() {
        return $this->open;
    }

    public function getUserResponses() {
        return $this->user_responses;
    }

    public function getUserResponse($user_id) {
        if (!isset($this->user_responses[$user_id])) {
            return null;
        }
        return $this->user_responses[$user_id];
    }

    public function getReleaseDate() {
        return $this->release_date;
    }

    public function isCorrect($response) {
        return in_array($response, $this->responses) and in_array($response, $this->answers);
    }

    public function getScore($user_id) {
        if (!isset($this->user_responses[$user_id])){
            return 0.0;
        }
        return $this->isCorrect($this->user_responses[$user_id]) ? (float)$this->core->getConfig()->getPollsPtsForCorrect() : (float)$this->core->getConfig()->getPollsPtsForIncorrect();
    }
}