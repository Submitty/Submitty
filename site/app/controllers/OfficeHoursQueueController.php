<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\models\Notification;
use Symfony\Component\Routing\Annotation\Route;
use app\models\OfficeHoursQueue;
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
       $oh_queue = $this->core->getQueries()->getQueueByUser($this->core->getUser()->getId());
       return Response::WebOnlyResponse(
           new WebResponse(
               'OfficeHoursQueue',
               'showQueue',
               $oh_queue,
               $this->core->getConfig()->getCourse()
           )
       );
     }
     /**
      * @param
      * @Route("/{_semester}/{_course}/OfficeHoursQueue/add")
      * @return Response
      */
      public function addPerson(){
        if(isset($_POST['name'])){
          //Add the user to the database
          $this->core->getQueries()->addUserToQueue($this->core->getUser()->getId(), $_POST['name']);
        }
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
