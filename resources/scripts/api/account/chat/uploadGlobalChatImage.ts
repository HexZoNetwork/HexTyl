import http from '@/api/http';

export default (file: File): Promise<string> => {
    return new Promise((resolve, reject) => {
        const data = new FormData();
        data.append('image', file);

        http.post('/api/client/account/chat/upload', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
            .then(({ data: response }) => resolve(response.attributes.url))
            .catch(reject);
    });
};
