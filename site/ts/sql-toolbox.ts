import { buildCourseUrl, getCsrfToken } from './utils/server';

export type SqlQueryResult =
    | {
        status: 'success';
        data: { [key: string]: number | string | null }[];
    }
    | {
        status: 'fail';
        message: string;
    };

export type QueryListEntry = {
    id: number;
    query_name: string;
    query: string;
};

export async function runSqlQuery(sql_query: string) {
    const form_data = new FormData();
    form_data.append('csrf_token', getCsrfToken());
    form_data.append('sql', sql_query);

    try {
        const resp = await fetch(
            buildCourseUrl(['sql_toolbox']),
            {
                method: 'POST',
                body: form_data,
            },
        );

        if (!resp.ok) {
            throw new Error('Failed to run query.');
        }

        const json = await resp.json() as SqlQueryResult;

        if (json.status !== 'success') {
            throw new Error(json.message);
        }
        return json;
    }
    catch (exc) {
        return {
            status: 'fail',
            message: (exc as Error).toString(),
        };
    }
}
