import auth from "@/middleware/auth";
import guest from "@/middleware/guest";
import { AccessDenied, ThankYou, NotFound, Unauthorized } from "@sds/oneui-layout";
import { createPermissionMiddleware, PERMISSIONS } from "@/utils/rbac";
import links from "@/constant/links";
import i18n from "./../i18n";
const t = i18n.global.t;
const routes = [
  {
    path: links.login,
    name: "Login",
    component: () => import("@/views/auth/login/index.vue"),
    meta: { middleware: [guest] },
  },
  {
    path: links.base_path,
    name: "Layout",
    redirect: links.dashboard,
    component: () => import("@/Layout/index.vue"),
    meta: { middleware: [auth] },
    children: [
      {
        path: links.dashboard,
        name: "Dashboard",
        component: () => import("@/views/dashboard/index.vue"),
        meta: {
          title: "menu.dashboard",
          groupParent: "Dashboard",
          permissions: [] // Accessible to all authenticated users
        },
      },
      // Attendance Routes with actual permissions
      {
        path: links.tab_1.tab_1_4,
        name: "attendance",
        redirect: links.tab_1.tab_1_4,
        meta: {
          title: ["menu.tab_1"],
          middleware: [auth, createPermissionMiddleware([
            PERMISSIONS.ATTENDANCE.DASHBOARD,
            PERMISSIONS.ATTENDANCE.VIEW_REGULARIZATION,
            PERMISSIONS.ATTENDANCE.VIEW_HISTORY,
            PERMISSIONS.ATTENDANCE.VIEW_LEAVE_REPORT,
            PERMISSIONS.ATTENDANCE.DOWNLOAD_ATTENDANCE_FINAL_REPORT
          ], { requireAll: false })], // User needs ANY attendance permission
        },
        children: [
          {
            path: "",
            name: "attendance1",
            component: () => import("@/views/attendances/index.vue"),
            meta: {
              title: ["menu.dashboard"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.DASHBOARD
              ])]
            }
          },
          {
            path: links.tab_1.tab_1_7,
            name: "SpecimenSignature",
            component: () => import("@/views/attendances/SpecimenSignature.vue"),
            meta: {
              title: ["menu.tab_1_7"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.VIEW_HISTORY
              ], { requireAll: false })]
            }
          },
          {
            path: links.tab_1.tab_1_8,
            name: "ResetAttendance",
            component: () => import("@/views/attendances/ResetAttendance.vue"),
            meta: {
              title: ["menu.tab_1_8"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.VIEW_HISTORY
              ], { requireAll: false })]
            }
          },
          {
            path: links.tab_1.tab_1_9,
            name: "ScheduleReminder",
            component: () => import("@/views/attendances/ScheduleReminder.vue"),
            meta: {
              title: ["menu.tab_1_9"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.VIEW_HISTORY
              ], { requireAll: false })]
            }
          },
          {
            path: links.tab_1.tab_1_10,
            name: "StaffList",
            component: () => import("@/views/attendances/StaffList.vue"),
            meta: {
              title: ["menu.tab_1_10"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.LOBBY_OFFICE
              ], { requireAll: false })]
            }
          },
          {
            path: links.tab_1.tab_1_2 + '/absent-members',
            name: "AbsentMember",
            component: () => import("@/views/attendances/history/list/AbsentMember.vue"),
            meta: {
              title: 'Long Absentee Report',
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.VIEW_HISTORY
              ], { requireAll: false })]
            }
          },
          {
            path: links.tab_1.tab_1_2 + '/daily-report',
            name: "DailyReport",
            component: () => import("@/views/attendances/history/list/DailyReport.vue"),
            meta: {
              title: 'Daily Report',
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.VIEW_HISTORY
              ], { requireAll: false })]
            }
          },
          {
            path: links.tab_1.tab_1_2 + '/signed-report',
            name: "SignedReport",
            component: () => import("@/views/attendances/history/list/SignedReport.vue"),
            meta: {
              title: 'Signed Report',
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.ATTENDANCE.VIEW_HISTORY
              ], { requireAll: false })]
            }
          },
          {
            path: links.tab_1.tab_1_6,
            name: "PaperSigned",
            component: () => import("@/views/attendances/PaperSigned.vue"),
            meta: {
              title: ["menu.tab_1_6"],
            }
          },
          {
            path: links.tab_1.tab_1_1,
            name: "Attendance_Regularization",
            component: () => import("@/views/attendances/regulization/list/index.vue"),
            meta: {
              title: ["menu.tab_1", "menu.tab_1_1"],
              middleware: [auth, createPermissionMiddleware([PERMISSIONS.ATTENDANCE.VIEW_REGULARIZATION])]
            }
          },
          {
            path: links.tab_1.tab_1_1 + '/approval-process/:id',
            name: "ANS_Approval",
            component: () => import("@/views/attendances/regulization/list/Approval.vue"),
            meta: {
              title: ["menu.tab_1", "Approval Process"],
              middleware: [auth, createPermissionMiddleware(Object.values(PERMISSIONS.APPROVAL.ANS), { requireAll: false })]
            },
          },
          {
            path: links.tab_1.tab_1_2,
            name: "Attendance_History",
            component: () => import("@/views/attendances/history/list/index.vue"),
            meta: {
              title: ["menu.tab_1", "menu.tab_1_2"],
              middleware: [auth, createPermissionMiddleware([PERMISSIONS.ATTENDANCE.VIEW_HISTORY])],
              permissions: [PERMISSIONS.ATTENDANCE.VIEW_HISTORY]
            },
          },
          {
            path: links.tab_1.tab_1_3,
            name: "Leave_Request",
            component: () => import("@/views/attendances/leave/list/index.vue"),
            meta: {
              title: ["menu.tab_1_3"],
              middleware: [auth, createPermissionMiddleware([PERMISSIONS.ATTENDANCE.VIEW_LEAVE_REPORT])]
            },
          },
          {
            path: links.tab_1.tab_1_3 + '/approval-process/:id',
            name: "Leave_Request_Approval",
            component: () => import("@/views/attendances/leave/list/Approval.vue"),
            meta: {
              title: ["menu.tab_1_3", "Approval Process"],
              middleware: [auth, createPermissionMiddleware(Object.values(PERMISSIONS.APPROVAL.LEAVE), { requireAll: false })]
            },
          },
          {
            path: links.tab_1.tab_1_5,
            name: "attendance-report",
            component: () => import("@/views/attendances/report/list/attendences-report.vue"),
            meta: {
              title: ["menu.tab_1_5"],
              middleware: [auth, createPermissionMiddleware([PERMISSIONS.ATTENDANCE.DOWNLOAD_ATTENDANCE_FINAL_REPORT])],
              permissions: [PERMISSIONS.ATTENDANCE.DOWNLOAD_ATTENDANCE_FINAL_REPORT]
            },
          },
          {
            path: links.committee,
            name: "CommitteeAttendance",
            component: () => import("@/views/attendances/committee.vue"),
            meta: {
              title: ["menu.dashboard"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.COMMITTEE.VIEW_MEETING
              ], { requireAll: false })],
              groupParent: t("menu.tab_1"),
              permissions: [
                PERMISSIONS.COMMITTEE.VIEW_MEETING
              ]
            },
          },
        ]
      },
      {
        path: links.tab_3.tab_3_1,
        name: "new-user",
        component: () => import("@/views/new-user/index.vue"),
        meta: {
          middleware: [auth], // Add specific permissions: createPermissionMiddleware(["PERM:CODE:HERE"])
          permissions: []
        },
      },
      {
        path: links.tab_3.tab_3_2,
        name: "register-tab",
        component: () => import("@/views/register-tab/index.vue"),
        meta: {
          middleware: [auth],
          permissions: []
        },
      },

      // Session routes with actual permissions
      {
        path: links.tab_4.tab_4_1,
        redirect: links.tab_4.tab_4_1,
        name: "session",
        meta: {
          title: ["menu.session"],
          middleware: [auth],
        },
        children: [
          {
            path: "",
            name: "sessionList",
            component: () => import("@/views/session/index.vue"),
            meta: {
              middleware: [auth, createPermissionMiddleware([PERMISSIONS.SESSION.VIEW])],
              permissions: [PERMISSIONS.SESSION.VIEW]
            },
          },
          {
            path: links.tab_4.tab_4_2,
            name: "Create-Session",
            component: () => import("@/views/session/CreateSession.vue"),
            meta: {
              title: ["menu.create_session"],
              middleware: [auth, createPermissionMiddleware([PERMISSIONS.SESSION.CREATE])],
              permissions: [PERMISSIONS.SESSION.CREATE]
            },
          },
          {
            path: links.tab_4.tab_4_3,
            name: "Manage-Sitting",
            component: () => import("@/views/session/ManageSitting.vue"),
            beforeEnter: (to, from, next) => {
              const id = to.query.id;
              if (id === undefined) {
                // No id param, allow navigation
                next();
              } else if (/^\d+$/.test(id)) {
                // id is integer, allow navigation
                next();
              } else {
                // id param is invalid, redirect to access denied page
                next({ name: 'Access_Denied' });
              }
            },
            meta: {
              title: ["menu.manage_sitting"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.SESSION.CREATE,
                PERMISSIONS.SESSION.EDIT
              ], { requireAll: false })], // User needs create OR edit permission
              permissions: [PERMISSIONS.SESSION.CREATE, PERMISSIONS.SESSION.EDIT]
            },
          },
        ]
      },
      // Committee Meeting routes with actual permissions
      {
        path: links.CommitteeMeeting,
        redirect: links.CommitteeMeeting,
        name: "CommitteeMeeting",
        meta: {
          title: ["menu.committeMeetingList"],
          middleware: [auth]
        },
        children: [
          {
            path: "",
            name: "MeetingList",
            component: () => import("@/views/CommitteeMeeting/list/index.vue"),
            meta: {
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.COMMITTEE.CREATE_MEETING,
                PERMISSIONS.COMMITTEE.EDIT_MEETING,
                PERMISSIONS.COMMITTEE.VIEW_MEETING
              ], { requireAll: false })], // User needs ANY committee permission
              permissions: [
                PERMISSIONS.COMMITTEE.CREATE_MEETING,
                PERMISSIONS.COMMITTEE.EDIT_MEETING,
                PERMISSIONS.COMMITTEE.VIEW_MEETING
              ]
            },
          },
          {
            path: "/committee-meeting/AddMeeting",
            name: "AddMeeting",
            component: () => import("@/views/CommitteeMeeting/AddMeeting.vue"),
            meta: {
              title: ["menu.add_meeting"],

              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.COMMITTEE.CREATE_MEETING,
                PERMISSIONS.COMMITTEE.EDIT_MEETING
              ], { requireAll: false })],
              permissions: [PERMISSIONS.COMMITTEE.CREATE_MEETING, PERMISSIONS.COMMITTEE.EDIT_MEETING]
            },
          },
          {
            path: "/committee-meeting/EditMeeting/:id",
            name: "EditMeeting",
            component: () => import("@/views/CommitteeMeeting/AddMeeting.vue"),
            beforeEnter: (to, from, next) => {
              const id = to.params.id;
              if (id === undefined) {
                // No id param, allow navigation
                next();
              } else if (/^\d+$/.test(id)) {
                // id is integer, allow navigation
                next();
              } else {
                // id param is invalid, redirect to access denied page
                next({ name: 'Access_Denied' });
              }
            },
            meta: {
              title: ["menu.edit_meeting"],
              middleware: [auth, createPermissionMiddleware([
                PERMISSIONS.COMMITTEE.CREATE_MEETING,
                PERMISSIONS.COMMITTEE.EDIT_MEETING
              ], { requireAll: false })],
              permissions: [PERMISSIONS.COMMITTEE.CREATE_MEETING, PERMISSIONS.COMMITTEE.EDIT_MEETING]
            },
          },
        ]
      },
      {
        path: links.access_denied,
        name: "Access_Denied",
        component: () => import("@/views/access_denied.vue"),
        meta: { title: "permission_denied_title", hide: true, permissions: [] },
      },
      // {
      //   path: "AddMeeting/:id?",
      //   name: "AddMeeting",
      //   component: () => import("@/views/CommitteeMeeting/AddMeeting.vue"),
      //   meta: {
      //     middleware: [auth, createPermissionMiddleware([
      //       PERMISSIONS.COMMITTEE.CREATE_MEETING,
      //       PERMISSIONS.COMMITTEE.EDIT_MEETING
      //     ], { requireAll: false })], // User needs create OR edit permission
      //     groupParent: "CommitteeMeeting",
      //     permissions: [PERMISSIONS.COMMITTEE.CREATE_MEETING, PERMISSIONS.COMMITTEE.EDIT_MEETING]
      //   },
      // },
      {
        path: links.access_denied,
        name: "Access_Denied",
        component: AccessDenied,
        meta: { title: "permission_denied_title", hide: true },
      },
    ],
  },
  // Access Denied
  {
    path: links.unauthorized,
    name: "Unauthorized",
    component: Unauthorized,
    props: { title: t('pad_title'), message: t('pad_message') },
    meta: { title: "pad_title", hide: true },
  },
  {
    path: links.thank_you,
    name: "Thank_You",
    component: ThankYou,
    meta: { title: "thankyou", middleware: [guest] },
  },
  {
    path: "/:catchAll(.*)",
    name: "404",
    component: NotFound,
  },

];


export default routes;
