<?php

namespace app\controllers\student;


use app\controllers\AbstractController;
use app\libraries\FileUtils;

class PDFController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view_annotated_pdf':
                return $this->showAnnotatedPdf();
                break;
        }
    }

    private function showAnnotatedPdf(){
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $gradeable_id = $_GET['gradeable_id'] ?? NULL;
        $user_id = $this->core->getUser()->getId();
        $file_name = $_GET['file_name'] ?? NULL;
        $annotation_file_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name). '_annotation.json';
        $active_version = $this->core->getQueries()->getGradeable($gradeable_id, $user_id)->getActiveVersion();
        $annotation_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'annotations', $gradeable_id, $user_id, $active_version, $annotation_file_name);
        $annotation_json = is_file($annotation_path) ? file_get_contents($annotation_path) : "";
        return $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFAnnotationEmbedded.twig', [
            'gradeable_id' => $gradeable_id,
            'user_id' => $user_id,
            'filename' => $file_name,
            'annotation_json' => $annotation_json,
            'student_popup' => true
        ]);
    }
}
