import { Component } from './Component';

export interface GradeableRubric {
    id: string,
    precision: number,
    components: Component[];
}
