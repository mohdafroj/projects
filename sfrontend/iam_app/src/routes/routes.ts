// src/routes/routes.ts

import type { ReactNode } from "react";

const IAM_BASE_PATH = process.env.IAM_BASE_PATH || "";

export interface AppRoute {
    name: string;
    path: string;
    element?: ReactNode;
    meta?: {
        title: string;
        groupParent?: string;
        permissions?: string[];
    };
}

export const routes: AppRoute[] = [
    {
        name: "Dashboard",
        path: "/",
        meta: {
            title: "Dashboard",
            groupParent: "Dashboard",
            permissions: [],
        },
    },
    {
        name: "Register",
        path: "/register",
        meta: {
            title: "Register",
        },
    },
    {
        name: "Login",
        path: "/login",
        meta: {
            title: "Login",
        },
    },
];

export const getRoute = (name: string) => routes.find((r) => r.name === name);
export const getRoutePath = (name: string) => routes.find((r) => r.name === name)?.path;
export const getRouteElement = (name: string) => routes.find((r) => r.name === name)?.element;
export const getRouteMeta = (name: string) => routes.find((r) => r.name === name)?.meta;

export const LINKS = {
    IAM_BASE_PATH: IAM_BASE_PATH,
    DASHBOARD: {
        path: IAM_BASE_PATH + '/',
        name: 'Dashboard',
        meta: {
            title: 'Dashboard',
            groupParent: 'Dashboard',
            permissions: [],
        },
    },
    REGISTER: {
        path: IAM_BASE_PATH + '/register',
        name: 'Register',
        meta: {
            title: 'Register',
        },
    },
    LOGIN: {
        path: IAM_BASE_PATH + '/login',
        name: 'Login',
        meta: {
            title: 'Login',
        },
    },
}