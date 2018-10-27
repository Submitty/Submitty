<?php

namespace app\controllers\pdf;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\models\PDFGenerator;
use app\libraries\FileUtils;

class PDFController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'student_pdf_view':
                $this->showStudentPDF();
                break;
            case 'grader_fullpage':
                //Currently not in use
                $this->showGraderPDFFullpage();
                break;
            case 'grader_embedded':
                $this->showGraderPDFEmbedded();
                break;
            case 'download_annotated_pdf':
                $this->downloadAnnotatedPdf();
                return;
            case 'save_pdf_annotation':
                $this->savePDFAnnotation();
                break;
        }
    }

    private function downloadAnnotatedPdf(){
        $PDFGenerator = new PDFGenerator($this->core);
        return "test";
    }

    private function showStudentPDF(){
        $gradeable_id = $_GET['gradeable_id'] ?? NULL;
        $user_id = $this->core->getUser()->getId();
        $filename = $_GET['file_name'] ?? NULL;
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $active_version = $this->core->getQueries()->getGradeable($gradeable_id, $user_id)->getActiveVersion();
        if($gradeable->isTeamAssignment()){
            $user_id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $user_id)->getId();
        }
        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $user_id, $active_version);
        $annotation_jsons = [];
        if(is_dir($annotation_path)){
            $first_file = scandir($annotation_path)[2];
            $annotation_path = FileUtils::joinPaths($annotation_path, $first_file);
            if(is_file($annotation_path)) {
                $dir_iter = new \DirectoryIterator(dirname($annotation_path . '/'));
                foreach ($dir_iter as $fileinfo) {
                    if (!$fileinfo->isDot()) {
                        $grader_id = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileinfo->getFilename());
                        $grader_id = explode('_', $grader_id)[1];
                        $annotation_jsons[$grader_id] = file_get_contents($fileinfo->getPathname());
                    }
                }
            }
        }
        return $this->core->getOutput()->renderOutput(array('PDF'), 'showPDFEmbedded', $gradeable_id, $user_id, $filename, $annotation_jsons, true);
    }

    private function savePDFAnnotation(){
        //Save the annotation layer to a folder.
        $annotation_layer = $_POST['annotation_layer'];
        $annotation_info = $_POST['GENERAL_INFORMATION'];
        $grader_id = $this->core->getUser()->getId();
        $course_path = $this->core->getConfig()->getCoursePath();
        $gradeable = $this->core->getQueries()->getGradeable($annotation_info['gradeable_id']);
        $user_id = $annotation_info['user_id'];
        if($gradeable->isTeamAssignment()){
            $first_member = $this->core->getQueries()->getTeamById($user_id)->getMemberUserIds()[0];
            $active_version = $this->core->getQueries()->getGradeable($annotation_info['gradeable_id'], $first_member)->getActiveVersion();
        } else {
            $active_version = $this->core->getQueries()->getGradeable($annotation_info['gradeable_id'], $user_id)->getActiveVersion();
        }
        $annotation_gradeable_path = FileUtils::joinPaths($course_path, 'annotations', $annotation_info['gradeable_id']);
        if(!FileUtils::createDir($annotation_gradeable_path) && !is_dir($annotation_gradeable_path)){
            $this->core->addErrorMessage("Creating annotation gradeable folder failed");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            return array('error' => true, 'message' => 'Creating annotation gradeable folder failed');
        }
        $annotation_user_path = FileUtils::joinPaths($annotation_gradeable_path, $user_id);
        if(!FileUtils::createDir($annotation_user_path) && !is_dir($annotation_user_path)){
            $this->core->addErrorMessage("Creating annotation user folder failed");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            return array('error' => true, 'message' => 'Creating annotation user folder failed');
        }
        $annotation_version_path = FileUtils::joinPaths($annotation_user_path, $active_version);
        if(!FileUtils::createDir($annotation_version_path) && !is_dir($annotation_version_path)){
            $this->core->addErrorMessage("Creating annotation version folder failed");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            return array('error' => true, 'message' => 'Creating annotation version folder failed');
        }
        $new_file_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $annotation_info['file_name']) . "_" .$grader_id .'.json';
        file_put_contents(FileUtils::joinPaths($annotation_version_path, $new_file_name), $annotation_layer);
        return true;
    }

    private function showGraderPDFEmbedded(){
        //This is the embedded pdf annotator that we built.
        $gradeable_id = $_POST['gradeable_id'] ?? NULL;
        $user_id = $_POST['user_id'] ?? NULL;
        $filename = $_POST['filename'] ?? NULL;
        $active_version = $this->core->getQueries()->getGradeable($gradeable_id, $user_id)->getActiveVersion();
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        $id = $user_id;
        if($gradeable->isTeamAssignment()){
            $id = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $user_id)->getId();
        }
        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $id, $active_version);
        $annotation_jsons = [];
        //Dir iterator needs the first file.
        if(is_dir($annotation_path) && count(scandir($annotation_path)) > 2){
            $first_file = scandir($annotation_path)[2];
            $annotation_path = FileUtils::joinPaths($annotation_path, $first_file);
            if(is_file($annotation_path)) {
                $dir_iter = new \DirectoryIterator(dirname($annotation_path . '/'));
                foreach ($dir_iter as $fileinfo) {
                    if (!$fileinfo->isDot()) {
                        $no_extension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $fileinfo->getFilename());
                        $pdf_info = explode('_', $no_extension);
                        $pdf_id = $pdf_info[0];
                        $grader_id = $pdf_info[1];
                        if($pdf_id.'.pdf' === $filename){
                            $annotation_jsons[$grader_id] = file_get_contents($fileinfo->getPathname());
                        }
                    }
                }
            }
        }
        $this->core->getOutput()->renderOutput(array('PDF'), 'showPDFEmbedded', $gradeable_id, $id, $filename, $annotation_jsons, false);
    }

    private function showGraderPDFFullpage(){
        //This shows the pdf-annotate.js library's default pdf annotator. It might be useful in the future to have
        //a full-sized annotator, so keeping this in for now.
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->renderOutput(array('grading', 'PDFAnnotation'), 'showAnnotationPage');
    }
}
