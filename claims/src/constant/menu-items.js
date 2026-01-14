import LINKS from "./links";
import { hasPermission, hasAnyPermission, PERMISSIONS } from "@/utils/rbac";

export const getMenuItems = (t) => {
  //Start of Dashboard & Member Services menu section
  let response = [
    {
      title: t("menu.dashboard"),
      icon: "heroicons:adjustments-horizontal-16-solid",
      link: LINKS.DASHBOARD,
    },
    {
      isHeadr: true,
      title: t("menu.member_services"),
    },
    {
      title: 'E-Sign',
      icon: "heroicons:adjustments-horizontal-16-solid",
      link: LINKS.ESIGN,
    }
  ];
  //End of Dashboard & Member Services menu section

  //Start of claim menu section
  let manageMenu = [];
  const itClaim = [...Object.values(PERMISSIONS.ITCLAIM)];
  const tadaClaim = [...Object.values(PERMISSIONS.TADACLAIM)];
  if (hasAnyPermission([...itClaim, ...tadaClaim])) {
    let manageMenuChild = [];
    if (hasAnyPermission([...itClaim])) {
      manageMenuChild = [...manageMenuChild, {
        childtitle: t("menu.it_claim"),
        childlink: LINKS.CLAIM.IT,
      }];
    }
    if (hasAnyPermission([...tadaClaim])) {
      manageMenuChild = [...manageMenuChild, {
        childtitle: t("menu.tada_claims"),
        childlink: LINKS.CLAIM.TADA,
      }];
    }
    manageMenu = [...manageMenu, {
      title: t("menu.claims"),
      icon: "heroicons:clipboard-document-check",
      link: "#",
      child: manageMenuChild
    }];
  }
  response = [...response, ...manageMenu];
  //End of claim menu section

  //Start of cghs and medical claim menu section
  let menuItems = [];
  if (hasPermission(PERMISSIONS.CGHS.MANAGE_CARD)) {
    menuItems.push({
      title: t("menu.cghs_card"),
      icon: "streamline-freehand:meeting-presentation",
      link: LINKS.CGHSCard,
    });
  }

  // Always visible items (for all authenticated users)
  if (hasPermission([PERMISSIONS.CGHSAPPROVE.APPROVE])) {
    menuItems.push({
      title: t("menu.cghs_card_approver"),
      icon: "streamline-freehand:meeting-presentation",
      link: LINKS.CGHSCardApprove,
    });
  }

  // //for medical-claim tab_2_2 defines as medical claim
  // if (hasPermission([PERMISSIONS.CGHS.MANAGE_CARD])) {
  //   menuItems.push({
  //     title: t("menu.medical_claim"),
  //     icon: "streamline-freehand:meeting-presentation",
  //     link: LINKS.medicalClaimDetails,
  //   });
  // }

  response = [...response, ...menuItems];
  //End of cghs and medical claim menu section

  const reports = [{
    title: t("reports"),
    icon: "heroicons:clipboard-document-check",
    link: "#",
    child: [
      {
        childtitle: t("menu.it_claim_reports"),
        childlink: LINKS.REPORT.IT,
      },
      // {
      //   childtitle: t("menu.medical_reports"),
      //   childlink: LINKS.REPORT.MEDICAL,
      // },
    ],
  }];

  response = [...response, ...reports];
  return response;
};
