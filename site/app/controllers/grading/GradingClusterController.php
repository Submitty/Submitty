<?php

declare(strict_types=1);

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\entities\grading_cluster\GradingCluster;
use app\entities\grading_cluster\GradingClusterConfig;
use app\entities\grading_cluster\GradingClusterAlgorithm;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;
use app\libraries\FileUtils;

class GradingClusterController extends AbstractController {
    /**
     * Renames an existing cluster for a gradeable.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering/{cluster_id}/rename", methods: ["POST"], requirements: ["cluster_id" => "\d+"])]
    public function renameCluster(string $gradeable_id, int $cluster_id): JsonResponse {
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

        $cluster_name = trim($_POST['cluster_name'] ?? '');
        if ($cluster_name === '') {
            return JsonResponse::getErrorResponse("Cluster name cannot be empty.");
        }
        if (mb_strlen($cluster_name) > 255) {
            return JsonResponse::getErrorResponse("Cluster name cannot exceed 255 characters.");
        }

        $entity_manager = $this->core->getCourseEntityManager();
        $cluster = $entity_manager->getRepository(GradingCluster::class)->find($cluster_id);
        if ($cluster === null || $cluster->getConfig()->getGradeableId() !== $gradeable->getId()) {
            return JsonResponse::getErrorResponse("Invalid cluster.");
        }
        if ($cluster_name === 'Unclustered') {
            return JsonResponse::getErrorResponse("This cluster name is reserved.");
        }
        foreach ($cluster->getConfig()->getClusters() as $other_cluster) {
            if ($other_cluster->getId() !== $cluster->getId() && $other_cluster->getClusterName() === $cluster_name) {
                return JsonResponse::getErrorResponse("Another cluster already uses this name.");
            }
        }

        $cluster->setClusterName($cluster_name);
        $entity_manager->flush();

        return JsonResponse::getSuccessResponse([
            'cluster_id' => $cluster->getId(),
            'cluster_name' => $cluster->getClusterName(),
        ]);
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

        $clustering_job_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "clustering__" . $semester . "__" . $course . "__" . $gradeable->getId() . ".json");

        $clustering_job_data = [
            "job" => "GradingClustering",
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $gradeable->getId(),
            "algorithm" => $algorithm->value
        ];

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
