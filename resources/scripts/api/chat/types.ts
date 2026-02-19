import { FractalResponseData } from '@/api/http';

export interface ChatMessage {
    id: number;
    senderUuid: string;
    senderEmail: string;
    body: string | null;
    mediaUrl: string | null;
    replyToId: number | null;
    replyPreview: string | null;
    deliveredCount: number;
    readCount: number;
    createdAt: Date;
}

export const rawDataToChatMessage = (data: FractalResponseData): ChatMessage => ({
    id: data.attributes.id,
    senderUuid: data.attributes.sender_uuid,
    senderEmail: data.attributes.sender_email,
    body: data.attributes.body ?? null,
    mediaUrl: data.attributes.media_url ?? null,
    replyToId: data.attributes.reply_to_id ?? null,
    replyPreview: data.attributes.reply_preview ?? null,
    deliveredCount: Number(data.attributes.delivered_count || 0),
    readCount: Number(data.attributes.read_count || 0),
    createdAt: new Date(data.attributes.created_at),
});
