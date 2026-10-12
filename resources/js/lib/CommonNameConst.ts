// Service request status
export const REQUEST_STATUS = {
    UNDER_REVIEW: 'under_review',
    APPROVED: 'approved',
} as const;

export type RequestStatus = (typeof REQUEST_STATUS)[keyof typeof REQUEST_STATUS];
