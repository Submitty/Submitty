<?php
namespace app\views;
use app\libraries\FileUtils;

class PDFView extends AbstractView {
    /**
     * adds to our buffer a twig output of either student view or grader view.
     *
     * @param $gradeable_id
     * @param $user_id
     * @param $filename
     * @param $annotation_jsons
     * @param $is_student
     *
     * @return void
     */
    public function showPDFEmbedded($params){
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $pdf_url = $this->core->buildCourseUrl(['gradeable',  $params["gradeable_id"], 'encode_pdf']);
        $is_student = $params["is_student"];

        $localcss = array();
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'pdf_embedded.css'), 'css');
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf_viewer.css'), 'vendor');

        $localjs = array();

        //This jquery file should not need to be added here as jquery should already be in the header on any page
        // $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('jquery', 'jquery.min.js'), 'vendor');

        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf_viewer.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf.worker.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf-annotate.js', 'pdf-annotate.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'PDFAnnotateEmbedded.js'), 'js');

        // This initializes the toolbar and activates annotation mode
        if (!isset($is_student) || !$is_student) {
            $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'PDFInitToolbar.js'), 'js');
        }

        return $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFAnnotationEmbedded.twig', [
            'gradeable_id' => $params["gradeable_id"],
            'user_id' => $params["id"],
            'grader_id' => $this->core->getUser()->getId(),
            'filename' => $params["file_name"],
            'annotation_jsons' => json_encode($params["annotation_jsons"]),
            'student_popup' => $is_student,
            'page_num' => $params["page_num"],
            'pdf_url_base' => $pdf_url,
            'localcss' => $localcss,
            'localjs' => $localjs,
            'csrfToken' => $this->core->getCsrfToken()
        ]);
    }
}
