export interface Notification {
    id: number;
    component: string;
    metadata: string;
    content: string;
    seen: boolean;
    elapsed_time: number;
    created_at: string;
    notify_time: string;
    semester: string;
    course: string;
    notification_url: string;
}
