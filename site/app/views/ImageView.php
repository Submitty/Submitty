<?php

namespace app\views;

use app\libraries\FileUtils;

class ImageView extends AbstractView {
    /**
     * adds to our buffer a twig output of image annotation interface.
     */
    public function showImageEmbedded(
        string $gradeable_id,
        string $user_id,
        ?string $file_name,
        ?string $file_path,
        ?string $anon_path,
        ?string $download_path,
        array $annotation_jsons,
        bool $is_student,
        bool $jquery = false,
        bool $is_peer_grader = false
    ): void {
        $this->core->getOutput()->useFooter(false);
        $this->core->getOutput()->useHeader(false);

        $display_file_url = $this->core->buildCourseUrl(['display_file']);

        $localcss = [];
        $localcss[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('image', 'image_annotation.css'), 'css');

        $localjs = [];

        if ($jquery) {
            $localjs[] = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('jquery', 'jquery.min.js'), 'vendor');
        }

        try {
            $this->core->getOutput()->renderTwigOutput('grading/electronic/ImageAnnotationEmbedded.twig', [
                'gradeable_id' => $gradeable_id,
                'user_id' => $user_id,
                'grader_id' => $this->core->getUser()->getId(),
                'filename' => $file_name,
                'file_path' => $file_path,
                'annotation_jsons' => $annotation_jsons,
                'student_popup' => $is_student,
                'can_download' => !$is_peer_grader,
                'display_file_url' => $display_file_url,
                'directory' => $this->getDirectoryFromPath($file_path),
                'localcss' => $localcss,
                'localjs' => $localjs,
                'csrfToken' => $this->core->getCsrfToken(),
                'anon_path' => $anon_path,
                'download_path' => $download_path
            ]);
        }
        catch (\Exception $e) {
            $this->core->addErrorMessage("Error rendering twig template: " . $e->getMessage());
            $this->core->addErrorMessage("Stack trace: " . $e->getTraceAsString());
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }
    }

    /**
     * Helper method to extract directory type from file path
     */
    private function getDirectoryFromPath(string $file_path): string {
        if (str_contains($file_path, 'user_assignment_settings.json')) {
            return 'submission_versions';
        }
        elseif (str_contains($file_path, 'submissions')) {
            return 'submissions';
        }
        elseif (str_contains($file_path, 'results_public')) {
            return 'results_public';
        }
        elseif (str_contains($file_path, 'results')) {
            return 'results';
        }
        elseif (str_contains($file_path, 'checkout')) {
            return 'checkout';
        }
        elseif (str_contains($file_path, 'attachments')) {
            return 'attachments';
        }
        return 'submissions'; // default
    }
}
