import { Routes } from '@angular/router';
import { Websocket } from './pages/websocket/websocket';
import { Home } from './pages/home/home';
import { MainLayout } from './layout/main-layout/main-layout';

export const routes: Routes = [
    {
        path: '',
        component: MainLayout,
        children: [
            {
                path: 'home',
                component: Home
            },
        ]
    },
    {
        path: 'websocket',
        component: Websocket,
    }
];
