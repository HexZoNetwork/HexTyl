import http from '@/api/http';

export default async (avatar: File): Promise<string> => {
    const data = new FormData();
    data.append('avatar', avatar);

    const response = await http.post('/api/client/account/avatar', data, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });

    return response.data?.attributes?.url || '';
};
