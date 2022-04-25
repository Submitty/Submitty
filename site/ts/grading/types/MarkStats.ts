export interface MarkStats {
    section_submitter_count: number,
    total_submitter_count: number,
    section_graded_component_count: number,
    total_graded_component_count: number,
    section_total_component_count: number,
    total_total_component_count: number,
    submitter_ids: string[],
    submitter_anon_ids: {[user_id: string]: string}
}
