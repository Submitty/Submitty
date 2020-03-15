<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class PollModel extends AbstractModel {
    protected $id;
    protected $name;
    protected $question;
    protected $responses;
    protected $answer;
    protected $open;
    protected $user_response;
    protected $submitter;

    public function __construct(Core $core, $id, $name, $question, array $responses, $answer, $open, $user_response) {
        parent::__construct($core);
        $this->id = $id;
        $this->name = $name;
        $this->question = $question;
        $this->responses = $responses;
        $this->answer = $answer;
        $this->open = $open;
        $this->user_response = $user_response;
        $this->submitter = $this->core->getUser()->getId();
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

    public function getAnswer() {
        return $this->answer;
    }

    public function isOpen() {
        return $this->open;
    }

    public function getUserResponse() {
        return $this->user_response;
    }

    public function getScore() {
        return 1.0;
    }

    public function getSubmitter() {
        return $this->submitter;
    }

    
}