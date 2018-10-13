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
    public function showPDFEmbedded($gradeable_id, $user_id, $filename, $annotation_jsons, $is_student){
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $pdf_url = $this->core->buildUrl(array('component' => 'misc', 'page' => 'base64_encode_pdf'));
        return $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFAnnotationEmbedded.twig', [
            'gradeable_id' => $gradeable_id,
            'user_id' => $user_id,
            'grader_id' => $this->core->getUser()->getId(),
            'filename' => $filename,
            'annotation_jsons' => json_encode($annotation_jsons),
            'student_popup' => $is_student,
            'pdf_url_base' => $pdf_url
        ]);
    }
}
