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
    protected $user_responses;
    protected $release_date;
    protected $status;

    public function __construct(Core $core, $id, $name, $question, array $responses, array $answers, $status, array $user_responses, $release_date) {
        parent::__construct($core);
        $this->id = $id;
        $this->name = $name;
        $this->question = $question;
        $this->responses = $responses;
        $this->answers = $answers;
        $this->status = $status;
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
        return array_keys($this->responses);
    }

    public function getResponsesWithKeys() {
        return $this->responses;
    }

    public function getAnswers() {
        return $this->answers;
    }

    public function isOpen() {
        return $this->status == "open";
    }

    public function isClosed() {
        return $this->status == "closed";
    }

    public function isEnded() {
        return $this->status == "ended";
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

    public function getResponseString($response_id) {
        if (isset($this->responses[$response_id])) {
            return $this->responses[$response_id];
        }
        return "No Response";
    }

    public function getReleaseDate() {
        return $this->release_date;
    }

    public function isCorrect($response) {
        return in_array($response, $this->getResponses()) && in_array($response, $this->answers);
    }

    public function getScore($user_id) {
        if (!isset($this->user_responses[$user_id])) {
            return 0.0;
        }
        return $this->isCorrect($this->user_responses[$user_id]) ? (float) $this->core->getConfig()->getPollsPtsForCorrect() : (float) $this->core->getConfig()->getPollsPtsForIncorrect();
    }

    public function isInPast() {
        return date("Y-m-d") > $this->release_date;
    }

    public function isInFuture() {
        return date("Y-m-d") < $this->release_date;
    }

    public function isToday() {
        return date("Y-m-d") == $this->release_date;
    }
}
