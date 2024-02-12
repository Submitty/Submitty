<?php

namespace app\controllers;

use app\entities\poll\Poll;
use app\libraries\Core;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
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

        /** @var \app\repositories\poll\PollRepository */
        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatroom = $repo->findByChatroomId($chatroom_id);

         if (!is_numeric($chatroom_id)) {
            $this->core->addErrorMessage("Invalid Chatroom ID");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

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
     * @Route("/chat/{chatroomId}/message", name="chatroom_post_message", methods={"POST"})
     */
    public function postMessage(int $chatroomId, Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $content = $request->request->get('content');

        $chatroom = $entityManager->getRepository(Chatroom::class)->find($chatroomId);
        if (!$chatroom || !$chatroom->getIsActive()) {
            return new JsonResponse(['error' => 'Chatroom not found or not active.'], Response::HTTP_NOT_FOUND);
        }

        $message = new Message();
        $message->setContent($content);
        $message->setChatroom($chatroom);
        // Set other necessary properties, such as timestamp

        $entityManager->persist($message);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'messageId' => $message->getId()]);
    }

    /**
     * @Route("/chat/{chatroomId}/messages", name="chatroom_messages", methods={"GET"})
     */
    public function getMessages(int $chatroomId, EntityManagerInterface $entityManager): JsonResponse {
        $chatroom = $entityManager->getRepository(Chatroom::class)->find($chatroomId);
        if (!$chatroom) {
            return new JsonResponse(['error' => 'Chatroom not found.'], Response::HTTP_NOT_FOUND);
        }

        $messages = $chatroom->getMessages();
        $response = [];

        foreach ($messages as $message) {
            $response[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s')
            ];
        }

        return new JsonResponse(['messages' => $response]);
    }
}
