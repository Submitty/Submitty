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

    private const MAX_ROWS = 1000;


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
            $limitedQuery = rtrim($query, ';');
            $countQuery = "SELECT COUNT(*) as total FROM ({$limitedQuery}) as count_query";
            $this->core->getCourseDB()->query($countQuery);
            $totalRows = $this->core->getCourseDB()->rows()[0]['total'];
            $limitedQuery = "SELECT * FROM ({$limitedQuery}) as results LIMIT " . self::MAX_ROWS;
            $this->core->getCourseDB()->query($limitedQuery);
            $rows = $this->core->getCourseDB()->rows();
        
            return JsonResponse::getSuccessResponse([
                'data' => $rows,
                'message' => $totalRows > self::MAX_ROWS 
                    ? "Output was truncated. Showing " . count($rows) . " of {$totalRows} total rows." 
                    : "Showing " . count($rows) . " of {$totalRows} total rows."
            ]);
        }
        catch (DatabaseException $exc) {
            return JsonResponse::getFailResponse("Error running query: " . $exc->getMessage());
        }
        finally {
            $this->core->getCourseDB()->rollback();
        }
    }
}
