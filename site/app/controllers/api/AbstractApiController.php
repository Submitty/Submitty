<?php


namespace app\controllers\api;

use app\libraries\Core;
use Symfony\Component\HttpFoundation\Request;


abstract class AbstractApiController {

    /** @var Core  */
    protected $core;

    /** @var Request */
    protected $request;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->request = Request::createFromGlobals();
    }

}