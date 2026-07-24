<?php

declare(strict_types=1);

namespace app\entities\grading_cluster;

enum GradingClusterAlgorithm: string {
    case DummySplit = 'dummy_split';

    public function description(): string {
        return match($this) {
            self::DummySplit => 'Clusters students based on the starting letter of user_id / team_id. Results in 3 clusters: Cluster A (A-M), Cluster B (N-Z), and Unclustered (students without an active submission or students who have changed their active version after Clustering algorithm was initiated).'
        };
    }
}
