<?php

namespace app\controllers\student;


use app\controllers\AbstractController;
use app\models\PDFGenerator;
use app\libraries\FileUtils;

class PDFController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view_annotated_pdf':
                $this->showAnnotatedPdf();
                break;
            case 'download_annotated_pdf':
                $this->downloadAnnotatedPdf();
                return;
        }
    }

    private function downloadAnnotatedPdf(){
        $PDFGenerator = new PDFGenerator($this->core);
        return "test";
    }

    private function showAnnotatedPdf(){
        $gradeable_id = $_GET['gradeable_id'] ?? NULL;
        $user_id = $this->core->getUser()->getId();
        $filename = $_GET['file_name'] ?? NULL;
        $active_version = $this->core->getQueries()->getGradeable($gradeable_id, $user_id)->getActiveVersion();
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
}
