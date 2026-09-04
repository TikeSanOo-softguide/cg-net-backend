<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;

final class AdminHome
{
    /**
     * Preferred landing paths after login, in sidebar order.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function candidates(): array
    {
        return [
            ['dashboard.view', '/dashboard'],
            ['customers.view', '/customers'],
            ['cpe.view', '/cpe/inventory'],
            ['packages.view', '/packages'],
            ['billing.view', '/billing/invoices'],
            ['top-up-cards.view', '/top-up-cards/batch'],
            ['service-requests.view', '/service-requests/installations'],
            ['regions.view', '/regions'],
            ['notifications.view', '/notifications/announcements'],
            ['support.view', '/support/conversations'],
            ['cms.view', '/cms/banners'],
            ['staff.view', '/staff'],
            ['roles.view', '/roles'],
            ['activity.view', '/activity-logs'],
            ['reports.view', '/reports'],
            ['settings.view', '/settings/general'],
        ];
    }

    public static function path(?Authorizable $user): string
    {
        if ($user === null) {
            return '/login';
        }

        foreach (self::candidates() as [$permission, $path]) {
            if ($user->can($permission)) {
                return $path;
            }
        }

        return '/login';
    }
}
