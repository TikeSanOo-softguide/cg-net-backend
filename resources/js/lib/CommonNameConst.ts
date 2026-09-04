// Change Plan
export const CHANGE_PLAN_STATUS = {
    UNDER_REVIEW: 'under_review',
    APPROVED: 'approved',
} as const;

export type ChangePlanStatus = (typeof CHANGE_PLAN_STATUS)[keyof typeof CHANGE_PLAN_STATUS];
