<?php


namespace app\controllers\api;


use app\controllers\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class TokenController extends AbstractController {

    /** @var Request */
    private $request;

    public function run() {
        $this->request = Request::createFromGlobals();
        switch ($this->request->getMethod()) {
            case 'GET':
                $this->get();
                break;
        }
    }

    private function get() {
        $this->core->getOutput()->renderJsonSuccess("test");
    }
}