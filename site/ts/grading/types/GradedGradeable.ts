export interface GradedGradeable {
    ta_grading_total: number,
    user_group: number,
    peer_gradeable: boolean,
    ta_grading_earned: number,
    anon_id: string,
    itempool_items: {[id: number]: string}
    // If there is autograding
    auto_grading_total?: number,
    // If there is autograding and the user has a grade
    auto_grading_earned?: number,
    // If peer_gradeable is true
    see_peer_grade?: number,
    peer_grade_earned?: number,
    peer_total?: number,
    combined_peer_score: number
}
