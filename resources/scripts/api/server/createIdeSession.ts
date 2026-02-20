import http from '@/api/http';

interface Params {
    terminal?: boolean;
    extensions?: boolean;
}

interface IdeSession {
    token: string;
    token_hash: string;
    expires_at: string;
    launch_url: string;
    ttl_minutes: number;
}

export default (uuid: string, params: Params = {}): Promise<IdeSession> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/ide/session`, {
            terminal: !!params.terminal,
            extensions: !!params.extensions,
        })
            .then(({ data }) => resolve(data.attributes as IdeSession))
            .catch(reject);
    });
};
