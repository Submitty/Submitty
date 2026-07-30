<?php

namespace app\controllers\api;

use app\controllers\AbstractController;
use app\libraries\response\RedirectResponse;
use app\libraries\Core;
use app\entities\Term;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TermsController
 *
 * Controller to deal with the submitty term API.
 */
class TermsController extends AbstractController {
    /**
     * @return MultiResponse
     */
    #[Route("/term/new", methods: ["POST"])]
    #[Route("/api/terms", methods: ["POST"])]
    public function addNewTerm() {
        if (!$this->core->getUser()->isSuperUser()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        if (
            !isset($_POST['term_id'])
            || !isset($_POST['term_name'])
            || !isset($_POST['start_date'])
            || !isset($_POST['end_date'])
        ) {
            $error = "Term ID, term name, start date, or end date not set.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }

        $term_id = $_POST['term_id'];
        $term_name = $_POST['term_name'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $em = $this->core->getSubmittyEntityManager();
        $term = $em->find(Term::class, $term_id);

        if ($term !== null) {
            $error = "Term with that ID already exists.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }
        elseif ($end_date < $start_date) {
            $error = "End date should be after Start date.";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }

        $start_date_obj = new \DateTime($start_date);
        $end_date_obj = new \DateTime($end_date);
        $term_length_days = $start_date_obj->diff($end_date_obj)->days;

        if ($term_length_days > 360) {
            $error = "Term length cannot exceed 360 days (this term spans $term_length_days days).";
            $this->core->addErrorMessage($error);
            return new MultiResponse(
                JsonResponse::getFailResponse($error),
                null,
                new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
            );
        }

        $term = new Term(
            $term_id,
            $term_name,
            new \DateTime($start_date),
            new \DateTime($end_date),
        );
        $em->persist($term);
        $em->flush();
        $this->core->addSuccessMessage("Term added successfully.");
        return new MultiResponse(
            JsonResponse::getSuccessResponse([
                "term_id" => $term_id,
                "term_name" => $term_name,
                "start_date" => $start_date,
                "end_date" => $end_date
            ]),
            null,
            new RedirectResponse($this->core->buildUrl(['home', 'courses', 'new']))
        );
    }
}