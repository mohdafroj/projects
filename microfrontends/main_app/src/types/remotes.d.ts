declare module 'dashboard/Dashboard' {
    import { ComponentType } from 'react';
    interface DashboardProps {
        username?: string;
    }
    const Dashboard: ComponentType<DashboardProps>;
    export default Dashboard;
}

declare module 'settings/Settings' {
    import { ComponentType } from 'react';
    const Settings: ComponentType;
    export default Settings;
}
