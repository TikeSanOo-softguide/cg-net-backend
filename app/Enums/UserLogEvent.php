<?php

namespace App\Enums;

enum UserLogEvent: string
{
    case LoginSuccess = 'login_success';
    case Logout = 'logout';
    case RegisterComplete = 'register_complete';
    case CreateRelocationRequest = 'create_relocation_request';
    case UpdateRelocationRequest = 'update_relocation_request';
    case CancelRelocationRequest = 'cancel_relocation_request';
    case DeleteRelocationRequest = 'delete_relocation_request';
    case CreateBroadbandApplication = 'create_broadband_application';
    case UpdateBroadbandApplication = 'update_broadband_application';
    case DeleteBroadbandApplication = 'delete_broadband_application';
    case CancelBroadbandApplication = 'cancel_broadband_application';
    case CreateFailureReport = 'create_failure_report';
    case UpdateFailureReport = 'update_failure_report';
    case CancelFailureReport = 'cancel_failure_report';
    case DeleteFailureReport = 'delete_failure_report';
    case CreateChangePasswordRequest = 'create_change_password_request';
    case UpdateChangePasswordRequest = 'update_change_password_request';
    case CancelChangePasswordRequest = 'cancel_change_password_request';
    case DeleteChangePasswordRequest = 'delete_change_password_request';
    case CreateChangePlanRequest = 'create_change_plan_request';
    case UpdateChangePlanRequest = 'update_change_plan_request';
    case CancelChangePlanRequest = 'cancel_change_plan_request';
    case DeleteChangePlanRequest = 'delete_change_plan_request';

    /** @return list<string> */
    public static function values(): array
    {
        $values = array_map(static fn(self $event): string => $event->value, self::cases());
        sort($values, SORT_STRING);

        return $values;
    }
}
