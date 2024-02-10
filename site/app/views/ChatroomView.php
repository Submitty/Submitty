<?php

namespace app\views;

use app\libraries\Core;
use app\libraries\Output;

class ChatroomView extends AbstractView {
    public function __construct(Core $core, Output $output) {
        parent::__construct($core, $output);

        $this->core->getOutput()->addBreadcrumb("Live Lecture Chat", $this->core->buildCourseUrl(['chat']));
        $this->core->getOutput()->addInternalCss('chatroom.css');
        $this->core->getOutput()->enableMobileViewport();
    }

    public function showChatroomPageInstructor()
    {
        return $this->core->getOutput()->renderTwigTemplate("chatroom/ChatroomIns.twig", [
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse()
        ]);
    }

    public function showChatroomPageStudent()
    {
        return $this->core->getOutput()->renderTwigTemplate("chatroom/ChatroomStu.twig", [
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse()
        ]);
    }
}



