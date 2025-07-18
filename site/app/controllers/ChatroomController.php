<?php

namespace app\controllers;

use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\entities\chat\Chatroom;
use app\entities\chat\Message;
use app\entities\UserEntity;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\socket\Client;
use WebSocket;

#[Enabled(feature: "chat")]
class ChatroomController extends AbstractController {
    /**
     * Send a message over WebSocket.
     *
     * @param array{
     *     type:        string,
     *     socket:      string,
     *     id?:         int,
     *     title?:      string,
     *     description?:string,
     *     allow_anon?: bool,
     *     host_name?:  string,
     *     base_url?:   string,
     *     user_id?:    string,
     *     content?:    string,
     *     display_name?:string,
     *     role?:       string,
     *     timestamp?:  string
     * } $msg_array
     */
    private function sendSocketMessage(array $msg_array): void {
        $msg_array['page'] = $this->core->getConfig()->getTerm() . '-' . $this->core->getConfig()->getCourse() . '-' . $msg_array['socket'];
        $msg_array['user_id'] = $msg_array['user_id'] ?? $this->core->getUser()->getId();
        try {
            $client = new Client($this->core);
            $client->json_send($msg_array);
        }
        catch (WebSocket\ConnectionException $e) {
            $this->core->addNoticeMessage("WebSocket Server is down, page won't load dynamically.");
        }
    }

    #[Route("/courses/{_semester}/{_course}/chat", methods: ["GET"])]
    public function showChatroomsPage(): WebResponse {
        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatrooms = $repo->findBy(['is_deleted' => 'FALSE'], ['id' => 'ASC']);

        return new WebResponse(
            'Chatroom',
            'showAllChatrooms',
            $chatrooms
        );
    }

    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/chat/new", methods:["POST"])]
    public function addChatroom(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();
        $user = $this->core->getUser();
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';

        $userEntity = $em->getRepository(UserEntity::class)->find($user->getId());

        if (!isset($userEntity)) {
            $this->core->addErrorMessage("Host user could not be found.");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        if (trim($title) === '') {
            $this->core->addErrorMessage("Chatroom title cannot be empty");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }
        $chatroom = new Chatroom($userEntity, $title, $description);
        if (!isset($_POST['allow-anon'])) {
            $chatroom->setAllowAnon(false);
        }
        $em->persist($chatroom);
        $em->flush();

        $this->core->addSuccessMessage("Chatroom successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/{anonymous_route_segment?}", methods: ["GET"], requirements: ["chatroom_id" => "\d+", "anonymous_route_segment" => "anonymous"])]
    public function getChatroom(string $chatroom_id, ?string $anonymous_route_segment = null): WebResponse|RedirectResponse {
        $isAnonymous = ($anonymous_route_segment === 'anonymous');

        if (!is_numeric($chatroom_id)) {
            $this->core->addErrorMessage("Invalid chatroom ID");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatroom = $repo->find($chatroom_id);

        if ($chatroom === null || $chatroom->chatDeleted()) {
            $this->core->addErrorMessage("Chatroom not found");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        if ($isAnonymous && $chatroom->isAllowAnon() !== $isAnonymous) {
            $this->core->addErrorMessage("Chatroom does not allow anonymous users");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        if (!$chatroom->isActive() && !$this->core->getUser()->accessAdmin()) {
            $this->core->addErrorMessage("Chatroom not enabled");
            return new RedirectResponse(
                $this->core->buildCourseUrl(['chat'])
            );
        }
        return new WebResponse(
            'Chatroom',
            'showChatroom',
            $chatroom,
            $isAnonymous
        );
    }

    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/chat/delete", methods: ["POST"])]
    public function deleteChatroom(): JsonResponse {
        $chatroom_id = intval($_POST['chatroom_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();

        $repo = $em->getRepository(Chatroom::class);

        $chatroom = $repo->find($chatroom_id);
        if ($chatroom === null) {
            return JsonResponse::getFailResponse('Invalid Chatroom ID');
        }
        $chatroom->deleteChat();
        $em->flush();
        return JsonResponse::getSuccessResponse();
    }

    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/edit", methods: ["POST"])]
    public function editChatroom(string $chatroom_id): RedirectResponse {
        $em = $this->core->getCourseEntityManager();
        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);

        if (!isset($chatroom)) {
            $this->core->addErrorMessage("Chatroom not found");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        if (trim($title) === '') {
            $this->core->addErrorMessage("Chatroom title cannot be empty");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }
        $chatroom->setTitle($title);
        $chatroom->setDescription($description);
        $chatroom->setAllowAnon(isset($_POST['allow-anon']));

        $em->flush();

        $this->core->addSuccessMessage("Chatroom successfully updated");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    #[AccessControl(role: "INSTRUCTOR")]
    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/toggleActiveStatus", methods: ["POST"], requirements: ["chatroom_id" => "\d+"])]
    public function toggleChatroomActiveStatus(string $chatroom_id): RedirectResponse {
        $em = $this->core->getCourseEntityManager();
        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);
        $msg_array = [];

        if ($chatroom === null) {
            $this->core->addErrorMessage("Chatroom not found");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }
        if (!$chatroom->isActive()) {
            $msg_array = [];
            $msg_array['type'] = 'chat_open';
            $msg_array['id'] = $chatroom->getId();
            $msg_array['title'] = $chatroom->getTitle();
            $msg_array['description'] = $chatroom->getDescription();
            $msg_array['allow_anon'] = $chatroom->isAllowAnon();
            $msg_array['host_name'] = $chatroom->getHostName();
            $msg_array['base_url'] = $this->core->buildCourseUrl(['chat']);
            $msg_array['socket'] = "chatrooms";
        }
        else {
            $msg_array = [];
            $msg_array['type'] = 'chat_close';
            $msg_array['id'] = $chatroom->getId();
            $msg_array['socket'] = "chatrooms";
            // indiv_msg_array sends to kick people out of closing chatrooms, msg_array sends to remove/add the chatroom to the chat list
            $indiv_msg_array = [];
            $indiv_msg_array['type'] = 'chat_close';
            $indiv_msg_array['socket'] = "chatroom_$chatroom_id";
            $this->sendSocketMessage($indiv_msg_array);
        }
        $this->sendSocketMessage($msg_array);
        $chatroom->setSessionStartedAt($chatroom->isActive() ? null : new \DateTime("now"));
        $chatroom->toggleActiveStatus();

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

    #[Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/send/{anonymous_route_segment?}", methods: ["POST"], requirements: ["chatroom_id" => "\d+", "anonymous_route_segment" => "anonymous"])]
    public function addMessage(string $chatroom_id, ?string $anonymous_route_segment = null,): JsonResponse {
        $isAnonymous = ($anonymous_route_segment === 'anonymous');
        $em = $this->core->getCourseEntityManager();
        $user = $this->core->getUser();
        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);
        if ($chatroom === null) {
            return JsonResponse::getFailResponse("Chatroom not found");
        }
        if (!$chatroom->isActive() && !$user->accessAdmin()) {
            return JsonResponse::getFailResponse("This chatroom is not enabled");
        }
        if (strcmp($_POST['content'], "") === 0) {
            return JsonResponse::getFailResponse("Can't send blank message");
        }
        $msg_array = [];
        $msg_array['content'] = $_POST['content'];
        $msg_array['type'] = 'chat_message';
        $msg_array['user_id'] = $isAnonymous ? 'null' : $user->getId();
        $display_name = '';
        if ($chatroom->isAllowAnon() && $isAnonymous) {
            $display_name = $chatroom->calcAnonName($user->getId());
        }
        else {
            if ($user->accessAdmin()) {
                $display_name = $user->getDisplayFullName();
            }
            else {
                $display_name = $user->getDisplayAbbreviatedName();
            }
        }
        $msg_array['display_name'] = $display_name;
        $msg_array['role'] = ($user->accessAdmin() && !$isAnonymous) ? 'instructor' : 'student';
        $msg_array['socket'] = "chatroom_$chatroom_id";
        $msg_array['timestamp'] = date("Y-m-d H:i:s");
        $this->sendSocketMessage($msg_array);
        $message = new Message($user->getId(), $msg_array['display_name'], $msg_array['role'], $msg_array['content'], $chatroom);
        $em->persist($message);
        $em->flush();
        return JsonResponse::getSuccessResponse($message);
    }
}
