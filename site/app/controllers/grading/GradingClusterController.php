<?php

declare(strict_types=1);

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\entities\grading_cluster\GradingClusterConfig;
use app\entities\grading_cluster\GradingClusterAlgorithm;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;
use app\libraries\FileUtils;

class GradingClusterController extends AbstractController {

    /**
     * Set the group of the given paths to the group that owns the course, so the
     * daemon user can read files uploaded by PHP. The course config file is used
     * as the reference for the correct group, the same approach notebook builder
     * takes for its uploads.
     *
     * @param string[] $paths
     * @return string|null An error message, or null on success.
     */
    private function applyCourseGroup(array $paths): ?string {
        $course_group = @filegroup($this->core->getConfig()->getCourseJsonPath());
        if ($course_group === false) {
            return "Could not determine the course group for the uploaded script.";
        }
        foreach ($paths as $path) {
            if (filegroup($path) === $course_group) {
                continue;
            }
            if (!@chgrp($path, $course_group)) {
                return "Could not set the group on '" . basename($path) . "' so the "
                    . "clustering daemon can read it. Check the permissions on the "
                    . "course's clustering_uploads directory.";
            }
        }
        return null;
    }

    /**
     * Generates clusters for a given gradeable using the specified algorithm.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/create_clustering", methods: ["POST"])]
    public function createClustering(string $gradeable_id): JsonResponse {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return JsonResponse::getErrorResponse("Invalid CSRF token.");
        }

        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            return JsonResponse::getErrorResponse("Invalid gradeable_id parameter.");
        }

        if (!$this->core->getConfig()->isSubmissionClusteringEnabled()) {
            return JsonResponse::getErrorResponse("Clustering is not enabled for this gradeable.");
        }

        $algorithm = GradingClusterAlgorithm::tryFrom($_POST['algorithm'] ?? '');
        if ($algorithm === null) {
            return JsonResponse::getErrorResponse("Invalid or missing algorithm parameter.");
        }

        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        $script_path = '';
        if ($algorithm === GradingClusterAlgorithm::CustomUpload) {
            // Validate uploaded file
            if (!isset($_FILES['custom_script']) || $_FILES['custom_script']['error'] !== UPLOAD_ERR_OK) {
                return JsonResponse::getErrorResponse("No file was uploaded or the upload failed.");
            }

            $uploaded_file = $_FILES['custom_script'];
            $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            if ($file_extension !== 'py') {
                return JsonResponse::getErrorResponse("Only Python (.py) files are allowed.");
            }

            // 1MB file size limit
            if ($uploaded_file['size'] > 1048576) {
                return JsonResponse::getErrorResponse("Custom script file size must not exceed 1MB.");
            }

            // Save to course clustering_uploads directory
            $upload_dir = FileUtils::joinPaths(
                $this->core->getConfig()->getCoursePath(),
                'clustering_uploads',
                $gradeable->getId()
            );

            if (!FileUtils::createDir($upload_dir, true, 02770)) {
                return JsonResponse::getErrorResponse("Failed to create clustering upload directory.");
            }

            $script_path = FileUtils::joinPaths($upload_dir, 'custom_algorithm.py');
            // Remove any file from a previous upload first. copy() onto an
            // existing file keeps that file's owner, group and mode, so a file
            // left behind by an older run stays unreadable to the daemon.
            if (file_exists($script_path) && !unlink($script_path)) {
                return JsonResponse::getErrorResponse("Failed to replace the previous custom script.");
            }
            if (!copy($uploaded_file['tmp_name'], $script_path)) {
                return JsonResponse::getErrorResponse("Failed to save uploaded script.");
            }

            // PHP-FPM runs as submitty_php:submitty_php but the daemon that reads
            // this script is not in the submitty_php group, so the group must be
            // set to the course group explicitly rather than relying on the
            // parent directory's setgid bit.
            $group_error = $this->applyCourseGroup([$upload_dir, $script_path]);
            if ($group_error !== null) {
                return JsonResponse::getErrorResponse($group_error);
            }
            chmod($script_path, 0660);
        }

        // Clear any status from a previous run so a stale error is not reported
        // against this one.
        $status_file = FileUtils::joinPaths(
            $this->core->getConfig()->getCoursePath(),
            'clustering',
            $gradeable->getId(),
            'status.json'
        );
        if (file_exists($status_file)) {
            unlink($status_file);
        }

        $clustering_job_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "clustering__" . $semester . "__" . $course . "__" . $gradeable->getId() . ".json");

        $clustering_job_data = [
            "job" => "GradingClustering",
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $gradeable->getId(),
            "algorithm" => $algorithm->value
        ];

        if ($script_path !== '') {
            $clustering_job_data['script_path'] = $script_path;
        }

        if (
            (!is_writable($clustering_job_file) && file_exists($clustering_job_file))
            || file_put_contents($clustering_job_file, json_encode($clustering_job_data, JSON_PRETTY_PRINT)) === false
        ) {
            return JsonResponse::getErrorResponse("Failed to write clustering job to daemon queue.");
        }

        return JsonResponse::getSuccessResponse([]);
    }

    /**
     * Checks if the clustering job is currently in progress.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering/status", methods: ["GET"])]
    public function checkClusteringStatus(string $gradeable_id): JsonResponse {
        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            return JsonResponse::getErrorResponse("Invalid gradeable_id parameter.");
        }

        if (!$this->core->getConfig()->isSubmissionClusteringEnabled()) {
            return JsonResponse::getErrorResponse("Clustering is not enabled for this gradeable.");
        }

        $semester = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();
        $daemon_job_queue_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue");
        $job_name = "clustering__" . $semester . "__" . $course . "__" . $gradeable->getId() . ".json";

        $clustering_job_file = FileUtils::joinPaths($daemon_job_queue_path, $job_name);
        $processing_job_file = FileUtils::joinPaths($daemon_job_queue_path, "PROCESSING_" . $job_name);

        if (file_exists($clustering_job_file) || file_exists($processing_job_file)) {
            return JsonResponse::getSuccessResponse(['status' => 'processing']);
        }

        // The daemon writes the outcome under the course directory. It cannot be
        // written into the daemon job queue, because anything created there is
        // picked up and consumed by the job queue watcher.
        $status_file = FileUtils::joinPaths(
            $this->core->getConfig()->getCoursePath(),
            'clustering',
            $gradeable->getId(),
            'status.json'
        );

        if (file_exists($status_file)) {
            $status_data = json_decode(file_get_contents($status_file), true);
            if (is_array($status_data) && isset($status_data['error'])) {
                return JsonResponse::getSuccessResponse([
                    'status' => 'error',
                    'error_message' => $status_data['error']
                ]);
            }
        }

        return JsonResponse::getSuccessResponse(['status' => 'done']);
    }

    /**
     * Fetches all clusters and their members for a given gradeable.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering", methods: ["GET"])]
    public function getClusters(string $gradeable_id): JsonResponse {
        $gradeable = $this->tryGetGradeable($gradeable_id, false);
        if ($gradeable === false) {
            return JsonResponse::getErrorResponse("Invalid gradeable_id parameter.");
        }

        if (!$this->core->getConfig()->isSubmissionClusteringEnabled()) {
            return JsonResponse::getErrorResponse("Clustering is not enabled for this gradeable.");
        }

        $config = $this->core->getCourseEntityManager()
            ->getRepository(GradingClusterConfig::class)
            ->findWithClustersAndMembers($gradeable->getId());

        if ($config === null) {
            return JsonResponse::getSuccessResponse([
                "gradeable_id" => $gradeable->getId(),
                "clusters"     => [],
            ]);
        }

        $submitters = $this->core->getQueries()->getActiveSubmittersForGradeable($gradeable->getId());
        $active_versions = [];
        foreach ($submitters as $submitter) {
            $id = $submitter['user_id'] ?? $submitter['team_id'];
            $active_versions[$id] = (int) $submitter['active_version'];
        }

        $result = [];
        foreach ($config->getClusters() as $cluster) {
            $valid_members = [];
            foreach ($cluster->getValidMembers($active_versions) as $m) {
                $valid_members[] = [
                    'id'      => $m->getId(),
                    'user_id' => $m->getUserId(),
                    'team_id' => $m->getTeamId(),
                    'active_version' => $m->getActiveVersion(),
                ];
            }

            $result[] = [
                'id'           => $cluster->getId(),
                'cluster_name' => $cluster->getClusterName(),
                'algorithm'    => $config->getAlgorithm()->value,
                'member_count' => count($valid_members),
                'members'      => $valid_members,
            ];
        }

        return JsonResponse::getSuccessResponse([
            "gradeable_id" => $gradeable->getId(),
            "clusters"     => $result,
        ]);
    }
}
