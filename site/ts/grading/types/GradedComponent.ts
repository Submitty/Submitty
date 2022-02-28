export interface GradedComponent {
    score: number,
    comment: string,
    custom_mark_selected: boolean,
    mark_ids: number[],
    graded_version: number,
    grade_time: string,
    grader_id: string,
    verifier_id: string,
    custom_mark_enabled: number,
}
