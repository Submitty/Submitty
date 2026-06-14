<?php

declare(strict_types=1);

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\entities\grading_cluster\GradingCluster;
use app\entities\grading_cluster\GradingClusterAlgorithm;
use app\entities\grading_cluster\GradingClusterMember;
use app\libraries\response\JsonResponse;
use app\libraries\grading_cluster\DummySplitAlgorithm;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\routers\AccessControl;

class GradingClusterController extends AbstractController {
    /**
     * Generates clusters for a given gradeable using the specified algorithm.
     */
    #[AccessControl(role: "FULL_ACCESS_GRADER")]
    #[Route("/api/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering", methods: ["POST"])]
    public function createClustering(string $gradeable_id): JsonResponse {
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
            GradingClusterAlgorithm::DummySplit => (new DummySplitAlgorithm())->run($submitters),
        };

        foreach ($cluster_groups as $cluster_name => $members) {
            if ($members === []) {
                continue;
            }

            $cluster = new GradingCluster($gradeable_id, $cluster_name, $algorithm);
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
    #[Route("/api/courses/{_semester}/{_course}/gradeable/{gradeable_id}/clustering", methods: ["GET"])]
    public function getClusters(string $gradeable_id): JsonResponse {
        $clusters = $this->core->getCourseEntityManager()
            ->getRepository(GradingCluster::class)
            ->findBy(['gradeable_id' => $gradeable_id], ['id' => 'ASC']);

        $result = [];
        foreach ($clusters as $cluster) {
            $result[] = [
                'id'           => $cluster->getId(),
                'cluster_name' => $cluster->getClusterName(),
                'algorithm'    => $cluster->getAlgorithm()->value,
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
}
