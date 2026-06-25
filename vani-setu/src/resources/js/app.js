import './bootstrap';
import { initAppRouterWorkspace } from './app-router-workspace';
import { initReporterWorkspace } from './reporter-workspace';
import { initTranslatorWorkspace } from './translator-workspace';
import { initReviewerWorkspace } from './reviewer-workspace';
import { initSgWorkspace } from './sg-workspace';
import { initDirectorWorkspace } from './director-workspace';
import { initSynopsisWorkspace } from './synopsis-workspace';

const roleWorkspace = document.getElementById('role-workspace');

if (roleWorkspace?.dataset.workspace === 'router') {
    initAppRouterWorkspace(roleWorkspace);
}

if (roleWorkspace?.dataset.workspace === 'reporter') {
    initReporterWorkspace(roleWorkspace);
}

if (roleWorkspace?.dataset.workspace === 'translator') {
    initTranslatorWorkspace(roleWorkspace);
}

if (roleWorkspace?.dataset.workspace === 'reviewer') {
    initReviewerWorkspace(roleWorkspace);
}

if (roleWorkspace?.dataset.workspace === 'sg') {
    initSgWorkspace(roleWorkspace);
}

if (roleWorkspace?.dataset.workspace === 'director') {
    initDirectorWorkspace(roleWorkspace);
}

if (roleWorkspace?.dataset.workspace === 'synopsis') {
    initSynopsisWorkspace(roleWorkspace);
}
