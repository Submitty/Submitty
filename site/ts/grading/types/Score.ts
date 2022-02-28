export interface Score {
    ta_grading_complete: boolean,
    ta_grading_earned: number | undefined,
    ta_grading_total: number,
    peer_grade_earned: number | undefined,
    peer_total: number,
    auto_grading_complete: boolean,
    auto_grading_earned?: number,
    auto_grading_total?: number;
}
