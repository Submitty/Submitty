<?php

namespace app\controllers;

use app\entities\poll\Poll;
use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\entities\chat\Chatroom;
use app\entities\chat\Message;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

class ChatroomController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat", methods={"GET"})
     */
    public function showChatroomsPage(): WebResponse {
       /** @var \app\repositories\chat\ChatroomRepository */
        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $user = $this->core->getUser();
        $chatrooms = $repo->findAll();
        $active_chatrooms = $repo->findAllActiveChatrooms();

        if ($user->accessAdmin()) {
            return new WebResponse(
                'Chatroom',
                'showChatPageInstructor',
                $chatrooms
            );
        }
        else { // Student view
            return new WebResponse(
                'Chatroom',
                'showChatPageStudent',
                $active_chatrooms
            );
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}", methods={"GET"}, requirements={"chatroom_id": "\d*", })
     * @return RedirectResponse|WebResponse
     */
     public function showChatroom(string $chatroom_id): WebResponse|RedirectResponse {

         if (!is_numeric($chatroom_id)) {
            $this->core->addErrorMessage("Invalid Chatroom ID");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        /** @var \app\repositories\poll\PollRepository */
        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatroom = $repo->find($chatroom_id);

        return new WebResponse(
            'Chatroom',
            'showChatroom',
            $chatroom
        );
     }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/newChatroom", name="new_chatroom", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function addChatroom(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();

        $fields = ['title', 'description'];
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                $this->core->addErrorMessage("Chatroom must fill out all fields");
                return new RedirectResponse($this->core->buildCourseUrl(['chat']));
            }
        }

        $chatroom = new Chatroom();
        $chatroom->setTitle($_POST['title']);
        $chatroom->setDescription($_POST['description']);
        $chatroom->setHostId($this->core->getUser()->getId());
        $em->persist($chatroom);
        $em->flush();

        $this->core->addSuccessMessage("Chatroom successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/deleteChatroom", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function deleteChatroom(): JsonResponse {
        $chatroom_id = intval($_POST['chatroom_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();

        /** @var \app\repositories\chat\ChatroomRepository */
        $repo = $em->getRepository(Chatroom::class);

        /** @var Chatroom|null */
        $chatroom = $repo->find(Chatroom::class, $chatroom_id);
        if ($chatroom === null) {
            return JsonResponse::getFailResponse('Invalid Chatroom ID');
        }

        foreach ($chatroom->getMessages() as $message) {
            $em->remove($message);
        }

        $em->remove($chatroom);
        $em->flush();

        return JsonResponse::getSuccessResponse();
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/messages", name="fetch_chatroom_messages", methods={"GET"})
     */
    public function fetchChatroomMessages(int $chatroom_id): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $messages = $em->getRepository(Message::class)->findBy(['chatroom' => $chatroom_id], ['timestamp' => 'ASC']);

        $formattedMessages = array_map(function ($message) {
            return [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s'),
                'user_id' => $message->getUserId()
            ];
        }, $messages);

        return JsonResponse::getSuccessResponse($formattedMessages);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/send", name="send_chatroom_messages", methods={"POST"})
     */
    public function sendMessage(int $chatroom_id): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $content = $_POST['content'] ?? '';
        $userId = $this->core->getUser()->getId();
        $message = new Message();
        $message->setChatroomId($chatroom_id);
        $message->setUserId($userId);
        $message->setContent($content);
        $message->setTimestamp(new \DateTimeImmutable("now"));

        $em->persist($message);
        $em->flush();

        return JsonResponse::getSuccessResponse("Message sent successfully");
    }
}
