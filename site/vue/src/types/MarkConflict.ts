export type MarkConflictResolution = 'dom' | 'server' | 'old-server';

export interface MarkInfo {
    id: number;
    points: number;
    title: string | null;
    publish: boolean;
}

export interface ConflictInfo {
    domMark: MarkInfo;
    serverMark: MarkInfo | null;
    oldServerMark: MarkInfo | null;
    localDeleted: boolean;
}

export interface RawMark {
    id: number;
    points: number;
    title: string | undefined;
    publish: boolean;
}

export interface RawConflictInfo {
    domMark: RawMark;
    serverMark: RawMark | null;
    oldServerMark: RawMark | null;
    localDeleted: boolean;
}
