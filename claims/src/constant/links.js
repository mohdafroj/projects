const DS = '/';
const CLAIM = DS + 'claims';
const REPORT = DS + 'reports';

export default {
    BASE_PATH: "",
    DASHBOARD: DS + "dashboard",
    LOGIN: DS + "login",
    ACCESS_DENIED: DS + "access-denied",
    THANK_YOU: DS + "thankyou",
    UNAUTHORIZED: DS + "unauthorized",
    ESIGN: DS + 'form-list',
    CLAIM: {
        ROOT: CLAIM,
        IT: CLAIM + DS + "it-equipment",
        TADA: CLAIM + DS + "tada-claims"
    },
    //------------cghs rss ends -----------//
    CGHSCard: DS + "cghs-card-request" + DS,
    CGHSCardApprove: DS + "cghs-card-status",
    //------------cghs rss ends -----------//

    //------------add card member cghs web starts -----------//
    CGHSAddNewCard: DS + "add-cghs-card",
    CGHSAddNewCardOfficeDt: DS + "add-cghs-card-office-dt",
    CGHSAddNewCardDesidenceAdd: DS + "add-cghs-card-residence-add",
    CGHSAddNewCardFamilyInfo: DS + "add-cghs-card-family-info",
    CGHSAddNewCardThankyou: DS + "thankyou-member",
    //------------add card member cghs web end -----------//
    medicalClaimDetails: DS + "medical-claim" + DS,
    REPORT: {
        IT: REPORT + DS + "it-equipment-claim-report",
        MEDICAL: REPORT + DS + "medical-claim-report",
    },
};
