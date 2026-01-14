import auth from "@/middleware/auth";
import guest from "@/middleware/guest";
import { AccessDenied, ThankYou, NotFound, Unauthorized } from "@sds/oneui-layout";
import { createPermissionMiddleware, PERMISSIONS } from "@/utils/rbac";
import LINKS from "@/constant/links";
import i18n from "./../i18n";
const t = i18n.global.t;

const routes = [
  {
    path: LINKS.LOGIN,
    name: "Login",
    component: () => import("@/views/auth/login/index.vue"),
    meta: { middleware: [guest] },
  },
  {
    path: LINKS.BASE_PATH,
    name: "Layout",
    redirect: LINKS.DASHBOARD,
    component: () => import("@/Layout/index.vue"),
    meta: { middleware: [auth] },
    children: [
      {
        path: LINKS.DASHBOARD,
        name: "Dashboard",
        component: () => import("@/views/dashboard/index.vue"),
        meta: {
          title: "menu.dashboard",
          middleware: [auth]
        },
      },
      {
        path: "/notification",
        name: "notifications",
        component: () => import("@/views/notifications.vue"),
        meta: {
          title: "menu.notifications",
        },
      },
      {
        path: LINKS.CLAIM.ROOT,
        redirect: LINKS.CLAIM.ROOT,
        name: "Claims",
        meta: {
          middleware: [auth],
          title: "menu.claims"
        },
        children: [
          {
            path: "",
            redirect: LINKS.CLAIM.IT,
            name: "ClaimName",
          },
          {
            path: LINKS.CLAIM.IT,
            redirect: LINKS.CLAIM.IT,
            name: "ITClaim",
            meta: {
              title: ["menu.it_claim"],
              middleware: [auth]
            },
            children: [
              {
                path: "",
                name: "ITClaimList",
                component: () => import("@/views/claims/it-equipment/list/index.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.ITCLAIM.APPROVE, PERMISSIONS.ITCLAIM.REVIEW, PERMISSIONS.ITCLAIM.INITIATE], { requireAll: false })]
                }
              },



              {
                path: LINKS.CLAIM.IT + '/financial-entitlement',
                name: "ITFinancialEntitlement",
                component: () => import("@/views/claims/it-equipment/financial-entitlement.vue"),
                meta: {
                  title: ["claims.it_budget"],
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.ITCLAIM.APPROVE, PERMISSIONS.ITCLAIM.REVIEW, PERMISSIONS.ITCLAIM.INITIATE], { requireAll: false })]
                }
              },

              {
                path: LINKS.CLAIM.IT + '/submit-new-claim',
                name: "ITClaimAdd",
                component: () => import("@/views/claims/it-equipment/submit-new-claim/index.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.ITCLAIM.INITIATE], { requireAll: false })],
                  title: ["new_claim"]
                }
              },

              {
                path: LINKS.CLAIM.IT + '/submit-new-claim/invoice',
                name: "ITClaimNewSubmitInvoice",
                component: () => import("@/views/claims/it-equipment/submit-new-claim/invoice.vue"),
                meta: {
                  middleware: [auth]
                }
              },

              {
                path: LINKS.CLAIM.IT + '/submit-new-claim/submitEsign',
                name: "ITClaimSubmitEsign",
                component: () => import("@/views/claims/it-equipment/submit-new-claim/submitEsign.vue"),
                meta: {
                  middleware: [auth]
                }
              },
              {
                path: LINKS.CLAIM.IT + '/detail/:id(\\d+)',
                name: "ITClaimDetail",
                component: () => import("@/views/claims/it-equipment/detail/index.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.ITCLAIM.APPROVE, PERMISSIONS.ITCLAIM.REVIEW, PERMISSIONS.ITCLAIM.INITIATE], { requireAll: false })],
                  title: ["detail"]
                }
              },
              {
                path: LINKS.CLAIM.IT + '/forwardedclaim',
                name: "ITClaimDetailForwardedSummary",
                component: () => import("@/views/claims/it-equipment/detail/ForwardedSummary.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.ITCLAIM.APPROVE, PERMISSIONS.ITCLAIM.REVIEW, PERMISSIONS.ITCLAIM.INITIATE], { requireAll: false })],
                  title: ["forwared_claims"]
                }
              },
              {
                path: LINKS.CLAIM.IT + '/history/:id(\\d+)',
                name: "ITClaimStatusHistory",
                component: () => import("@/views/claims/it-equipment/detail/StatusHistory.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.ITCLAIM.APPROVE, PERMISSIONS.ITCLAIM.REVIEW, PERMISSIONS.ITCLAIM.INITIATE], { requireAll: false })],
                  title: ["file_history"]
                }
              },
            ]
          },

          // TA-DA Claims routes

          {
            path: LINKS.CLAIM.TADA,
            redirect: LINKS.CLAIM.TADA,
            name: "TADAClaim",
            meta: {
              title: ["menu.tada_claims"],
              middleware: [auth]
            },
            children: [
              {
                path: LINKS.CLAIM.TADA,

                name: "TADAClaims",
                component: () => import("@/views/claims/tada-claims/tada-list.vue"),
                meta: {

                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.TADACLAIM.APPROVE, PERMISSIONS.TADACLAIM.REVIEW, PERMISSIONS.TADACLAIM.INITIATE], { requireAll: false })]
                }
              },


              {
                path: LINKS.CLAIM.TADA,

                name: "TADASelectClaims",
                component: () => import("@/views/claims/tada-claims/index.vue"),
                meta: {
                  title: ["Choose for Add New TA/DA Claims"],
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.TADACLAIM.INITIATE], { requireAll: false })]
                }
              },

              {
                path: LINKS.CLAIM.TADA + '/add-tada-by-pnr',
                name: "addFlightByPNR",
                component: () => import("@/views/claims/tada-claims/by-pnr.vue"),
                meta: {
                  middleware: [auth],
                  title: ['Add TADA claim by PNR']
                }
              },


              {
                path: LINKS.CLAIM.TADA + '/add-tada-by-road',
                name: "addRoadJourney",
                component: () => import("@/views/claims/tada-claims/by-road.vue"),
                meta: {
                  middleware: [auth],
                  title: ['Add TADA by Road']
                }
              },




              {
                path: LINKS.CLAIM.TADA + '/add-tada-claim',
                name: "TADAClaimsAdd",
                component: () => import("@/views/claims/tada-claims/add-tada-claim.vue"),
                meta: {
                  title: ["Add New TA/DA Claim"],
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.TADACLAIM.APPROVE, PERMISSIONS.TADACLAIM.REVIEW, PERMISSIONS.TADACLAIM.INITIATE], { requireAll: false })]
                }
              },


              {
                path: LINKS.CLAIM.TADA + '/submit-new-claim/submitEsign',
                name: "SubmitEsign",
                component: () => import("@/views/claims/tada-claims/submitEsign.vue"),
                meta: {
                  middleware: [auth]
                }
              },
              {
                path: LINKS.CLAIM.TADA + '/detail/:id(\\d+)',
                name: "TADAClaimDetail",
                component: () => import("@/views/claims/tada-claims/detail/index.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.TADACLAIM.APPROVE, PERMISSIONS.TADACLAIM.REVIEW, PERMISSIONS.TADACLAIM.INITIATE], { requireAll: false })],
                  title: ["detail"]
                }
              },
              {
                path: LINKS.CLAIM.TADA + '/forwardedclaim',
                name: "TADAClaimDetailForwardedSummary",
                component: () => import("@/views/claims/tada-claims/detail/ForwardedSummary.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.TADACLAIM.APPROVE, PERMISSIONS.TADACLAIM.REVIEW, PERMISSIONS.TADACLAIM.INITIATE], { requireAll: false })],
                  title: ["forwared_claims"]
                }
              },
              {
                path: LINKS.CLAIM.TADA + '/history/:id(\\d+)',
                name: "TADAClaimStatusHistory",
                component: () => import("@/views/claims/tada-claims/detail/StatusHistory.vue"),
                meta: {
                  middleware: [auth, createPermissionMiddleware([PERMISSIONS.TADACLAIM.APPROVE, PERMISSIONS.TADACLAIM.REVIEW, PERMISSIONS.TADACLAIM.INITIATE], { requireAll: false })],
                  title: ["file_history"]
                }
              },
            ]

          }

          // END TA-DA Claims routes

        ]
      },

      // for approval module
      {
        path: 'approval/action',
        name: 'notice-approval',
        component: () => import('@/views/approval/index.vue'),
        meta: {
          groupParent: 'rssms',
        },
      },
      {
        path: 'approval/progress',
        name: 'notice-approval-progress',
        component: () => import('@/views/approval/index.vue'),
        meta: {
          groupParent: 'rssms',
        },
      },
      {
        path: 'notice-action/:id',
        name: 'notice-action',
        component: () => import('@/views/approval/view.vue'),
        meta: {
          groupParent: 'rssms',
        },
      },

      // end approval module



      //----------RSS Esign ----------------------------------//
      {
        path: '/form-list',
        name: "eSignForm",
        component: () => import("@/views/esign/formlist.vue"),
        meta: {
          title: "menu.dashboard",
          middleware: [auth]
        },
      },
      {
        path: '/form-list/:id',
        name: "eSignFormDetails",
        component: () => import("@/views/esign/formListId.vue"),
        meta: {
          title: "menu.dashboard",
          middleware: [auth]
        },
      },

      //-----------------CGHS Rss Router---------------------//
      {
        path: LINKS.CGHSCard,
        name: "RecommendRequest",
        component: () => import("@/views/ma-card-status/index.vue"),
        meta: {
          groupParent: t("bredcrum.home"),
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHS.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHS.MANAGE_CARD]
        },
      },
      {
        path: LINKS.CGHSCardApprove,
        name: "AddCardRequests",
        component: () => import("@/views/ma-card-status-approve/index.vue"),
        meta: {
          groupParent: t("bredcrum.home"),
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHSAPPROVE.APPROVE])],
          permissions: [PERMISSIONS.CGHSAPPROVE.APPROVE]
        },
      },
      {
        path: "/get-member-preview-details/:id",
        name: "get-member-preview-details",
        component: () => import("@/views/ma-card-status-approve/PreviewDetails.vue"),
        meta: {
          groupParent: "Home",
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHSAPPROVE.APPROVE])],
          permissions: [PERMISSIONS.CGHSAPPROVE.APPROVE]
        },
      },
      //-----------------CGHS Rss Router---------------------//
      //=================add card web start=================//
      {
        path: LINKS.CGHSAddNewCard,
        name: "AddNewCardRequest",
        component: () => import("@/views/cghs-add-card/cghsCardStep1.vue"),
        meta: {
          groupParent: t("bredcrum.home"),
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHS.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHS.MANAGE_CARD]
        },
      },
      {
        path: LINKS.CGHSAddNewCardOfficeDt,
        name: "AddNewCardRequestOfficeDt",
        component: () => import("@/views/cghs-add-card/cghsCardStep2.vue"),
        meta: {
          groupParent: t("bredcrum.home"),
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHS.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHS.MANAGE_CARD]
        },
      },
      {
        path: LINKS.CGHSAddNewCardDesidenceAdd,
        name: "AddNewCardRequestResidenceAdd",
        component: () => import("@/views/cghs-add-card/cghsCardStep3.vue"),
        meta: {
          groupParent: t("bredcrum.home"),
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHS.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHS.MANAGE_CARD]
        },
      },

      {
        path: LINKS.CGHSAddNewCardFamilyInfo,
        name: "AddNewCardRequestFamilyInfo",
        component: () => import("@/views/cghs-add-card/cghsCardStep4.vue"),
        meta: {
          groupParent: t("bredcrum.home"),
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHS.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHS.MANAGE_CARD]
        },
      },
      {
        path: LINKS.CGHSAddNewCardThankyou,
        name: "thankyoumember",
        component: () => import("@/views/cghs-add-card/thankyou.vue"),
        meta: {
          groupParent: t("bredcrum.home"),
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHS.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHS.MANAGE_CARD]
        },
      },
      //=================add card web end=================//    

      //page for testing purpose
      {
        path: "/test",
        name: "test",
        component: () => import("@/views/test/index.vue"),
        meta: {
          groupParent: "ma-card-status",
        },
      },
      //for testing porpose

      //---------- medical claim routs starts here ----------//
      //list page
      {
        path: "/medical-claim",
        name: "medical-claim",
        component: () => import("@/views/medical/list/index.vue"),
        meta: {
          title: ["menu.tab_2_2"],
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHSAPPROVE.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHSAPPROVE.APPROVE]
        },
      },
      //details page
      {
        path: "/medical-claim-detail/:id",
        name: "medical-claim-detail",
        component: () => import("@/views/medical/detail/index.vue"),
        meta: {
          groupParent: "medical-claim",
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHSAPPROVE.MANAGE_CARD, PERMISSIONS.CGHS.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHSAPPROVE.APPROVE, PERMISSIONS.CGHS.MANAGE_CARD]
        },
      },

      //approver page
      {
        path: "/medical-claim-approver/:id",
        name: "medical-claim-approver",
        component: () => import("@/views/medical/detail/remark.vue"),
        meta: {
          groupParent: "medical-claim",
          middleware: [auth, createPermissionMiddleware([PERMISSIONS.CGHSAPPROVE.MANAGE_CARD])],
          permissions: [PERMISSIONS.CGHSAPPROVE.APPROVE]
        },
      },
      //---------- medical claim routs ends here ----------//


      //Start of claim report routes
      {
        path: LINKS.REPORT.IT,
        redirect: LINKS.REPORT.IT,
        name: "ITClaimReport",
        meta: {
          title: "reports",
        },
        children: [
          {
            path: "",
            name: "ITClaimReport1",
            component: () => import("@/views/reports/MemberClaimReports.vue"),
            meta: {
              title: "menu.it_claim_reports",
            }
          },
          {
            path: "claim-details/:id",
            name: "ITClaimReportDetails",
            component: () => import("@/views/reports/ClaimDetailReport.vue"),
            meta: {
              title: "menu.it_claim_reports",
            }
          },
          {
            path: LINKS.REPORT.MEDICAL,
            name: "MedicalClaimReport",
            component: () => import("@/views/reports/MedicalClaimReports.vue"),
            meta: {
              title: "menu.medical_reports",
            },
          },
        ]
      },
      //End of claim report routes
      //Start of Access Denied route
      {
        path: LINKS.ACCESS_DENIED,
        name: "Access_Denied",
        component: AccessDenied,
        props: { title: t('pad_title'), message: t('pad_message') },
        meta: { title: "pad_title", hide: true },
      },
      //End of Access Denied route
    ],
  },
  {
    path: LINKS.UNAUTHORIZED,
    name: "Unauthorized",
    component: Unauthorized,
    props: { title: t('pad_title'), message: t('pad_message') },
    meta: { title: "pad_title", hide: true },
  },
  // Error routes
  {
    path: LINKS.THANK_YOU,
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