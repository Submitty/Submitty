import { Mark } from './Mark';

export interface Component {
    id: number,
    title: string,
    ta_comment: string,
    student_comment: string,
    page: number,
    lower_clamp: number,
    default: number,
    max_value: number,
    upper_clamp: number,
    marks: Mark[],
    is_itempool_linked: boolean,
    itempool_option: string | undefined,
    peer: boolean;
}

