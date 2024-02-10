<?php

namespace app\controllers;


use app\libraries\Core;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;

class ChatroomController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat", methods={"GET"})
     */
    public function showChatroomPage(): WebResponse {

        if ($this->core->getUser()->accessAdmin()) {

            return new WebResponse(
                'Chatroom',
                'showChatroomPageInstructor'
            );
        }
        else { // Student view
            return new WebResponse(
                'Chatroom',
                'showChatroomPageStudent'
            );
        }
    }

    // Activates the chatroom
    public function activateChatroom() {
        // Logic to mark the chatroom as active in the database
        // Ensure any previously active chatroom is marked as inactive
    }

    // Deactivates the chatroom
    public function deactivateChatroom() {
        // Logic to mark the chatroom as inactive
    }

    // Handles message submission for the active chatroom
    public function publishMessage() {
        // Insert message into chatroom_messages table
    }

    // Fetches messages for the active chatroom
    public function getMessages() {
        // Fetch messages from chatroom_messages table
    }
}
