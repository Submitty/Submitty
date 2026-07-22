<?php

namespace app\controllers\image_submission_files;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PDFController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    // also update in ImageController.php
    /**
     * If $real is false, given the real file path and the gradeable id,
     * returns the anonymous file path to the submitted file, which contains
     * the student's anonymous id. If $real is true, given the anonymous path
     * and the student's id, returns the real file path to the submitted file.
     * @param string $file_path the path to the submission file
     * @param string $id
     * - if $real is false, the gradeable id of the submitted file we're getting the path of
     * - if $real is true, the user id of the user whose submitted file we are finding the path of
     * @param bool $real whether the output string should be the real or anonymous file path
     * @return string
     */
    public function getSubmittedFilePath(string $file_path, string $id, bool $real): string {
        $path = "";
        $file_path_parts = explode("/", $file_path);

        if ($real && str_contains($file_path, "..")) {
            return "INVALID FILE PATH";
        }

        for ($index = 1; $index < count($file_path_parts); $index++) {
            if ($index === 9) {
                if ($real) {
                    $path .= "/" . $id;
                }
                else {
                    $user_id = $file_path_parts[$index];
                    $anon_ids = $this->core->getQueries()->getSubmitterIdFromAnonId($user_id, $id);
                    $path .= "/" . (empty($anon_ids) ? $user_id : $anon_ids[$user_id]);
                }
            }
            else {
                $path = $path . "/" . $file_path_parts[$index];
            }
        }
        return $path;
    }

    // #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/pdf")]
    // public function showStudentPDF(string $gradeable_id, string $filename, string $path): void {
    //     $filename = html_entity_decode($filename);
    //     $id = $this->core->getUser()->getId();
    //     $gradeable = $this->tryGetGradeable($gradeable_id);
    //     if ($gradeable->isTeamAssignment()) {
    //         $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id)->getId();
    //     }

    //     $this->core->getOutput()->renderOutput(['PDF'], 'showPDFEmbedded', $gradeable_id, $id, $filename, $path, 1, true);
    // }

    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/pdf", methods: ["POST"])]
    public function showGraderPDFEmbedded(string $gradeable_id) {
        // User can be a team
        $id = $_POST['user_id'] ?? null;
        $filename = $_POST['filename'] ?? null;
        $page_num = $_POST['page_num'] ?? null;
        // Explicit boolean parsing for is_anon
        $is_anon = filter_var($_POST['is_anon'] ?? false, FILTER_VALIDATE_BOOL);
        $filename = html_entity_decode($filename);
        $file_path = urldecode($_POST['file_path']);
        $real_path = $is_anon ? "" : $file_path;

        if ($is_anon) {
            $id = $this->core->getQueries()->getSubmitterIdFromAnonId($id, $gradeable_id);
            $real_path = $this->getSubmittedFilePath($file_path, $id, true);
            if (!file_exists($real_path)) {
                return JsonResponse::getErrorResponse('The PDF file could not be found');
            }
        }

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return JsonResponse::getErrorResponse('Could not get gradeable');
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $id);
        if ($graded_gradeable === false) {
            return JsonResponse::getErrorResponse('Could not get graded gradeable');
        }

        if (!$this->core->getAccess()->canI("grading.electronic.grade", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            return JsonResponse::getErrorResponse('You do not have permission to grade this student');
        }

        $this->core->getOutput()->renderOutput(['PDF'], 'showPDFEmbedded', $gradeable_id, $id, $filename, $file_path, $page_num, false);
    }
}
