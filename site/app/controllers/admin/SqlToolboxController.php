<?php

declare(strict_types=1);

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\entities\db\Table;
use app\exceptions\DatabaseException;
use app\libraries\database\QueryIdentifier;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\views\admin\SqlToolboxView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UsersController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */

class SqlToolboxController extends AbstractController {
    #[Route("/courses/{_semester}/{_course}/sql_toolbox", methods: ["GET"])]
    public function showToolbox(): WebResponse {
        return new WebResponse(
            SqlToolboxView::class,
            'showToolbox',
            $this->core->getCourseEntityManager()->getRepository(Table::class)->findBy(
                ['schema' => 'public'],
                ['name' => 'ASC']
            )
        );
    }

    #[Route("/courses/{_semester}/{_course}/sql_toolbox", methods: ["POST"])]
    public function runQuery(): JsonResponse {
        $query = trim($_POST['sql']);

        if (QueryIdentifier::identify($query) !== QueryIdentifier::SELECT) {
            return JsonResponse::getFailResponse('Invalid query, can only run SELECT queries.');
        }

        $semiColonCount = substr_count($query, ';');
        if ($semiColonCount > 1 || ($semiColonCount === 1 && substr($query, -1) !== ';')) {
            return JsonResponse::getFailResponse('Detected multiple queries, not running.');
        }

        try {
            $this->core->getCourseDB()->beginTransaction();
            $this->core->getCourseDB()->query($query);
            return JsonResponse::getSuccessResponse($this->core->getCourseDB()->rows());
        }
        catch (DatabaseException $exc) {
            return JsonResponse::getFailResponse("Error running query: " . $exc->getMessage());
        }
        finally {
            $this->core->getCourseDB()->rollback();
        }
    }

    #[Route("/courses/{_semester}/{_course}/sql_toolbox/queries", methods: ["GET"])]
    public function getSavedQuery(): JsonResponse {
        $user_id = $this->core->getUser()->getId();
        return JsonResponse::getSuccessResponse($this->core->getQueries()->getInstructorQueries($user_id));
    }

    #[Route("/courses/{_semester}/{_course}/sql_toolbox/queries", methods: ["POST"])]
    public function saveQuery(): JsonResponse {
        $user_id = $this->core->getUser()->getId();
        $query = $_POST['query'];
        $query_name = $_POST['query_name'];

        if ($query_name > 255) {
            return JsonResponse::getFailResponse("Query name must be less than 255 characters long");
        }

        $queries = $this->core->getQueries()->getInstructorQueries($user_id);
        foreach ($queries as $existing_query) {
            if ($existing_query['query_name'] === $query_name) {
                return JsonResponse::getFailResponse("Query name already exists, please choose a different name.");
            }
        }

        $this->core->getQueries()->saveInstructorQueries($user_id, $query_name, $query);
        return JsonResponse::getSuccessResponse("Successfully saved the query");
    }
}
