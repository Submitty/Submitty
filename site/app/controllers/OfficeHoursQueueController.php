<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\models\Notification;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class OfficeHourQueueController
 *
 */
class OfficeHourQueueController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @param $show_all
     * @Route("/{_semester}/{_course}/OfficeHoursQueue")
     * @return Response
     */
     public function showQueue(){
       return Response::WebOnlyResponse(
           new WebResponse(
               'OfficeHoursQueue',
               'showQueue',
               $this->core->getConfig()->getCourse()
           )
       );
     }
}
