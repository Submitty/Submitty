"""
Single cluster algorithm: places all submitters into a single cluster.
"""

from .clustering_algorithm import ClusteringAlgorithm


class SingleCluster(ClusteringAlgorithm):
    """
    Algorithm that places all submitters into a single cluster.
    """

    def run(self, submitters):
        return {
            'All Submitters': submitters
        }
