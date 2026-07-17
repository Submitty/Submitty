<?php

declare(strict_types=1);

namespace app\libraries\grading_cluster;

class DummySplitAlgorithm implements GradingClusterAlgorithmInterface {
    /**
     * Placeholder dummy split algorithm: splits submitters A–M into Cluster A, N–Z into Cluster B.
     *
     * @param array<int, array<string, mixed>> $submitters
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function run(array $submitters): array {
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
