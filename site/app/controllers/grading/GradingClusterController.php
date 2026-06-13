<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\entities\grading_cluster\GradingCluster;
use app\entities\grading_cluster\GradingClusterAlgorithm;
use app\entities\grading_cluster\GradingClusterMember;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;

class GradingClusterController extends AbstractController {
    /**
     * Generates clusters for a given gradeable using the specified algorithm.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering", methods: ["POST"])]
    public function createClustering(string $gradeable_id): JsonResponse {
        // 1. Validate CSRF token
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return JsonResponse::getErrorResponse("Invalid CSRF token.");
        }

        $algorithm = GradingClusterAlgorithm::tryFrom($_POST['algorithm'] ?? '');
        if ($algorithm === null) {
            return JsonResponse::getErrorResponse("Invalid or missing algorithm parameter.");
        }

        $em = $this->core->getCourseEntityManager();

        $em->createQuery('DELETE FROM app\entities\grading_cluster\GradingCluster c WHERE c.gradeable_id = :gradeable_id')
           ->setParameter('gradeable_id', $gradeable_id)
           ->execute();

        $submitters = $this->core->getQueries()->getActiveSubmittersForGradeable($gradeable_id);
        if ($submitters === []) {
            return JsonResponse::getErrorResponse("No active submissions found for this gradeable.");
        }

        $cluster_groups = match ($algorithm) {
            GradingClusterAlgorithm::DummySplit => $this->runDummySplitAlgorithm($submitters),
        };

        foreach ($cluster_groups as $label => $members) {
            if ($members === []) {
                continue;
            }

            $cluster = new GradingCluster($gradeable_id, $label, $algorithm);
            foreach ($members as $member) {
                new GradingClusterMember($cluster, $member['user_id'] ?? null, $member['team_id'] ?? null);
            }
            $em->persist($cluster);
        }
        $em->flush();

        return JsonResponse::getSuccessResponse([]);
    }

    /**
     * Fetches all clusters and their members for a given gradeable.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering", methods: ["GET"])]
    public function getClusters(string $gradeable_id): JsonResponse {
        $clusters = $this->core->getCourseEntityManager()
            ->getRepository(GradingCluster::class)
            ->findBy(['gradeable_id' => $gradeable_id], ['created_at' => 'ASC']);

        $result = [];
        foreach ($clusters as $cluster) {
            $result[] = [
                'id'           => $cluster->getId(),
                'label'        => $cluster->getLabel(),
                'algorithm'    => $cluster->getAlgorithm()->value,
                'created_at'   => $cluster->getCreatedAt()->format('Y-m-d H:i:s'),
                'member_count' => $cluster->getMemberCount(),
                'members'      => array_map(
                    fn(GradingClusterMember $m) => [
                        'id'      => $m->getId(),
                        'user_id' => $m->getUserId(),
                        'team_id' => $m->getTeamId(),
                    ],
                    $cluster->getMembers()->toArray()
                ),
            ];
        }

        return JsonResponse::getSuccessResponse([
            "gradeable_id" => $gradeable_id,
            "clusters"     => $result,
        ]);
    }

    /**
     * Placeholder dummy split algorithm: splits submitters A–M into Cluster A, N–Z into Cluster B.
     *
     * @param array<int, array<string, mixed>> $submitters
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function runDummySplitAlgorithm(array $submitters): array {
        $cluster_a = [];
        $cluster_b = [];

        foreach ($submitters as $submitter) {
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
