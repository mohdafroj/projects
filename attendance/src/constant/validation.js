import {
  useValidation as useValidationDep,
  required
} from '@sds/oneui-validation';

export const useValidation = useValidationDep;

export const approvalNoticeValSchema = {
  toc: {
    subject: [required()],
    remarks: [required()],
    file: [required()],
  },
  draft: {
    content: [required()],
  },
  approve: {
    draft_id: [required()],
  },
};
