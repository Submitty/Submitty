<?php

declare(strict_types=1);

namespace app\libraries\grading_cluster;

interface GradingClusterAlgorithmInterface {
    /**
     * Runs the clustering algorithm.
     *
     * @param array<int, array<string, mixed>> $submitters The submitters to cluster
     * @return array<string, array<int, array<string, mixed>>> A map of cluster label to an array of submitters
     */
    public function run(array $submitters): array;
}
