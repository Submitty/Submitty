<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;

class ClusterController extends AbstractController {

    /**
     * Get submission clusters for a gradeable.
     *
     * @Route("/courses/{semester}/{course}/gradeable/{gradeable_id}/clusters",
     *        methods={"GET"})
     * @AccessControl(role="LIMITED_ACCESS_GRADER")
     */
    public function getClusters(string $gradeable_id): JsonResponse {
        // Validate gradeable exists
        $gradeable = $this->core->getQueries()
                         ->getGradeableConfig($gradeable_id);
        if ($gradeable === null) {
            return JsonResponse::getFailResponse("Gradeable not found");
        }

        $course_path  = $this->core->getConfig()->getCoursePath();
        $submissions_dir = "{$course_path}/submissions/{$gradeable_id}";
        $output_file  = "{$course_path}/results/{$gradeable_id}/clusters.json";

        // Return cached result if fresh (< 10 minutes old)
        if (file_exists($output_file)
            && (time() - filemtime($output_file)) < 600) {
            $data = json_decode(file_get_contents($output_file), true);
            return JsonResponse::getSuccessResponse($data);
        }

        // Run Python clustering script
        $script = "/usr/local/submitty/python_submitty_utils"
                . "/submitty_utils/cluster_submissions.py";
        $cmd = sprintf(
            "python3 %s --submissions_dir %s --output_file %s --n_clusters 5 2>&1",
            escapeshellarg($script),
            escapeshellarg($submissions_dir),
            escapeshellarg($output_file)
        );
        exec($cmd, $output, $exit_code);

        if ($exit_code !== 0 || !file_exists($output_file)) {
            return JsonResponse::getFailResponse(
                "Clustering failed: " . implode("\n", $output)
            );
        }

        $data = json_decode(file_get_contents($output_file), true);
        return JsonResponse::getSuccessResponse($data);
    }
}