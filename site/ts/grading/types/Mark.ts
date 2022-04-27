export interface Mark {
    id: number,
    points: number,
    publish: boolean,
    title: string,
    deleted?: boolean,
    order?: number;
}

export interface AddNewMarkResponse {
    mark_id: number
}
