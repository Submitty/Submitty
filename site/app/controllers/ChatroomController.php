<?php

namespace app\controllers;

use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\entities\chat\Chatroom;
use app\entities\chat\Message;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\socket\Client;
use app\libraries\DateUtils;

/**
 * @Enabled("chat")
 */
class ChatroomController extends AbstractController {
    #[Route("/courses/{_semester}/{_course}/chat", methods: ["GET"])]
    public function showChatroomssPage(): WebResponse {
        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatrooms = $repo->findBy([], ['id' => 'ASC']);

        return new WebResponse(
            'Chatroom',
            'showAllChatrooms',
            $chatrooms
        );
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/chat/new", methods:["POST"])]
    public function addChatroom(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();

        $hostId = $this->core->getUser()->getId();
        $hostName = $this->core->getUser()->getDisplayFullName();
        $chatroom = new Chatroom($hostId, $hostName, $_POST['title'], $_POST['description']);
        if (!isset($_POST['allow-anon'])) {
            $chatroom->setAllowAnon(false);
        }

        $em->persist($chatroom);
        $em->flush();

        $this->core->addSuccessMessage("Chatroom successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    /**
     * @return RedirectResponse|WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}", methods: ["GET"], requirements: ["chatroom_id" => "\d+"])]
    public function getChatroom(string $chatroom_id): WebResponse|RedirectResponse {
        if (!is_numeric($chatroom_id)) {
            $this->core->addErrorMessage("Invalid Chatroom ID");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatroom = $repo->find($chatroom_id);

        if ($chatroom === null) {
            $this->core->addErrorMessage("chatroom not found");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        return new WebResponse(
            'Chatroom',
            'showChatroom',
            $chatroom,
        );
    }

    /**
     * @return RedirectResponse|WebResponse
     */
    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/anonymous", methods: ["GET"], requirements: ["chatroom_id" => "\d+"])]
    public function getChatroomAnon(string $chatroom_id): WebResponse|RedirectResponse {
        if (!is_numeric($chatroom_id)) {
            $this->core->addErrorMessage("Invalid Chatroom ID");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatroom = $repo->find($chatroom_id);

        return new WebResponse(
            'Chatroom',
            'showChatroom',
            $chatroom,
            true,
        );
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/chat/delete", methods: ["POST"])]
    public function deleteChatroom(): JsonResponse {
        $chatroom_id = intval($_POST['chatroom_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();

        $repo = $em->getRepository(Chatroom::class);

        $chatroom = $repo->find($chatroom_id);
        if ($chatroom === null) {
            return JsonResponse::getFailResponse('Invalid Chatroom ID');
        }
        foreach ($chatroom->getMessages() as $message) {
            $em->remove($message);
        }
        unset($_SESSION["anon_name_chatroom_{$chatroom_id}"]);
        $em->remove($chatroom);
        $em->flush();
        return JsonResponse::getSuccessResponse();
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/edit", methods: ["POST"])]
    public function editChatroom(string $chatroom_id): RedirectResponse {
        $em = $this->core->getCourseEntityManager();
        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);

        if (!$chatroom) {
            $this->core->addErrorMessage("Chatroom not found");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        if (isset($_POST['title'])) {
            $chatroom->setTitle($_POST['title']);
        }
        if (isset($_POST['description'])) {
            $chatroom->setDescription($_POST['description']);
        }
        if (isset($_POST['allow-anon'])) {
            $chatroom->setAllowAnon(true);
        }
        else {
            $chatroom->setAllowAnon(false);
        }

        $em->flush();

        $this->core->addSuccessMessage("Chatroom successfully updated");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    /**
     * @AccessControl(role="INSTRUCTOR")
     */
    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/toggleOnOff", methods: ["POST"], requirements: ["chatroom_id" => "\d+"])]
    public function toggleChatroomOnOff(string $chatroom_id): RedirectResponse {
        $em = $this->core->getCourseEntityManager();
        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);

        if ($chatroom === null) {
            $this->core->addErrorMessage("Chatroom not found");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        $chatroom->toggle_on_off();

        $em->flush();

        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/messages", methods: ["GET"], requirements: ["chatroom_id" => "\d+"])]
    public function fetchMessages(string $chatroom_id): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $messages = $em->getRepository(Message::class)->findBy(['chatroom' => $chatroom_id], ['timestamp' => 'ASC']);

        $formattedMessages = array_map(function ($message) {
            return [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s'),
                'user_id' => $message->getUserId(),
                'display_name' => $message->getDisplayName(),
                'role' => $message->getRole()
            ];
        }, $messages);

        return JsonResponse::getSuccessResponse($formattedMessages);
    }

    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/send", methods: ["POST"], requirements: ["chatroom_id" => "\d+"])]
    public function addMessage(string $chatroom_id): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $msg_json = [];
        $msg_json['content'] = $_POST['content'];
        $msg_json['user_id']= $_POST['user_id'];
        $msg_json['display_name'] = $_POST['display_name'] ?? '';
        $msg_json['role'] = $_POST['role'] ?? 'student';
        $msg_json['type'] = 'chat_message';
        $msg_json['timestamp'] = date("Y-m-d H:i:s");
        $msg_json['page'] = $this->core->getConfig()->getTerm() . '-' . $this->core->getConfig()->getCourse() . "-chatroom_$chatroom_id";
        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);

        $message = new Message($msg_json['user_id'], $msg_json['display_name'], $msg_json['role'], $msg_json['content'], $chatroom);

        try {
            $client = new Client($this->core);
            $client->json_send($msg_json);
        } catch (WebSocket\ConnectionException $e) {
            $this->core->addNoticeMessage("WebSocket Server is down, page won't load dynamically.");
        }
        

        $em->persist($message);
        $em->flush();

        return JsonResponse::getSuccessResponse($message);
    }
}
