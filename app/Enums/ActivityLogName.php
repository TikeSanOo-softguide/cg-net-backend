<?php

namespace App\Enums;

enum ActivityLogName: string
{
    case Addon = 'addon';
    case Admin = 'admin';
    case Announcement = 'announcement';
    case AppVersion = 'app_version';
    case Area = 'area';
    case Banner = 'banner';
    case BillPayments = 'bill_payments';
    case Category = 'category';
    case ChatbotFlow = 'chatbot_flow';
    case ChangePasswordRequest = 'change_password_request';
    case ChangePlanRequest = 'change_plan_request';
    case Contact = 'contact';
    case Customers = 'customers';
    case FailureReport = 'failure_report';
    case Faq = 'faq';
    case Gallery = 'gallery';
    case InstallationApplication = 'installation_application';
    case Network = 'network';
    case News = 'news';
    case NotificationTemplate = 'notification_template';
    case Package = 'package';
    case PackageOrders = 'package_orders';
    case Promotion = 'promotion';
    case PushNotification = 'push_notification';
    case QuickReply = 'quick_reply';
    case Region = 'region';
    case RelocationRequest = 'relocation_request';
    case Reports = 'reports';
    case Role = 'role';
    case Service = 'service';
    case ServiceRequests = 'service-requests';
    case Speed = 'speed';
    case State = 'state';
    case Staff = 'staff';
    case SupportContact = 'support_contact';
    case Term = 'term';
    case TermsAndConditions = 'terms_and_conditions';
    case TopUpCards = 'top_up_cards';
    case TopUpCardBatchImport = 'top_up_card_batch_import';
    case TopUpCardOffice = 'top_up_card_office';

    /** @return array<string, list<string>> */
    public static function eventMap(): array
    {
        return [
            self::Addon->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::Admin->value => self::eventValues(
                ActivityLogEvent::LoggedIn,
                ActivityLogEvent::LoginFailed,
                ActivityLogEvent::LoginBlocked,
            ),
            self::Announcement->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::AppVersion->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::Area->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::Banner->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::BillPayments->value => self::eventValues(
                ActivityLogEvent::MarkedCompleted,
                ActivityLogEvent::Refunded,
            ),
            self::Category->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::ChatbotFlow->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::ChangePasswordRequest->value => self::eventValues(ActivityLogEvent::StatusUpdated),
            self::ChangePlanRequest->value => self::eventValues(ActivityLogEvent::StatusUpdated),
            self::Contact->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::Customers->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::StatusChanged,
                ActivityLogEvent::SessionRevoked,
                ActivityLogEvent::AccountBound,
                ActivityLogEvent::AccountUnbound,
                ActivityLogEvent::WalletAdjusted,
            ),
            self::FailureReport->value => self::eventValues(ActivityLogEvent::StatusUpdated),
            self::Faq->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::Gallery->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::InstallationApplication->value => self::eventValues(ActivityLogEvent::StatusUpdated),
            self::Network->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::News->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::NotificationTemplate->value => self::eventValues(ActivityLogEvent::Updated),
            self::Package->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::PackageOrders->value => self::eventValues(
                ActivityLogEvent::MarkedCompleted,
                ActivityLogEvent::Refunded,
            ),
            self::Promotion->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::PushNotification->value => self::eventValues(
                ActivityLogEvent::Pushed,
                ActivityLogEvent::Scheduled,
                ActivityLogEvent::Cancelled,
            ),
            self::QuickReply->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::Region->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::RelocationRequest->value => self::eventValues(ActivityLogEvent::StatusUpdated),
            self::Reports->value => self::eventValues(ActivityLogEvent::Completed, ActivityLogEvent::Failed),
            self::Role->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::Service->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::ServiceRequests->value => self::eventValues(ActivityLogEvent::Deleted),
            self::Speed->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::State->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::Staff->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::SupportContact->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::Term->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
                ActivityLogEvent::BulkDeleted,
            ),
            self::TermsAndConditions->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
            self::TopUpCards->value => self::eventValues(ActivityLogEvent::Blocked),
            self::TopUpCardBatchImport->value => self::eventValues(
                ActivityLogEvent::StatusChanged,
                ActivityLogEvent::Succeeded,
                ActivityLogEvent::Failed,
            ),
            self::TopUpCardOffice->value => self::eventValues(
                ActivityLogEvent::Created,
                ActivityLogEvent::Updated,
                ActivityLogEvent::Deleted,
            ),
        ];
    }

    /** @return list<string> */
    private static function eventValues(ActivityLogEvent ...$events): array
    {
        return array_map(static fn(ActivityLogEvent $event): string => $event->value, $events);
    }

    public function menuLabelKey(): string
    {
        return match ($this) {
            self::Customers => 'menu.customers_list',
            self::InstallationApplication,
            self::FailureReport,
            self::ChangePasswordRequest,
            self::ChangePlanRequest,
            self::RelocationRequest,
            self::ServiceRequests
                => 'menu.service_requests',
            self::TopUpCards, self::TopUpCardOffice, self::TopUpCardBatchImport => 'menu.top_up_card_management',
            self::Announcement,
            self::NotificationTemplate,
            self::Promotion,
            self::PushNotification
                => 'menu.notifications',
            self::ChatbotFlow, self::QuickReply => 'menu.support',
            self::Addon, self::Network, self::Package, self::Speed, self::Term => 'menu.packages',
            self::PackageOrders, self::BillPayments => 'menu.billing',
            self::Area, self::Region, self::State => 'menu.region_management',
            self::Reports => 'menu.reports',
            self::Banner, self::Category, self::Contact, self::Gallery, self::News, self::Service => 'menu.cms',
            self::Admin, self::Role, self::Staff => 'menu.staff_role_management',
            self::AppVersion, self::Faq, self::SupportContact, self::TermsAndConditions => 'menu.settings',
        };
    }

    /**
     * @return list<array{group: string, options: list<string>}>
     */
    public static function groupedValues(): array
    {
        $groups = [];

        foreach (self::cases() as $logName) {
            $group = $logName->menuLabelKey();
            $groups[$group][] = $logName->value;
        }

        $orderedGroups = [
            'menu.customers_list' => [self::Customers],
            'menu.billing' => [self::PackageOrders, self::BillPayments],
            'menu.service_requests' => [
                self::InstallationApplication,
                self::FailureReport,
                self::RelocationRequest,
                self::ChangePlanRequest,
                self::ChangePasswordRequest,
                self::ServiceRequests,
            ],
            'menu.top_up_card_management' => [self::TopUpCardOffice, self::TopUpCardBatchImport, self::TopUpCards],
            'menu.notifications' => [
                self::Announcement,
                self::Promotion,
                self::PushNotification,
                self::NotificationTemplate,
            ],
            'menu.support' => [self::QuickReply, self::ChatbotFlow],
            'menu.packages' => [self::Network, self::Speed, self::Term, self::Addon, self::Package],
            'menu.region_management' => [self::State, self::Region, self::Area],
            'menu.reports' => [self::Reports],
            'menu.cms' => [self::Banner, self::Category, self::News, self::Service, self::Gallery, self::Contact],
            'menu.staff_role_management' => [self::Staff, self::Role, self::Admin],
            'menu.settings' => [self::AppVersion, self::TermsAndConditions, self::Faq, self::SupportContact],
        ];

        $result = [];
        foreach ($orderedGroups as $group => $orderedLogNames) {
            if (!isset($groups[$group])) {
                continue;
            }

            $options = array_values(
                array_filter(
                    array_map(static fn(self $logName): string => $logName->value, $orderedLogNames),
                    static fn(string $logName): bool => in_array($logName, $groups[$group], true),
                ),
            );
            $remainingOptions = array_values(array_diff($groups[$group], $options));
            sort($remainingOptions, SORT_STRING);

            $result[] = ['group' => $group, 'options' => [...$options, ...$remainingOptions]];
        }

        return $result;
    }
}
