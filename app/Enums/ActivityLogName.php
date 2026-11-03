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
    case TopUpCardOffice = 'top_up_card_office';

    public function menuLabelKey(): string
    {
        return match ($this) {
            self::Customers => 'menu.customers_list',
            self::InstallationApplication,
            self::FailureReport,
            self::ChangePasswordRequest,
            self::ChangePlanRequest,
            self::RelocationRequest,
            self::ServiceRequests => 'menu.service_requests',
            self::TopUpCards,
            self::TopUpCardOffice => 'menu.top_up_card_management',
            self::Announcement,
            self::NotificationTemplate,
            self::Promotion,
            self::PushNotification => 'menu.notifications',
            self::ChatbotFlow,
            self::QuickReply => 'menu.support',
            self::Addon,
            self::Network,
            self::Package,
            self::Speed,
            self::Term => 'menu.packages',
            self::Area,
            self::Region,
            self::State => 'menu.region_management',
            self::Reports => 'menu.reports',
            self::Banner,
            self::Category,
            self::Contact,
            self::Gallery,
            self::News,
            self::Service => 'menu.cms',
            self::Admin,
            self::Role,
            self::Staff => 'menu.staff_role_management',
            self::AppVersion,
            self::Faq,
            self::SupportContact,
            self::TermsAndConditions => 'menu.settings',
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

        foreach ($groups as &$options) {
            sort($options, SORT_STRING);
        }

        $orderedGroups = [
            'menu.customers_list',
            'menu.service_requests',
            'menu.top_up_card_management',
            'menu.notifications',
            'menu.support',
            'menu.packages',
            'menu.region_management',
            'menu.reports',
            'menu.cms',
            'menu.staff_role_management',
            'menu.settings',
        ];

        $result = [];
        foreach ($orderedGroups as $group) {
            if (isset($groups[$group])) {
                $result[] = ['group' => $group, 'options' => $groups[$group]];
            }
        }

        return $result;
    }
}
