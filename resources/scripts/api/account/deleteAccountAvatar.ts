import http from '@/api/http';

export default async (): Promise<string> => {
    const response = await http.delete('/api/client/account/avatar');

    return response.data?.attributes?.url || '';
};
