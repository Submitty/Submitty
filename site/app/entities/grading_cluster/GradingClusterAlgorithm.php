<?php

declare(strict_types=1);

namespace app\entities\grading_cluster;

enum GradingClusterAlgorithm: string {
    case DummySplit = 'dummy_split';
    case CustomUpload = 'custom_upload';
    case SingleCluster = 'single_cluster';

    public function description(): string {
        return match ($this) {
            self::DummySplit => 'Clusters students based on the starting letter of user_id / team_id. Results in 3 clusters: Cluster A (A-M), Cluster B (N-Z), and Unclustered (students without an active submission or students who have changed their active version after Clustering algorithm was initiated).',
            self::CustomUpload => 'Runs a custom Python clustering script uploaded by the instructor. The script executes inside a secure Docker container with network isolation and resource limits.',
            self::SingleCluster=> 'Returns a single cluster containing all active submitters. Anyone else without an active submission (i.e. Cancelled or Not Submitted) will be "Unclustered"'
        };
    }
}
