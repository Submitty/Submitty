<?php

namespace app\views;

use app\libraries\Core;
use app\libraries\Output;
use app\entities\chat\Chatroom;

class ChatroomView extends AbstractView {
    public function __construct(Core $core, Output $output) {
        parent::__construct($core, $output);
        $this->core->getOutput()->addBreadcrumb("Live Chat", $this->core->buildCourseUrl(['chat']));
        $this->core->getOutput()->addInternalCss('chatroom.css');
        $this->core->getOutput()->addInternalJs('websocket.js');
    }

    /**
     * @param Chatroom[] $chatrooms Array of Chatroom objets
     */
    public function showAllChatrooms(array $chatrooms): string {
        $chatroom_rows = [];
        foreach ($chatrooms as $chatroom) {
            $chatroom_rows[] = [
                'id' => $chatroom->getId(),
                'title' => $chatroom->getTitle(),
                'description' => $chatroom->getDescription(),
                'hostName' => $chatroom->getHostName(),
                'isAllowAnon' => $chatroom->isAllowAnon(),
                'isActive' => $chatroom->isActive(),
                'isReadOnly' => $chatroom->isReadOnly(),
                'allowReadOnlyAfterEnd' => $chatroom->allowReadOnlyAfterEnd()
            ];
        }
        return $this->core->getOutput()->renderTwigTemplate("Vue.twig", [
            "type" => "page",
            "name" => "ChatroomListPage",
            "args" => [
                "chatrooms" => $chatroom_rows,
                "userAdmin" => $this->core->getUser()->accessAdmin(),
                "baseUrl" => $this->core->buildCourseUrl() . '/chat',
                "csrfToken" => $this->core->getCsrfToken(),
            ]
        ]);
    }

    public function showChatroom(Chatroom $chatroom, bool $anonymous = false): string {
        $this->core->getOutput()->addBreadcrumb("Chatroom");
        $user = $this->core->getUser();
        $display_name = $user->getDisplayFullName();
        if ($anonymous) {
            $display_name = $chatroom->calcAnonName($user->getId());
        }
        else {
            if (!$user->accessAdmin()) {
                $display_name = $user->getDisplayAbbreviatedName();
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("Vue.twig", [
            "type" => "page",
            "name" => "ChatroomPage",
            "args" => [
                "chatroomId" => $chatroom->getId(),
                "chatroomTitle" => $chatroom->getTitle(),
                "baseUrl" => $this->core->buildCourseUrl() . '/chat',
                "csrfToken" => $this->core->getCsrfToken(),
                "userId" => $user->getId(),
                "displayName" => $display_name,
                "userAdmin" => $user->accessAdmin(),
                "isAnonymous" => $anonymous,
                "readOnly" => $chatroom->isReadOnly()
            ]
        ]);
    }
}
