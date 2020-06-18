<?php

namespace app\controllers\pdf;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;
use app\models\User;

class PDFController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @param $gradeable_id
     * @param $filename
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/pdf")
     */
    public function showStudentPDF($gradeable_id, $filename, $path) {
        $filename = html_entity_decode($filename);
        $id = $this->core->getUser()->getId();
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable->isTeamAssignment()) {
            $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id)->getId();
        }
        $submitter = $this->core->getQueries()->getSubmitterById($id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeableForSubmitter($gradeable, $submitter);
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $annotation_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $id, $active_version);
        $decoded_path = urldecode($path);
        $json_path = substr($decoded_path, 0, -3) . "json";
        $annotation_jsons = [];
        if (is_dir($annotation_dir)) {
            foreach (scandir($annotation_dir) as $annotation_file) {
                if (explode('_', $annotation_file)[0] === md5($decoded_path)) {
                    $file_content = file_get_contents(FileUtils::joinPaths($annotation_dir, $annotation_file));
                    $annotation_decoded = json_decode($file_content);
                    $grader_id = $annotation_decoded["userId"];
                    $annotation_jsons[$grader_id] = $file_content;
                }
            }
        }
        $params = [
            "gradeable_id" => $gradeable_id,
            "id" => $id,
            "file_name" => $filename,
            "file_path" => urldecode($decoded_path),
            "annotation_jsons" => $annotation_jsons,
            "is_student" => true,
            "page_num" => 1,
            'jquery' => true
        ];

        $this->core->getOutput()->renderOutput(['PDF'], 'showPDFEmbedded', $params);
    }
    
    /**
     * @param $gradeable_id
     * @param $filename
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/download_pdf")
     */
    public function downloadStudentPDF($gradeable_id, $filename, $path) {
        $filename = html_entity_decode($filename);
        $id = $this->core->getUser()->getId();
        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable->isTeamAssignment()) {
            $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id)->getId();
        }
        $submitter = $this->core->getQueries()->getSubmitterById($id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeableForSubmitter($gradeable, $submitter);
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $id, $active_version);
        $annotated_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotated_pdfs', $gradeable_id, $id, $active_version, $filename);
        $annotation_jsons = [];
        $rerender_annotated_pdf = true;
        $latest_timestamp = filemtime(urldecode($path));
        if (is_dir($annotation_path) && count(scandir($annotation_path)) > 2) {
            $first_file = scandir($annotation_path)[2];
            $annotation_path = FileUtils::joinPaths($annotation_path, $first_file);
            if (is_file($annotation_path)) {
                $dir_iter = new \DirectoryIterator(dirname($annotation_path . '/'));
                foreach ($dir_iter as $fileinfo) {
                    if (!$fileinfo->isDot()) {
                        $no_extension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileinfo->getFilename());
                        $pdf_info = explode('_', $no_extension);
                        $pdf_id = implode('_', array_slice($pdf_info, 0, -1));
                        $grader_id = $pdf_info[count($pdf_info) - 1];
                        if ($pdf_id . '.pdf' === $filename) {
                            $annotation_jsons[$grader_id] = file_get_contents($fileinfo->getPathname());
                            if ($latest_timestamp < $fileinfo->getMTime()) {
                                $latest_timestamp = $fileinfo->getMTime();
                            }
                        }
                    }
                }
            }
        }
        
        if (file_exists($annotated_path) && $latest_timestamp <= filemtime($annotated_path)) {
            $rerender_annotated_pdf = false;
        }
        
        $params = [
            "gradeable_id" => $gradeable_id,
            "id" => $id,
            "file_name" => $filename,
            "file_path" => urldecode($path),
            "annotation_jsons" => $annotation_jsons,
            "rerender_annotated_pdf" => $rerender_annotated_pdf,
            "is_student" => true,
            "page_num" => 1,
            'jquery' => true,
        ];
        $pdf_array[] = 'PDF';
        $this->core->getOutput()->renderOutput($pdf_array, 'downloadPDFEmbedded', $params);
    }

    /**
     * @param $gradeable_id
     * @param $target_dir
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/pdf/{target_dir}", methods={"POST"})
     */
    public function savePDFAnnotation($gradeable_id, $target_dir) {
        //Save the annotation layer to a folder.
        $annotation_layer = $_POST['annotation_layer'];
        $annotation_info = $_POST['GENERAL_INFORMATION'];
        $grader_id = $this->core->getUser()->getId();
        $course_path = $this->core->getConfig()->getCoursePath();
        $user_id = $annotation_info['user_id'];

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable === false) {
            return false;
        }
        if ($this->core->getUser()->getGroup() === User::GROUP_STUDENT) {
            if ($gradeable->isPeerGrading()) {
                $user_ids = $this->core->getQueries()->getPeerAssignment($gradeable_id, $grader_id);
        
                if (!in_array($user_id, $user_ids)) {
                    return $this->core->getOutput()->renderJsonFail('You do not have permission to grade this student');
                }
            }
            else {
                return $this->core->getOutput()->renderJsonFail('You do not have permission to grade this student');
            }
        }

        $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $user_id);
        if ($graded_gradeable === false) {
            return false;
        }

        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        $annotation_gradeable_path = FileUtils::joinPaths($course_path, $target_dir, $annotation_info['gradeable_id']);
        if (!is_dir($annotation_gradeable_path) && !FileUtils::createDir($annotation_gradeable_path)) {
            return $this->core->getOutput()->renderJsonFail('Creating annotation gradeable folder failed');
        }
        $annotation_user_path = FileUtils::joinPaths($annotation_gradeable_path, $user_id);
        if (!is_dir($annotation_user_path) && !FileUtils::createDir($annotation_user_path)) {
            return $this->core->getOutput()->renderJsonFail('Creating annotation user folder failed');
        }
        $annotation_version_path = FileUtils::joinPaths($annotation_user_path, $active_version);
        if (!is_dir($annotation_version_path) && !FileUtils::createDir($annotation_version_path)) {
            return $this->core->getOutput()->renderJsonFail('Creating annotation version folder failed.');
        }
        
        $partial_path = substr_replace($annotation_info["file_path"], "", 0, strlen($annotation_version_path) + 1);
        
        $annotation_body = [];
        
        $annotation_body["file_path"] = $partial_path;
        $annotation_body["grader_id"] = $grader_id;
        $annotation_body["annotations"] = $_POST['annotation_layer'];                    
        
        $annotation_json = json_encode($annotation_body);
                        
        file_put_contents(FileUtils::joinPaths($annotation_version_path, md5($annotation_info["file_path"])) . "_" . $grader_id . '.json', $annotation_json);
        $this->core->getOutput()->renderJsonSuccess('Annotation saved successfully!');
        return true;
    }

    /**
     * @param $gradeable_id
     * @Route("/{_semester}/{_course}/gradeable/{gradeable_id}/grading/pdf", methods={"POST"})
     */
    public function showGraderPDFEmbedded($gradeable_id) {
        // This is the embedded pdf annotator that we built.
        // User can be a team
        $id = $_POST['user_id'] ?? null;
        $filename = $_POST['filename'] ?? null;
        $page_num = $_POST['page_num'] ?? null;
        $is_anon = $_POST['is_anon'] ?? false;
        $filename = html_entity_decode($filename);

        if ($is_anon) {
            $id = $this->core->getQueries()->getUserFromAnon($id)[$id];
        }

        $gradeable = $this->tryGetGradeable($gradeable_id);
        if ($gradeable->isTeamAssignment()) {
            $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, null, $id);
        }
        else {
            $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $id);
        }
        
        $grader_id = $this->core->getUser()->getId();
        if ($this->core->getUser()->getGroup() === User::GROUP_STUDENT) {
            if ($gradeable->isPeerGrading()) {
                $user_ids = $this->core->getQueries()->getPeerAssignment($gradeable_id, $grader_id);
                if (!in_array($id, $user_ids)) {
                    return $this->core->getOutput()->renderJsonFail('You do not have permission to grade this student');
                }
            }
            else {
                return $this->core->getOutput()->renderJsonFail('You do not have permission to grade this student');
            }
        }
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $annotation_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $id, $active_version);
        $annotation_jsons = [];
        $file_path = $_POST['file_path'];
        if (is_dir($annotation_dir)) {
            foreach (scandir($annotation_dir) as $annotation_file) {
                if (explode('_', $annotation_file)[0] === md5($file_path)) {
                    $file_contents = file_get_contents(FileUtils::joinPaths($annotation_dir, $annotation_file);
                    $annotation_decoded = json_decode($file_contents, true);
                    if ($annotation_decoded != null) {
                        $grader_id = $annotation_decoded["annotations"][0]["userId"];
                        $annotation_jsons[$grader_id] = $file_contents;
                    }
                }
            }
        }
        $params = [
            "gradeable_id" => $gradeable_id,
            "id" => $id,
            "file_name" => $filename,
            "file_path" => $_POST['file_path'],
            "annotation_jsons" => $annotation_jsons,
            "is_student" => false,
            "page_num" => $page_num
        ];
        $this->core->getOutput()->renderOutput(['PDF'], 'showPDFEmbedded', $params);
    }

    /**
     * NOT IN USE
     */
    private function showGraderPDFFullpage() {
        //This shows the pdf-annotate.js library's default pdf annotator. It might be useful in the future to have
        //a full-sized annotator, so keeping this in for now.
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->renderOutput(['grading', 'PDFAnnotation'], 'showAnnotationPage');
    }
}
