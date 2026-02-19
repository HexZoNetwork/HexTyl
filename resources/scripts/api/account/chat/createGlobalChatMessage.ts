import http, { FractalResponseData } from '@/api/http';
import { ChatMessage, rawDataToChatMessage } from '@/api/chat/types';

interface Params {
    body?: string;
    mediaUrl?: string;
    replyToId?: number | null;
}

export default (params: Params): Promise<ChatMessage> => {
    return new Promise((resolve, reject) => {
        http.post('/api/client/account/chat/messages', {
            body: params.body,
            media_url: params.mediaUrl,
            reply_to_id: params.replyToId,
        })
            .then(({ data }: { data: FractalResponseData }) => resolve(rawDataToChatMessage(data)))
            .catch(reject);
    });
};
