import http from '@/api/http';

export type DashboardTemplate = 'midnight' | 'ocean' | 'ember';

export default async (dashboardTemplate: DashboardTemplate): Promise<void> => {
    await http.put('/api/client/account/profile', {
        dashboard_template: dashboardTemplate,
    });
};
