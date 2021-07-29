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
        ?string $anon_path,
        ?string $download_path,
        array $annotation_jsons,
        bool $is_student,
        ?int $page_num,
        bool $jquery = false
    ): void {
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $pdf_url = $this->core->buildCourseUrl(['gradeable',  $gradeable_id, 'encode_pdf']);

        $localcss = [];
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'pdf_embedded.css'), 'css');
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf_viewer.css'), 'vendor');

        $localjs = [];

        //This jquery file should not need to be added here as jquery should already be in the header on any page
        if ($jquery) {
            $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('jquery', 'jquery.min.js'), 'vendor');
        }

        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf_viewer.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf.worker.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf-annotate.js', 'pdf-annotate.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'PDFAnnotateEmbedded.js'), 'js');
        // This initializes the toolbar and activates annotation mode
        if (!$is_student) {
            $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'PDFInitToolbar.js'), 'js');
        }

        // var_dump($file_name);
        // var_dump($file_path);
        // var_dump($anon_path);
        // var_dump($download_path);

        $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFAnnotationEmbedded.twig', [
            'gradeable_id' => $gradeable_id,
            'user_id' => $user_id,
            'grader_id' => $this->core->getUser()->getId(),
            'filename' => $file_name,
            'file_path' => $file_path,
            'annotation_jsons' => json_encode($annotation_jsons),
            'student_popup' => $is_student,
            'can_download' => !$is_student,
            'page_num' => $page_num,
            'pdf_url_base' => $pdf_url,
            'localcss' => $localcss,
            'localjs' => $localjs,
            'csrfToken' => $this->core->getCsrfToken(),
            'student_pdf_download_url' => $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'download_pdf']),
            'anon_path' => $anon_path,
            'download_path' => $download_path
        ]);
    }

    /**
     * adds to our buffer a twig output of either student view or grader view.
     */
    public function downloadPDFEmbedded(
        string $gradeable_id,
        string $user_id,
        string $file_name,
        string $file_path,
        array $annotation_jsons,
        bool $rerender_annotated_pdf,
        bool $is_student,
        int $page_num,
        bool $jquery
    ): void {
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);
        $pdf_url = $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'encode_pdf']);

        $localcss = [];
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'pdf_embedded.css'), 'css');
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf_viewer.css'), 'vendor');

        $localjs = [];

        //This jquery file should not need to be added here as jquery should already be in the header on any page
        if ($jquery) {
            $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('jquery', 'jquery.min.js'), 'vendor');
        }

        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf_viewer.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdfjs', 'pdf.worker.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf-annotate.js', 'pdf-annotate.min.js'), 'vendor');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'PDFAnnotateEmbedded.js'), 'js');
        $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('jspdf', 'jspdf.min.js'), 'vendor');
        // This initializes the toolbar and activates annotation mode
        if (!$is_student) {
            $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'PDFInitToolbar.js'), 'js');
        }

        $this->core->getOutput()->renderTwigOutput('grading/electronic/PDFAnnotationEmbedded.twig', [
            'gradeable_id' => $gradeable_id,
            'user_id' => $user_id,
            'grader_id' => $this->core->getUser()->getId(),
            'filename' => $file_name,
            'file_path' => $file_path,
            'annotation_jsons' => json_encode($annotation_jsons),
            'rerender_annotated_pdf' => $rerender_annotated_pdf,
            'student_download' => $is_student,
            'page_num' => $page_num,
            'pdf_url_base' => $pdf_url,
            'localcss' => $localcss,
            'localjs' => $localjs,
            'csrfToken' => $this->core->getCsrfToken()
        ]);
    }
}
