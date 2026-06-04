<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use app\models\gradeable\GradingCluster;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;

// Handles all API requests related to AI-assisted grading clusters.
class GradingClusterController extends AbstractController
{

    /**
     * Generates clusters for a given gradeable using the specified algorithm.
     *
     * @param string $gradeable_id
     *
     * @return JsonResponse
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering/create", methods: ["POST"])]
    public function createClustering(string $gradeable_id): JsonResponse
    {
        // 1. Validate CSRF token
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return JsonResponse::getErrorResponse("Invalid CSRF token.");
        }

        // 2. Read the algorithm choice
        $algorithm = $_POST['algorithm'] ?? 'dummy_split';

        // 3. Clear existing clusters
        $this->core->getQueries()->clearGradingClustersByGradeable($gradeable_id);

        // 4. Get all submitters
        $this->core->getCourseDB()->query(
            "SELECT DISTINCT egv.user_id, egv.team_id
             FROM electronic_gradeable_version egv
             WHERE egv.g_id = ? AND egv.active_version > 0",
            [$gradeable_id]
        );
        $submitters = $this->core->getCourseDB()->rows();

        if (empty($submitters)) {
            return JsonResponse::getErrorResponse("No active submissions found for this gradeable.");
        }

        // 5. Run algorithm
        switch ($algorithm) {
            case 'dummy_split':
            default:
                $cluster_groups = $this->_runDummySplitAlgorithm($submitters);
                break;
        }

        // 6. Save clusters
        foreach ($cluster_groups as $label => $members) {
            if (empty($members)) {
                continue;
            }

            $cluster_id = $this->core->getQueries()->createGradingCluster($gradeable_id, $label, $algorithm);

            foreach ($members as $member) {
                $this->core->getQueries()->insertClusterMember($cluster_id, $member['user_id'] ?? null, $member['team_id'] ?? null);
            }
        }

        return JsonResponse::getSuccessResponse([
            "message"       => "Clustering completed successfully.",
            "cluster_count" => count($cluster_groups),
            "algorithm"     => $algorithm,
        ]);
    }

    /**
     * Deletes all clusters for a given gradeable.
     *
     * @param string $gradeable_id
     *
     * @return JsonResponse
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering/clear", methods: ["POST"])]
    public function clearClustering(string $gradeable_id): JsonResponse
    {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return JsonResponse::getErrorResponse("Invalid CSRF token.");
        }

        $this->core->getQueries()->clearGradingClustersByGradeable($gradeable_id);

        return JsonResponse::getSuccessResponse([
            "message" => "All clusters have been cleared successfully.",
        ]);
    }

    /**
     * Fetches all clusters and their members for a given gradeable.
     *
     * @param string $gradeable_id
     *
     * @return JsonResponse
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering", methods: ["GET"])]
    public function getClusters(string $gradeable_id): JsonResponse
    {
        $raw_clusters = $this->core->getQueries()->getGradingClustersByGradeable($gradeable_id);

        $clusters = [];
        foreach ($raw_clusters as $entry) {
            $cluster = new GradingCluster($this->core, $entry['cluster']);
            $cluster->setMembers($entry['members']);
            $clusters[] = [
                'id'           => $cluster->getId(),
                'label'        => $cluster->getLabel(),
                'algorithm'    => $cluster->getAlgorithm(),
                'created_at'   => $cluster->getCreatedAt(),
                'member_count' => $cluster->getMemberCount(),
                'members'      => $cluster->getMembers(),
            ];
        }

        return JsonResponse::getSuccessResponse([
            "gradeable_id" => $gradeable_id,
            "clusters"     => $clusters,
        ]);
    }

    /**
     * Placeholder dummy split algorithm.
     *
     * @param array<int, array<string, mixed>> $submitters
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function _runDummySplitAlgorithm(array $submitters): array
    {
        $cluster_a = [];
        $cluster_b = [];

        foreach ($submitters as $submitter) {
            $identifier = $submitter['user_id'] ?? $submitter['team_id'] ?? '';
            $first_char = strtoupper(substr($identifier, 0, 1));

            if ($first_char >= 'A' && $first_char <= 'M') {
                $cluster_a[] = $submitter;
            } else {
                $cluster_b[] = $submitter;
            }
        }

        return [
            'Cluster A (A-M)' => $cluster_a,
            'Cluster B (N-Z)' => $cluster_b,
        ];
    }
}
