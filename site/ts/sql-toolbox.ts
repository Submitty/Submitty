import { buildCourseUrl, getCsrfToken } from './utils/server';

export type ServerResponse<T> =
    | {
        status: 'success';
        data: T;
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

        const json = await resp.json() as ServerResponse<{ [key: string]: number | string | null }[]>;
        return json;
    }
    catch (exc) {
        return {
            status: 'fail',
            message: (exc as Error).toString(),
        };
    }
}

export async function saveSqlQuery(query_name: string, query: string) {
    const form = new FormData();
    form.append('csrf_token', getCsrfToken());
    form.append('query_name', query_name);
    form.append('query', query);

    try {
        const response = await fetch(buildCourseUrl(['sql_toolbox', 'queries']), {
            method: 'POST',
            body: form,
        });

        if (!response.ok) {
            throw new Error('Failed to save query');
        }

        const result = await response.json() as ServerResponse<number>;
        return result;
    }
    catch (e) {
        return {
            status: 'fail',
            message: (e as Error).toString(),
        };
    }
}
export async function deleteSqlQuery(id: number) {
    const form_data = new FormData();
    form_data.append('csrf_token', getCsrfToken());
    form_data.append('query_id', id.toString());

    try {
        const resp = await fetch(
            buildCourseUrl(['sql_toolbox', 'queries', 'delete']),
            {
                method: 'POST',
                body: form_data,
            },
        );

        if (!resp.ok) {
            throw new Error('Failed to delete query.');
        }
        const json = await resp.json() as ServerResponse<string>;
        return json;
    }
    catch (exc) {
        return {
            status: 'fail',
            message: (exc as Error).toString(),
        };
    }
}
