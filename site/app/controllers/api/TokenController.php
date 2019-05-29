<?php


namespace app\controllers\api;

use Symfony\Component\Routing\Annotation\Route;


class TokenController extends AbstractApiController {

    /**
     *
     * @Route("/token", methods={"GET"})
     */
    public function getToken() {
        $this->core->getOutput()->renderJsonSuccess("test");
    }
}