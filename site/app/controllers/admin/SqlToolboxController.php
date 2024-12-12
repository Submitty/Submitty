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

    #[Route("/courses/{_semester}/{_course}/saveQuery", methods: ["POST"])]
    public function saveQuery(): string {
        $query = trim($_POST['sql']);
        $name = trim($_POST['name']);
        $user = $this->core->getUser()->getId();

        if (QueryIdentifier::identify($query) !== QueryIdentifier::SELECT) {
            return 'Invalid query, can only save SELECT queries.';
        }

        $semiColonCount = substr_count($query, ';');
        if ($semiColonCount > 1 || ($semiColonCount === 1 && substr($query, -1) !== ';')) {
            return 'Detected multiple queries, not saving.';
        }

        /*
        print"YEAH THIS IS CALLED. Query: " .$query;
        if($user == NULL){
            print "USER IS NULL";
        }
        ELSE{
            print"USER: " .$user;
            print"QUERY NAME: " .$name;
        }*/

        try {
            
            $this->core->getQueries()->addSQLSavedQuery($user, $name, $query);
            return "Saving query " .$query;
        }
        catch(DatabaseException $exc) {
            print "ERROR " .$exc;
        }
        return "a";
    }

    #[Route("/courses/{_semester}/{_course}/removeQuery", methods: ["POST"])]
    public function removeQuery(): string {
        $name = trim($_POST['name']);
        $user = $this->core->getUser()->getId();

        if($user == NULL){
            print "USER IS NULL";
        }
        ELSE{
            print"USER: " .$user;
            print"QUERY NAME: " .$name;
        }

        try {
            
            $this->core->getQueries()->removeSQLSavedQuery($user, $name);
            return "Removing query named" .$name;
        }
        catch(DatabaseException $exc) {
            print "ERROR " .$exc;
        }
        return "a";
    }

    #[Route("/courses/{_semester}/{_course}/getQueries", methods: ["GET"])]
    public function getQueries(): JsonResponse {
        $user = $this->core->getUser()->getId();

        if($user == NULL){
            return JsonResponse::getFailResponse('user is null');
        }

        try {
            $queries = $this->core->getQueries()->getSQLSavedQueries($user);
            return JsonResponse::getSuccessResponse($queries);
        }
        catch(DatabaseException $exc) {
            return JsonResponse::getFailResponse('Detected errorz');
        }
    }
}
