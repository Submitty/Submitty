<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use app\models\gradeable\GradingCluster;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;

/**
 * Handles all API requests related to AI-assisted grading clusters.
 *
 * Endpoints:
 *   POST .../clustering/create  - Generate clusters for a gradeable
 *   POST .../clustering/clear   - Delete all clusters for a gradeable
 *   GET  .../clustering         - Fetch all clusters for a gradeable
 */
class GradingClusterController extends AbstractController {

    /**
     * Generates clusters for a given gradeable using the specified algorithm.
     *
     * For PR 1, only a simple "dummy_split" algorithm is implemented, which
     * splits students alphabetically into two groups. Future PRs will add
     * more sophisticated algorithms
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering/create", methods: ["POST"])]
    public function createClustering(string $gradeable_id): JsonResponse {
        // 1. Validate CSRF token to prevent cross-site request forgery
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return JsonResponse::getErrorResponse("Invalid CSRF token.");
        }

        // 2. Read the algorithm choice from the request (defaults to dummy_split for PR 1)
        $algorithm = $_POST['algorithm'] ?? 'dummy_split';

        // 3. Clear any existing clusters first to ensure a fresh start
        $this->core->getQueries()->clearGradingClustersByGradeable($gradeable_id);

        // 4. Get all users with an active submission for this gradeable
        //    We use electronic_gradeable_version to find who has submitted
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

        // 5. Run the selected algorithm
        switch ($algorithm) {
            case 'dummy_split':
            default:
                $cluster_groups = $this->runDummySplitAlgorithm($submitters);
                break;
        }

        // 6. Save the resulting groups to the database
        foreach ($cluster_groups as $label => $members) {
            if (empty($members)) {
                continue;
            }

            // Create the cluster metadata row and get its new DB-generated ID
            $cluster_id = $this->core->getQueries()->createGradingCluster(
                $gradeable_id,
                $label,
                $algorithm
            );

            // Assign each submitter to this cluster
            foreach ($members as $member) {
                $this->core->getQueries()->insertClusterMember(
                    $cluster_id,
                    $member['user_id'] ?? null,
                    $member['team_id'] ?? null
                );
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
     * Because grading_cluster_members has ON DELETE CASCADE, all members
     * are removed automatically when the parent cluster row is deleted.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering/clear", methods: ["POST"])]
    public function clearClustering(string $gradeable_id): JsonResponse {
        // 1. Validate CSRF token
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return JsonResponse::getErrorResponse("Invalid CSRF token.");
        }

        // 2. Delete all clusters from the database
        $this->core->getQueries()->clearGradingClustersByGradeable($gradeable_id);

        return JsonResponse::getSuccessResponse([
            "message" => "All clusters have been cleared successfully.",
        ]);
    }

    /**
     * Fetches all clusters and their members for a given gradeable.
     * This will be used by PR 2 to display clusters in the UI.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering", methods: ["GET"])]
    public function getClusters(string $gradeable_id): JsonResponse {
        $raw_clusters = $this->core->getQueries()->getGradingClustersByGradeable($gradeable_id);

        // Convert raw DB rows into GradingCluster model objects for clean access
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

    // Private Algorithm Helpers

    /**
     * Placeholder "dummy split" algorithm for PR 1 testing.
     *
     * Splits all submitters into two groups alphabetically:
     *   - "Cluster A (A-M)": users whose id/team_id starts with A-M
     *   - "Cluster B (N-Z)": users whose id/team_id starts with N-Z
     *
     * This is intentionally simple — it is only here to verify that the
     * database schema and API pipeline work correctly before real
     * algorithms are introduced in later PRs.
     *
     * @param array<int, array<string, mixed>> $submitters
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function runDummySplitAlgorithm(array $submitters): array {
        $cluster_a = [];
        $cluster_b = [];

        foreach ($submitters as $submitter) {
            // Use whichever ID is available (user or team)
            $identifier = $submitter['user_id'] ?? $submitter['team_id'] ?? '';
            $first_char = strtoupper(substr($identifier, 0, 1));

            if ($first_char >= 'A' && $first_char <= 'M') {
                $cluster_a[] = $submitter;
            }
            else {
                $cluster_b[] = $submitter;
            }
        }

        return [
            'Cluster A (A-M)' => $cluster_a,
            'Cluster B (N-Z)' => $cluster_b,
        ];
    }
}
