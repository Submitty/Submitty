<?php

namespace app\views;

use app\libraries\Core;
use app\libraries\Output;
use app\entities\chat\Chatroom;

class ChatroomView extends AbstractView {
    public function __construct(Core $core, Output $output) {
        parent::__construct($core, $output);
        $this->core->getOutput()->addBreadcrumb("Live Lecture Chat", $this->core->buildCourseUrl(['chat']));
        $this->core->getOutput()->addInternalCss('chatroom.css');
        $this->core->getOutput()->addInternalJs('chatroom.js');
        $this->core->getOutput()->addInternalJs('websocket.js');
    }

    /**
     * @param Chatroom[] $chatrooms Array of Chatroom objets
     */
    public function showChatPageInstructor(array $chatrooms): string {
        return $this->core->getOutput()->renderTwigTemplate("chat/ChatPageIns.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
            'chatrooms' => $chatrooms
        ]);
    }

    /**
     * @param Chatroom[] $chatrooms Array of Chatroom objets
     */
    public function showChatPageStudent(array $chatrooms): string {
        return $this->core->getOutput()->renderTwigTemplate("chat/ChatPageStu.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
            'chatrooms' => $chatrooms
        ]);
    }

    /**
     * @param Chatroom[] $chatrooms Array of Chatroom objets
     */
    public function showAllChatrooms(array $chatrooms): string {
        return $this->core->getOutput()->renderTwigTemplate("chat/AllChatroomsPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'chatrooms' => $chatrooms,
            'user_admin' => $this->core->getUser()->accessAdmin()
        ]);
    }

    public function showChatroom(Chatroom $chatroom, bool $anonymous = false): string {
        $this->core->getOutput()->addBreadcrumb("Chatroom");
        $user = $this->core->getUser();
        $display_name = $user->getDisplayFullName();
        $roomId = $chatroom->getId();
        $sessKey = "anon_name_chatroom_{$roomId}";
        if ($anonymous) {
            if (!isset($_SESSION[$sessKey])) {
                $adjectives = ["Quick","Lazy","Cheerful","Pensive","Mysterious","Bright","Sly","Brave","Calm","Eager","Fierce","Gentle","Jolly","Kind","Lively","Nice","Proud","Quiet","Rapid","Swift"];
                $nouns      = ["Duck","Goose","Swan","Eagle","Parrot","Owl","Sparrow","Robin","Pigeon","Falcon","Hawk","Flamingo","Pelican","Seagull","Cardinal","Canary","Finch","Hummingbird"];
                $adj  = $adjectives[array_rand($adjectives)];
                $noun = $nouns[array_rand($nouns)];
                $_SESSION[$sessKey] = "Anonymous {$adj} {$noun}";
            }
            $display_name = $_SESSION[$sessKey];
        }
        else {
            if (!$user->accessAdmin()) {
                $display_name = $user->getDisplayedGivenName() . " " . substr($user->getDisplayedFamilyName(), 0, 1) . ".";
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("chat/Chatroom.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/chat',
            'semester' => $this->core->getConfig()->getTerm(),
            'course' => $this->core->getConfig()->getCourse(),
            'chatroom' => $chatroom,
            'user_admin' => $this->core->getUser()->accessAdmin(),
            'user_id' => $this->core->getUser()->getId(),
            'user_display_name' => $display_name,
            'anonymous' => $anonymous,
        ]);
    }
}
