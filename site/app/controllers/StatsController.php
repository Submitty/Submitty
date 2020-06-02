<?php
namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\OfficeHoursQueueModel;
use app\libraries\routers\AccessControl;

/**
 * Class statscontroller
 *
 */

class statscontroller extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/{_semester}/{_course}/stats", methods={"GET"})
     */

    public function showall($something = null) {
        $this->core->getOutput()->renderOutput('Stats','showStats');
    }
}
