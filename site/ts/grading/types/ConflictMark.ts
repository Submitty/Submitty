import { Mark } from './Mark';

export interface ConflictMark {
    domMark: Mark,
    serverMark: Mark | null,
    oldServerMark: Mark | null,
    localDeleted: boolean
}

export interface ConflictMarks {
    [key: number]: ConflictMark
}
