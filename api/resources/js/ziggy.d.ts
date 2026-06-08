declare module 'ziggy-js' {
    export type RouteParams = Record<string, unknown> | unknown[] | string | number;

    export function route(name?: string, params?: RouteParams, absolute?: boolean): string;
}
