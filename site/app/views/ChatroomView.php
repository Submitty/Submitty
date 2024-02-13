<?php

namespace app\views;

use app\entities\chat\Chatroom;
use app\entities\chat\Message;
use app\libraries\Core;
use app\libraries\Output;
use app\libraries\FileUtils;
use app\libraries\Utils;

class ChatroomView extends AbstractView {
    public function __construct(Core $core, Output $output) {
        parent::__construct($core, $output);
        $this->core->getOutput()->addBreadcrumb("Live Lecture Chat", $this->core->buildCourseUrl(['chat']));
        $this->core->getOutput()->addInternalCss('chatroom.css');
        $this->core->getOutput()->addInternalJs('chatroom.js');
    }

    public function showChatPageInstructor(array $chatrooms) {
        return $this->core->getOutput()->renderTwigTemplate("chat/ChatPageIns.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
            'chatrooms' => $chatrooms
        ]);
    }

    public function showChatPageStudent(array $chatrooms) {
        return $this->core->getOutput()->renderTwigTemplate("chat/ChatPageStu.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
            'chatrooms' => $chatrooms
        ]);
    }

    public function showChatroom($chatroom) {
        $this->core->getOutput()->addBreadcrumb("Chatroom");
        return $this->core->getOutput()->renderTwigTemplate("chat/Chatroom.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
            'chatroom' => $chatroom,
            'user_admin' => $this->core->getUser()->accessAdmin()
        ]);
    }
}



