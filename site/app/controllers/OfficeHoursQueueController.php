<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\OfficeHoursQueueStudent;
use app\libraries\routers\AccessControl;

/**
 * Class OfficeHoursQueueController
 *
 */
class OfficeHoursQueueController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @param
     * @Route("/{_semester}/{_course}/OfficeHoursQueue")
     * @return Response
     */
     public function showQueue(){
       if(!$this->core->getConfig()->isQueueEnabled()){
         return Response::RedirectOnlyResponse(
             new RedirectResponse($this->core->buildCourseUrl(['home']))
         );
       }
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
      * @Route("/{_semester}/{_course}/OfficeHoursQueue/add", methods={"POST"})
      * @return Response
      */
      public function addPerson(){
        $section_id = $this->core->getQueries()->isValidCode($_POST['code']);
        if($_POST['name'] !== "" && !is_null($section_id) && $this->core->getQueries()->isQueueOpen()){
          //Add the user to the database
          $oh_queue = $this->core->getQueries()->getQueueByUser($this->core->getUser()->getId());
          if(!$oh_queue->isInQueue()){
            if($this->core->getQueries()->addUserToQueue($this->core->getUser()->getId(), $_POST['name'])){
              $this->core->addSuccessMessage("Added to queue");
            }
            else{
              $this->core->addErrorMessage("Unable to add to queue");
            }

          }
          else{
            $this->core->addErrorMessage("You are already in the queue");
          }
        }else{
          if($_POST['name'] == ""){
            $this->core->addErrorMessage("Invalid Name");
          }
          else if(is_null($section_id)){
            $this->core->addErrorMessage("Invalid Code");
          }
          else if(!$this->core->getQueries()->isQueueOpen()){
            $this->core->addErrorMessage("Queue is closed");
          }

        }
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
        );
      }

      /**
       * @param
       * @Route("/{_semester}/{_course}/OfficeHoursQueue/startHelp", methods={"POST"})
       * @AccessControl(role="LIMITED_ACCESS_GRADER")
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
       * @Route("/{_semester}/{_course}/OfficeHoursQueue/finishHelp", methods={"POST"})
       * @AccessControl(role="LIMITED_ACCESS_GRADER")
       * @return Response
       */
      public function finishHelpPerson(){
        if($this->core->getUser()->accessGrading())
          $this->core->getQueries()->finishHelpUser($_POST['user_id'], $this->core->getUser()->getId());
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
        );
      }

      /**
       * @param
       * @Route("/{_semester}/{_course}/OfficeHoursQueue/toggle", methods={"POST"})
       * @AccessControl(role="LIMITED_ACCESS_GRADER")
       * @return Response
       */
       public function toggleQueue(){
         if(!$this->core->getUser()->accessGrading()){
           return Response::RedirectOnlyResponse(
             new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
           );
         }
          if($_POST['queue_open'] == "Open Queue"){
            $this->core->getQueries()->openQueue();
          } else{
            $this->core->getQueries()->closeQueue();
          }
          return Response::RedirectOnlyResponse(
              new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
          );
       }

      /**
       * @param
       * @Route("/{_semester}/{_course}/OfficeHoursQueue/remove", methods={"POST"})
       * @return Response
       */
       public function removePerson(){
         if(!$this->core->getUser()->accessGrading()){
           if($this->core->getUser()->getId() != $_POST['user_id']){
             $this->core->addErrorMessage("Permission denied to remove that person");
             return Response::RedirectOnlyResponse(
                 new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
             );
           }
           $this->core->addSuccessMessage("Removed from queue");
         }
         $this->core->getQueries()->removeUserFromQueue($_POST['user_id'], $this->core->getUser()->getId());
         return Response::RedirectOnlyResponse(
             new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
         );
       }

       /**
        * @param
        * @Route("/{_semester}/{_course}/OfficeHoursQueue/code", methods={"POST"})
        * @AccessControl(role="LIMITED_ACCESS_GRADER")
        * @return Response
        */
        public function generateNewCode(){
          if(!$this->core->getUser()->accessGrading()){
            return Response::RedirectOnlyResponse(
              new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
            );
          }
          $this->core->getQueries()->openQueue();
          return Response::RedirectOnlyResponse(
              new RedirectResponse($this->core->buildCourseUrl(['OfficeHoursQueue']))
          );
        }


}
