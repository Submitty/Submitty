<?php
namespace app\views;

class PDFView extends AbstractView {
    /**
     * @param $gradeable_id
     * @param $user_id
     * @param $filename
     * @param $annotation_jsons
     * @param $is_student
     *
     * @return a twig output of either student view or grader view.
     */
    public function showPDFEmbedded($params){
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $pdf_url = $this->core->buildUrl(array('component' => 'misc', 'page' => 'base64_encode_pdf'));
        return $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFAnnotationEmbedded.twig', [
            'gradeable_id' => $params["gradeable_id"],
            'user_id' => $params["id"],
            'grader_id' => $this->core->getUser()->getId(),
            'filename' => $params["file_name"],
            'annotation_jsons' => json_encode($params["annotation_jsons"]),
            'student_popup' => $params["is_student"],
            'page_num' => $params["page_num"],
            'pdf_url_base' => $pdf_url
        ]);
    }
}
