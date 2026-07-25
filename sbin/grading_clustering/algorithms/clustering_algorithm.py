from abc import ABC, abstractmethod


class ClusteringAlgorithm(ABC):
    """
    Abstract base class for all grading clustering algorithms.
    """

    @abstractmethod
    def run(self, submitters):
        pass
