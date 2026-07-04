"""
Placeholder dummy split algorithm: splits submitters A-M into Cluster A, N-Z into Cluster B.
"""


def run(submitters):
    cluster_a = []
    cluster_b = []

    for submitter in submitters:
        # submitter is a dict with 'user_id' and 'team_id'
        identifier = submitter.get('user_id') or submitter.get('team_id') or ''
        first_char = identifier[0].upper() if identifier else ''

        if 'A' <= first_char <= 'M':
            cluster_a.append(submitter)
        else:
            cluster_b.append(submitter)

    return {
        'Cluster A (A-M)': cluster_a,
        'Cluster B (N-Z)': cluster_b,
    }
