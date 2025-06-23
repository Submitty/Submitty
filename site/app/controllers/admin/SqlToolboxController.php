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
        $sql_tables = $this->core->getCourseEntityManager()->getRepository(Table::class)->findBy(
            ['schema' => 'public'],
            ['name' => 'ASC']
        );

        // need to map to json-encodeable format
        $sql_structure_data = array_map(function ($sql_table) {
            return [
                'name' => $sql_table->getName(),
                'columns' => array_map(function ($column) {
                    return [
                        'name' => $column->getName(),
                        'type' => $column->getType(),
                    ];
                }, $sql_table->getColumns()->toArray()),
            ];
        }, $sql_tables);

        return new WebResponse(
            SqlToolboxView::class,
            'showToolbox',
            $sql_structure_data
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
}
