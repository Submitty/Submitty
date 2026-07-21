<?php

namespace app\views;

use app\libraries\FileUtils;

class PDFView extends AbstractView {
    /**
     * adds to our buffer a twig output of either student view or grader view.
     */
    public function showPDFEmbedded(
        string $gradeable_id,
        string $user_id,
        ?string $file_name,
        ?string $file_path,
        ?int $page_num,
        bool $jquery = false,
    ): void {
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $pdf_url = $this->core->buildCourseUrl(['gradeable',  $gradeable_id, 'encode_pdf']);

        $localcss = [];
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'pdf_embedded.css'), 'css');
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf_viewer.css'), 'vendor');

        $localjs = [];
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'PDFEmbedded.js'), 'js');

        //This jquery file should not need to be added here as jquery should already be in the header on any page
        if ($jquery) {
            $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('jquery', 'jquery.min.js'), 'vendor');
        }

        $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFEmbedded.twig', [
            'gradeable_id' => $gradeable_id,
            'user_id' => $user_id,
            'grader_id' => $this->core->getUser()->getId(),
            'filename' => $file_name,
            'file_path' => $file_path,
            'page_num' => $page_num,
            'pdf_url_base' => $pdf_url,
            'localcss' => $localcss,
            'localjs' => $localjs,
        ]);
    }
}
