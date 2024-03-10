<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\entities\chat\Chatroom;
use app\entities\chat\Message;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use app\views\ChatroomView;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class ChatroomController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat", methods={"GET"})
     */
    public function showChatroomsPage(): WebResponse {
        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $user = $this->core->getUser();
        $chatrooms = $repo->findBy([], ['id' => 'ASC']);
        $active_chatrooms = $repo->findBy(['is_active' => true], ['id' => 'ASC']);

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
     * @Route("/courses/{_semester}/{_course}/chat/newChatroom", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function addChatroom(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();

        $chatroom = new Chatroom();
        $chatroom->setTitle($_POST['title']);
        $chatroom->setDescription($_POST['description']);
        $chatroom->setHostId($this->core->getUser()->getId());
        $chatroom->setHostName($this->core->getUser()->getDisplayFullName());

        $em->persist($chatroom);
        $em->flush();

        $this->core->addSuccessMessage("Chatroom successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}", methods={"GET"})
     * @param string $chatroom_id
     * @return RedirectResponse|WebResponse
     */
     public function getChatroom(string $chatroom_id): WebResponse|RedirectResponse {
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
        );
     }

     /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/anonymous", methods={"GET"})
     * @param string $chatroom_id
     * @return RedirectResponse|WebResponse
     */
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
            $chatroom, true,
        );
     }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/deleteChatroom", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function deleteChatroom(): JsonResponse {
        $chatroom_id = intval($_POST['chatroom_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();

        /** @var \app\repositories\chat\ChatroomRepository $repo */
        $repo = $em->getRepository(Chatroom::class);

        /** @var Chatroom|null */
        $chatroom = $repo->find($chatroom_id);

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
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/edit", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function editChatroom(int $chatroom_id): RedirectResponse {
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
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/toggleOnOff", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
     public function toggleChatroomOnOf(int $chatroom_id): RedirectResponse {
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


    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/messages", methods={"GET"})
     */
    public function fetchMessages(int $chatroom_id): JsonResponse {
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

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/send", methods={"POST"})
     */
    public function addMessage(int $chatroom_id): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $content = $_POST['content'] ?? '';
        $userId = $_POST['user_id'] ?? null;
        $displayName = $_POST['display_name'] ?? '';
        $role = $_POST['role'] ?? 'student';

        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);
        $message = new Message();
        $message->setChatroom($chatroom);
        $message->setUserId($userId);
        $message->setDisplayName($displayName);
        $message->setRole($role);
        $message->setContent($content);

        $em->persist($message);
        $em->flush();

        return JsonResponse::getSuccessResponse($message);
    }
}
