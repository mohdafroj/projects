// constant/menu-items.js - Simplified menu with dynamic permissions
import links from "./links";
import { hasAnyPermission, hasPermission, PERMISSIONS } from "@/utils/rbac";


export const getMenuItems = (t) => {
  let menuItems = [];

  // Attendance Menu - only show if user has any attendance permission
  const attendanceChildren = [];

  if (hasAnyPermission([PERMISSIONS.ATTENDANCE.DASHBOARD])) { // General attendance access
    attendanceChildren.push({
      childtitle: t("menu.dashboard"),
      childlink: links.tab_1.tab_1_4,
    });
  }

  if (hasPermission([PERMISSIONS.ATTENDANCE.VIEW_REGULARIZATION])) {
    attendanceChildren.push({
      childtitle: t("menu.tab_1") + ' ' + t("menu.tab_1_1"),
      childlink: links.tab_1.tab_1_1,
    });
  }

  if (hasPermission([PERMISSIONS.ATTENDANCE.VIEW_HISTORY])) {
    attendanceChildren.push({
      childtitle: t("menu.tab_1") + ' ' + t("menu.tab_1_2"),
      childlink: links.tab_1.tab_1_2,
    });
  }

  if (hasPermission([PERMISSIONS.ATTENDANCE.VIEW_LEAVE_REPORT])) {
    attendanceChildren.push({
      childtitle: t("menu.tab_1_3"),
      childlink: links.tab_1.tab_1_3,
    });
  }
  // if (hasPermission([])) {
  //   attendanceChildren.push({
  //     childtitle: t("menu.tab_1_5"),
  //     childlink: links.tab_1.tab_1_5,
  //   });
  // }

  if (hasAnyPermission([PERMISSIONS.ATTENDANCE.VIEW_HISTORY])) {
    //Specimen Signature
    attendanceChildren.push({
      childtitle: t("menu.tab_1_7"),
      childlink: links.tab_1.tab_1_7,
    });

    //Reset Attendance 
    attendanceChildren.push({
      childtitle: t("menu.tab_1_8"),
      childlink: links.tab_1.tab_1_8,
    });

    //Schedule attendance signature alert
    attendanceChildren.push({
      childtitle: t("menu.tab_1_9"),
      childlink: links.tab_1.tab_1_9,
    });
  }

  if (hasAnyPermission([PERMISSIONS.ATTENDANCE.LOBBY_OFFICE])) {
    //Staff List
    attendanceChildren.push({
      childtitle: t("menu.tab_1_10"),
      childlink: links.tab_1.tab_1_10,
    });
  }

  // Add attendance menu if user has access to any attendance feature
  if (attendanceChildren.length > 0) {
    menuItems.push({
      title: t("menu.tab_1"),
      icon: "heroicons:clipboard-document-check",
      link: "#",
      child: attendanceChildren
    });
  }

  // User Management Menu - add permissions when needed
  const userMgmtChildren = [];

  if (hasPermission([])) { // Add specific permission when needed
    userMgmtChildren.push({
      childtitle: t("menu.tab_3_1"),
      childlink: links.tab_3.tab_3_1,
    });
  }

  if (hasPermission([])) { // Add specific permission when needed
    userMgmtChildren.push({
      childtitle: t("menu.tab_3_2"),
      childlink: links.tab_3.tab_3_2,
    });
  }

  // if (userMgmtChildren.length > 0) {
  //   menuItems.push({
  //     title: t("menu.tab_3"),
  //     icon: "solar:user-id-linear",
  //     link: "#",
  //     child: userMgmtChildren
  //   });
  // }

  // Always visible items (for all authenticated users)

  //Start of session view section
  // if (hasPermission(PERMISSIONS.SESSION.VIEW)) {
  //   menuItems.push({
  //     title: t("menu.session"),
  //     icon: "streamline-cyber:video-meeting-group",
  //     link: links.tab_4.tab_4_1,
  //   });
  // }
  //End of session view section

  //Start of committee meeting view section
  // if (hasPermission(PERMISSIONS.COMMITTEE.VIEW_MEETING)) {
  //   menuItems.push({
  //     title: t("menu.CommitteeMeeting"),
  //     icon: "streamline-freehand:meeting-presentation",
  //     link: links.CommitteeMeeting,
  //   });
  // }
  //End of committee meeting view section

  return menuItems;
};
