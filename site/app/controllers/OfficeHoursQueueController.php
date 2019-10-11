<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\models\Notification;
use Symfony\Component\Routing\Annotation\Route;
use app\models\OfficeHoursQueueStudent;
use app\site\libraries\database\DatabaseQueries;

use Exception;

/**
 * Class OfficeHourQueueController
 *
 */
class OfficeHourQueueController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @param
     * @Route("/{_semester}/{_course}/OfficeHoursQueue")
     * @return Response
     */
     public function showQueue(){
       if(!$this->core->getUser()->accessGrading()){
         $oh_queue = $this->core->getQueries()->getQueueByUser($this->core->getUser()->getId());
         return Response::WebOnlyResponse(
           new WebResponse(
             'OfficeHoursQueue',
             'showQueueStudent',
             $oh_queue,
             $this->core->getConfig()->getCourse()
             )
           );
        }
        else{
          $oh_queue = $this->core->getQueries()->getInstructorQueue();
          return Response::WebOnlyResponse(
            new WebResponse(
              'OfficeHoursQueue',
              'showQueueInstructor',
              $oh_queue,
              $this->core->getConfig()->getCourse()
              )
            );
        }
     }
     /**
      * @param
      * @Route("/{_semester}/{_course}/OfficeHoursQueue/add")
      * @return Response
      */
      public function addPerson(){
        if(isset($_POST['name'])){
          //Add the user to the database
          $oh_queue = $this->core->getQueries()->getQueueByUser($this->core->getUser()->getId());
          if(!$oh_queue->isInQueue()){
            $this->core->getQueries()->addUserToQueue($this->core->getUser()->getId(), $_POST['name']);
          }
        }
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
        );
      }

      /**
       * @param
       * @Route("/{_semester}/{_course}/OfficeHoursQueue/startHelp")
       * @return Response
       */
      public function startHelpPerson(){
        if($this->core->getUser()->accessGrading())
          $this->core->getQueries()->startHelpUser($_POST['user_id']);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
        );
      }

      /**
       * @param
       * @Route("/{_semester}/{_course}/OfficeHoursQueue/finishHelp")
       * @return Response
       */
      public function finishHelpPerson(){
        if($this->core->getUser()->accessGrading())
          $this->core->getQueries()->finishHelpUser($_POST['user_id']);
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
        );
      }

      /**
       * @param
       * @Route("/{_semester}/{_course}/OfficeHoursQueue/remove")
       * @return Response
       */
       public function removePerson(){
         $this->core->getQueries()->removeUserFromQueue($this->core->getUser()->getId());
         return Response::RedirectOnlyResponse(
             new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
         );
       }

}
