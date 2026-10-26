import { api } from './client';
import type { Resource } from './types';

export type TemplateKind = 'match' | 'weekend_summary';
export type TemplateAppliesTo = 'home' | 'away' | 'any';
export type ProviderKey = 'manual' | 'gateway' | 'meta' | 'twilio' | 'log';
export type DestinationType = 'group' | 'channel' | 'phone' | 'list';
export type MessageStatus = 'draft' | 'queued' | 'sending' | 'sent' | 'manual_sent' | 'failed' | 'unknown' | 'outdated' | 'discarded';

export interface WhatsAppTemplate {
    id: number;
    code: string;
    name: string;
    kind: TemplateKind;
    applies_to: TemplateAppliesTo;
    body: string;
    line_body: string | null;
    provider_template_name: string | null;
    is_active: boolean;
}

export type TemplateInput = Omit<WhatsAppTemplate, 'id' | 'code'>;

export interface WhatsAppDestination {
    id: number;
    scope: 'team' | 'club';
    team_id: number | null;
    team: string | null;
    label: string;
    provider: ProviderKey;
    provider_label: string;
    destination_type: DestinationType;
    destination_type_label: string;
    external_id_hint: string | null;
    invite_url: string | null;
    include_images: boolean;
    is_active: boolean;
}

export interface DestinationInput {
    scope: 'team' | 'club';
    team_id: number | null;
    label: string;
    provider: ProviderKey;
    destination_type: DestinationType;
    external_id: string | null;
    invite_url: string | null;
    include_images: boolean;
    is_active: boolean;
}

export interface ProviderInfo {
    key: ProviderKey;
    destination_types: DestinationType[];
    manual: boolean;
    supports_media: boolean;
}

export interface WhatsAppMessage {
    id: number;
    match_id: number | null;
    team: string | null;
    opponent: string | null;
    is_summary: boolean;
    destination: { id: number; label: string; type: DestinationType; type_label: string; team: string | null };
    provider: ProviderKey;
    provider_label: string;
    is_manual: boolean;
    body: string;
    status: MessageStatus;
    status_label: string;
    attempts: number;
    error_message: string | null;
    queued_at: string | null;
    sent_at: string | null;
    attachments: { id: number; url: string; day: string; page: number }[];
    share_url: string | null;
    can_edit: boolean;
    can_send: boolean;
    can_mark_sent: boolean;
    can_discard: boolean;
}

export type ImageKind = 'schedule' | 'results';

export interface GeneratedImage {
    id: number;
    kind: ImageKind;
    batch_id: string;
    day: string;
    weekday: string;
    page: number;
    total_pages: number;
    width: number;
    height: number;
    renderer: string;
    url: string;
    filename: string;
    warnings: string[];
    created_at: string | null;
}

export interface ImagesState {
    generating: boolean;
    confirmed: boolean;
    images: GeneratedImage[];
}

export interface ImageOptions {
    /** Separa els partits a casa i a fora a cada imatge, amb un títol petit per a cada grup. */
    group_home_away: boolean;
}

export interface ResultImagesState {
    generating: boolean;
    /** Partits de la jornada amb resultat (els que sortiran a la imatge). */
    results_count: number;
    images: GeneratedImage[];
}

/** Una generació: les imatges que es van crear d'un cop. */
export interface ImageBatch {
    batch_id: string;
    kind: ImageKind;
    matchday: { id: number; display_label: string } | null;
    options: Partial<ImageOptions> | null;
    created_at: string | null;
    created_by: string | null;
    images: GeneratedImage[];
}

export interface BrandingSettings {
    club_name: string;
    primary_color: string;
    primary_dark_color: string;
    has_logo: boolean;
    logo_url: string | null;
}

export interface PublicBranding {
    club_name: string;
    logo_url: string | null;
}

/** Nom i escut del club: no cal haver iniciat la sessió (la pantalla d'accés els mostra). */
export const publicBrandingApi = {
    get: () => api.get<Resource<PublicBranding>>('/public/branding').then((r) => r.data),
};

export const templatesApi = {
    list: () => api.get<Resource<WhatsAppTemplate[]>>('/whatsapp-templates').then((r) => r.data),
    variables: () => api.get<Resource<{ match: string[]; weekend_summary: string[]; line: string[] }>>('/whatsapp-templates/variables').then((r) => r.data),
    create: (input: TemplateInput) => api.post<Resource<WhatsAppTemplate>>('/whatsapp-templates', input).then((r) => r.data),
    update: (id: number, input: TemplateInput) => api.put<Resource<WhatsAppTemplate>>(`/whatsapp-templates/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/whatsapp-templates/${id}`),
};

export const destinationsApi = {
    list: () => api.get<Resource<WhatsAppDestination[]>>('/whatsapp-destinations').then((r) => r.data),
    providers: () => api.get<Resource<ProviderInfo[]>>('/whatsapp-destinations/providers').then((r) => r.data),
    create: (input: DestinationInput) => api.post<Resource<WhatsAppDestination>>('/whatsapp-destinations', input).then((r) => r.data),
    update: (id: number, input: DestinationInput) => api.put<Resource<WhatsAppDestination>>(`/whatsapp-destinations/${id}`, input).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/whatsapp-destinations/${id}`),
};

export const messagesApi = {
    list: (scheduleId: number) => api.get<Resource<WhatsAppMessage[]>>(`/schedules/${scheduleId}/whatsapp-messages`).then((r) => r.data),
    preview: (scheduleId: number) => api.post<Resource<WhatsAppMessage[]>>(`/schedules/${scheduleId}/whatsapp/preview`).then((r) => r.data),
    update: (id: number, body: string) => api.put<Resource<WhatsAppMessage>>(`/whatsapp-messages/${id}`, { body }).then((r) => r.data),
    send: (scheduleId: number, ids: number[]) =>
        api.post<Resource<{ queued: number[]; skipped: number[]; messages: WhatsAppMessage[] }>>(`/schedules/${scheduleId}/whatsapp/send`, { message_ids: ids, confirm: true }).then((r) => r.data),
    retryFailed: (scheduleId: number) => api.post<Resource<{ queued: number[]; messages: WhatsAppMessage[] }>>(`/schedules/${scheduleId}/whatsapp/retry-failed`).then((r) => r.data),
    markSent: (id: number) => api.post<Resource<WhatsAppMessage>>(`/whatsapp-messages/${id}/mark-sent`).then((r) => r.data),
    discard: (id: number) => api.post<Resource<WhatsAppMessage>>(`/whatsapp-messages/${id}/discard`).then((r) => r.data),
};

export const imagesApi = {
    get: (scheduleId: number) => api.get<Resource<ImagesState>>(`/schedules/${scheduleId}/images`).then((r) => r.data),
    generate: (scheduleId: number, options: ImageOptions) => api.post<Resource<ImagesState>>(`/schedules/${scheduleId}/images`, options).then((r) => r.data),
};

export const resultImagesApi = {
    get: (matchdayId: number) => api.get<Resource<ResultImagesState>>(`/matchdays/${matchdayId}/result-images`).then((r) => r.data),
    generate: (matchdayId: number) => api.post<Resource<ResultImagesState>>(`/matchdays/${matchdayId}/result-images`).then((r) => r.data),
};

export const imageHistoryApi = {
    list: (kind: ImageKind | null) => api.get<Resource<ImageBatch[]>>(kind === null ? '/generated-images' : `/generated-images?kind=${kind}`).then((r) => r.data),
    remove: (id: number) => api.delete<void>(`/generated-images/${id}`),
    removeBatch: (batchId: string) => api.delete<void>(`/generated-images/batches/${batchId}`),
};

export const settingsApi = {
    get: () => api.get<Resource<BrandingSettings>>('/settings').then((r) => r.data),
    update: (input: { club_name: string; primary_color: string; primary_dark_color: string }) => api.put<Resource<BrandingSettings>>('/settings', input).then((r) => r.data),
    uploadLogo: (file: File) => {
        const form = new FormData();
        form.append('logo', file);

        return api.post<Resource<BrandingSettings>>('/settings/logo', form).then((r) => r.data);
    },
    deleteLogo: () => api.delete<Resource<BrandingSettings>>('/settings/logo').then((r) => r.data),
};
