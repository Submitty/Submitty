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

    public function __construct(Core $core, $id, $name, $question, array $responses, array $answers, $open, array $user_responses) {
        parent::__construct($core);
        $this->id = $id;
        $this->name = $name;
        $this->question = $question;
        $this->responses = $responses;
        $this->answers = $answers;
        $this->open = $open;
        $this->user_responses = $user_responses;
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
        return $this->user_responses[$user_id];
    }

    public function getScore($user_id) {
        if (!isset($this->user_responses[$user_id])){
            return 0.0;
        }
        return in_array($this->getUserResponse($user_id), $this->answers) ? (float)$this->core->getConfig()->getPollsPtsForCorrect() : (float)$this->core->getConfig()->getPollsPtsForIncorrect();
    }
}