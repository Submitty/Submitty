"""
Placeholder dummy split algorithm: splits submitters A-M into Cluster A, N-Z into Cluster B.
"""

from .clustering_algorithm import ClusteringAlgorithm


class DummySplit(ClusteringAlgorithm):
    """
    Dummy split algorithm that splits submitters alphabetically into two clusters.
    """

    def run(self, submitters):
        cluster_a = []
        cluster_b = []

        for submitter in submitters:
            # submitter is a dict with 'user_id' and 'team_id'
            identifier = submitter.get('user_id') or submitter.get('team_id') or ''
            
            # find the first alphabetic character to ignore leading numbers or symbols
            first_letter = ''
            for char in identifier:
                if char.isalpha():
                    first_letter = char.upper()
                    break

            if 'A' <= first_letter <= 'M' and first_letter != '':
                cluster_a.append(submitter)
            else:
                cluster_b.append(submitter)

        return {
            'Cluster A (A-M)': cluster_a,
            'Cluster B (N-Z)': cluster_b,
        }
