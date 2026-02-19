import http, { FractalResponseList } from '@/api/http';
import { ChatMessage, rawDataToChatMessage } from '@/api/chat/types';

export default (uuid: string, limit = 100): Promise<ChatMessage[]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/chat/messages`, { params: { limit } })
            .then(({ data }: { data: FractalResponseList }) => resolve((data.data || []).map(rawDataToChatMessage)))
            .catch(reject);
    });
};
