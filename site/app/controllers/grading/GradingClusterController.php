<?php

declare(strict_types=1);

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\entities\grading_cluster\GradingCluster;
use app\entities\grading_cluster\GradingClusterConfig;
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
    #[Route("/courses/{_semester}/{_course}/gradeable/{gradeable_id}/create_clustering", methods: ["POST"])]
    public function createClustering(string $gradeable_id): JsonResponse {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return JsonResponse::getErrorResponse("Invalid CSRF token.");
        }

        if ($this->tryGetGradeable($gradeable_id, false) === false) {
            return JsonResponse::getErrorResponse("Invalid gradeable_id parameter.");
        }

        $algorithm = GradingClusterAlgorithm::tryFrom($_POST['algorithm'] ?? '');
        if ($algorithm === null) {
            return JsonResponse::getErrorResponse("Invalid or missing algorithm parameter.");
        }

        $em = $this->core->getCourseEntityManager();

        $submitters = $this->core->getQueries()->getActiveSubmittersForGradeable($gradeable_id);
        if ($submitters === []) {
            return JsonResponse::getErrorResponse("No active submissions found for this gradeable.");
        }

        $cluster_groups = match ($algorithm) {
            GradingClusterAlgorithm::DummySplit => (new DummySplitAlgorithm())->run($submitters),
        };

        // Deleting the config cascades and deletes all associated clusters and members
        $em->getRepository(GradingClusterConfig::class)->deleteByGradeableId($gradeable_id);

        $config = new GradingClusterConfig($gradeable_id, $algorithm);
        $em->persist($config);

        foreach ($cluster_groups as $cluster_name => $members) {
            if ($members === []) {
                continue;
            }

            $cluster = new GradingCluster($config, $cluster_name);
            foreach ($members as $member) {
                new GradingClusterMember($cluster, $member['user_id'] ?? null, $member['team_id'] ?? null, (int) $member['active_version']);
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
        if ($this->tryGetGradeable($gradeable_id, false) === false) {
            return JsonResponse::getErrorResponse("Invalid gradeable_id parameter.");
        }

        $config = $this->core->getCourseEntityManager()
            ->getRepository(GradingClusterConfig::class)
            ->findOneBy(['gradeable_id' => $gradeable_id]);

        if ($config === null) {
            return JsonResponse::getSuccessResponse([
                "gradeable_id" => $gradeable_id,
                "clusters"     => [],
            ]);
        }

        $submitters = $this->core->getQueries()->getActiveSubmittersForGradeable($gradeable_id);
        $active_versions = [];
        foreach ($submitters as $submitter) {
            $id = $submitter['user_id'] ?? $submitter['team_id'];
            $active_versions[$id] = (int) $submitter['active_version'];
        }

        $result = [];
        foreach ($config->getClusters() as $cluster) {
            $valid_members = [];
            foreach ($cluster->getMembers() as $m) {
                $member_id = $m->getUserId() ?? $m->getTeamId();
                if (isset($active_versions[$member_id]) && $active_versions[$member_id] === $m->getActiveVersion()) {
                    $valid_members[] = [
                        'id'      => $m->getId(),
                        'user_id' => $m->getUserId(),
                        'team_id' => $m->getTeamId(),
                        'active_version' => $m->getActiveVersion(),
                    ];
                }
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
            "gradeable_id" => $gradeable_id,
            "clusters"     => $result,
        ]);
    }
}
