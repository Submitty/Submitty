<?php

namespace app\controllers\pdf;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\User;

class PDFController extends AbstractController {
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/pdf")
     */
    public function showStudentPDF(string $gradeable_id, string $filename, string $path, string $anon_path): void {
        $filename = html_entity_decode($filename);
        $anon_path = urldecode($anon_path);
        $id = $this->core->getUser()->getId();
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable->isTeamAssignment()) {
            $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id)->getId();
        }
        $submitter = $this->core->getQueries()->getSubmitterById($id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeableForSubmitter($gradeable, $submitter);
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $id, $active_version);
        $annotation_jsons = [];
        $md5_path = md5($anon_path);
        if (is_dir($annotation_path)) {
            $dir_iter = new \FilesystemIterator($annotation_path);
            foreach ($dir_iter as $file_info) {
                if (explode('_', $file_info->getFilename())[0] === $md5_path) {
                    $file_contents = file_get_contents($file_info->getPathname());
                    $annotation_decoded = json_decode($file_contents, true);
                    if ($annotation_decoded !== null) {
                        $grader_id = $annotation_decoded["grader_id"];
                        $annotation_jsons[$grader_id] = json_encode($annotation_decoded['annotations']);
                    }
                }
            }
        }

        $this->core->getOutput()->renderOutput(['PDF'], 'showPDFEmbedded', $gradeable_id, $id, $filename, $path, null, null, $annotation_jsons, true, 1, true);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/download_pdf")
     */
    public function downloadStudentPDF(string $gradeable_id, string $filename, string $path, string $anon_path, string $student_id = ""): void {
        $filename = html_entity_decode($filename);
        $anon_path = urldecode($anon_path);
        $id = $this->core->getUser()->getId();

        if ($student_id !== "") {
            if ($this->core->getUser()->getGroup() === User::GROUP_STUDENT && $student_id !== $id) {
                $this->core->getOutput()->renderJsonFail('You do not have permission to access this file');
                return;
            }
            $id = $student_id;
        }

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable->isTeamAssignment()) {
            $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id)->getId();
        }
        $submitter = $this->core->getQueries()->getSubmitterById($id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeableForSubmitter($gradeable, $submitter);
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $id, $active_version);
        $annotation_jsons = [];

        $latest_timestamp = filemtime($path);
        $md5_path = md5($anon_path);
        if (is_dir($annotation_path)) {
            $dir_iter = new \FilesystemIterator($annotation_path);
            foreach ($dir_iter as $file_info) {
                if (explode('_', $file_info->getFilename())[0] === $md5_path) {
                    $file_contents = file_get_contents($file_info->getPathname());
                    $annotation_decoded = json_decode($file_contents, true);
                    if ($annotation_decoded !== null) {
                        $grader_id = $annotation_decoded["grader_id"];
                        $annotation_jsons[$grader_id] = json_encode($annotation_decoded['annotations']);
                        if ($latest_timestamp < $file_info->getMTime()) {
                            $latest_timestamp = $file_info->getMTime();
                        }
                    }
                }
            }
        }

        $rerender_annotated_pdf = (file_exists($annotation_path) && $latest_timestamp <= filemtime($annotation_path)) !== true;

        $pdf_array[] = 'PDF';
        $this->core->getOutput()->renderOutput($pdf_array, 'downloadPDFEmbedded', $gradeable_id, $id, $filename, $path, $annotation_jsons, $rerender_annotated_pdf, true, 1, true);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/pdf/{target_dir}", methods={"POST"})
     */
    public function savePDFAnnotation(string $gradeable_id, string $target_dir): JsonResponse {
        //Save the annotation layer to a folder.
        $annotation_info = $_POST['GENERAL_INFORMATION'];
        $grader_id = $this->core->getUser()->getId();
        $course_path = $this->core->getConfig()->getCoursePath();
        $user_id = $annotation_info['user_id'];

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return JsonResponse::getErrorResponse('Could not get gradeable');
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id);
        if ($graded_gradeable === false) {
            return JsonResponse::getErrorResponse('Could not get graded gradeable');
        }

        // Leaving the target_dir parameter gives us flexibility in the future, but it is currently only allowed to
        // ever have one value ("annotations").  There is commented-out code in PDFInitToolbar.js which makes use of
        // different directories, which may want to be re-enabled in the future.
        if ($target_dir !== 'annotations') {
            return JsonResponse::getErrorResponse("Invalid target directory $target_dir");
        }

        if (!$this->core->getAccess()->canI("grading.electronic.grade", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            return JsonResponse::getErrorResponse('You do not have permission to grade this student');
        }

        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        $annotation_gradeable_path = FileUtils::joinPaths($course_path, $target_dir, $gradeable_id);
        if (!is_dir($annotation_gradeable_path) && !FileUtils::createDir($annotation_gradeable_path)) {
            return JsonResponse::getErrorResponse('Creating annotation gradeable folder failed');
        }
        $annotation_user_path = FileUtils::joinPaths($annotation_gradeable_path, $user_id);
        if (!is_dir($annotation_user_path) && !FileUtils::createDir($annotation_user_path)) {
            return JsonResponse::getErrorResponse('Creating annotation user folder failed');
        }
        $annotation_version_path = FileUtils::joinPaths($annotation_user_path, $active_version);
        if (!is_dir($annotation_version_path) && !FileUtils::createDir($annotation_version_path)) {
            return JsonResponse::getErrorResponse('Creating annotation version folder failed');
        }

        $annotation_body = [
            'file_path' => $annotation_info['file_path'],
            'grader_id' => $grader_id,
            'annotations' => isset($_POST['annotation_layer']) ? json_decode($_POST['annotation_layer'], true) : []
        ];

        $annotation_json = json_encode($annotation_body);
        file_put_contents(FileUtils::joinPaths($annotation_version_path, md5($annotation_info["file_path"])) . "_" . $grader_id . '.json', $annotation_json);
        return JsonResponse::getSuccessResponse('Annotation saved successfully!');
    }

    /**
     * @Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/grading/pdf", methods={"POST"})
     */
    public function showGraderPDFEmbedded(string $gradeable_id) {
        // This is the embedded pdf annotator that we built.
        // User can be a team
        $id = $_POST['user_id'] ?? null;
        $filename = $_POST['filename'] ?? null;
        $page_num = $_POST['page_num'] ?? null;
        $is_anon = $_POST['is_anon'] ?? false;
        $filename = html_entity_decode($filename);
        $file_path = urldecode($_POST['file_path']);
        $real_path = $is_anon ? "" : urldecode($_POST['file_path']);

        if ($is_anon) {
            $id = $this->core->getQueries()->getSubmitterIdFromAnonId($id, $gradeable_id);

            $file_path_parts = explode("/", $file_path);
            for ($index = 1; $index < count($file_path_parts); $index++) {
                if ($index === 9) {
                    $real_path .= "/" . $id;
                }
                else {
                    $real_path = $real_path . "/" . $file_path_parts[$index];
                }
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

        // We've already verified that we can grade this assignment.  We just check to see if this a peer grader
        // to determine if we should show a button to download the PDF.
        $is_peer_grader = $this->core->getUser()->getGroup() === User::GROUP_STUDENT && $gradeable->hasPeerComponent();

        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $annotation_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $id, $active_version);
        $annotation_jsons = [];
        $file_path_md5 = md5($file_path);
        if (is_dir($annotation_dir)) {
            $dir_iter = new \FilesystemIterator($annotation_dir);
            foreach ($dir_iter as $annotation_file) {
                if (explode('_', $annotation_file->getFilename())[0] === $file_path_md5) {
                    $file_contents = file_get_contents($annotation_file->getPathname());
                    $annotation_decoded = json_decode($file_contents, true);
                    if ($annotation_decoded !== null) {
                        $grader_id = $annotation_decoded["grader_id"];
                        $annotation_jsons[$grader_id] = json_encode($annotation_decoded['annotations']);
                    }
                }
            }
        }

        $this->core->getOutput()->renderOutput(['PDF'], 'showPDFEmbedded', $gradeable_id, $id, $filename, $real_path, $file_path, $file_path, $annotation_jsons, false, $page_num, false, $is_peer_grader);
    }
}
