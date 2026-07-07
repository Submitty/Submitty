<?php

declare(strict_types=1);

namespace app\entities\grading_cluster;

enum GradingClusterAlgorithm: string {
    case DummySplit = 'dummy_split';
}
