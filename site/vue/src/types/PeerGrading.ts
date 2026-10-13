export interface PeerComponent {
    id: string;
    title: string;
    max: number;
    marks: number[];
    extra_credit?: boolean;
}

export interface MarkInfo {
    title: string;
    points: string;
}

export interface PeerDetails {
    graders: Record<string, string[]>;
    marks_assigned: Record<string, Record<string, number[]>>;
    graded_versions?: Record<string, Record<string, number>>;
    version_conflicts?: Record<string, Record<string, boolean>>;
}
