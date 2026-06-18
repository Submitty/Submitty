export interface UnseenNotificationCount {
    term: string;
    title: string;
    name: string;
    count: number;
}

export interface GetUnseenCountsResponse {
    status: string;
    data: UnseenNotificationCount[];
};
