<?php

namespace App\Http\Controllers\Package;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Enums\PackageOrderStatus;
use App\Exceptions\PackageOrderReviewConflict;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageOrder;
use App\Services\AdminNotification\AdminNotificationReadStatus;
use App\Services\Package\BuyPackageService;
use App\Support\NavigationStack;
use App\Support\PackageLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageOrderController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $search = trim((string) $request->query('search', ''));
        $openOrderId = $request->integer('open_package_order') ?: null;
        $packageId = $request->integer('package_id') ?: null;
        $status = PackageOrderStatus::tryFrom((string) $request->query('status', ''));
        $from = $request->query('from') === '' ? '' : $request->date('from')?->toDateString() ?? '';
        $to = $request->query('to') === '' ? '' : $request->date('to')?->toDateString() ?? '';
        $returnTo = $request->query('return_to');

        $orders = PackageOrder::query()
            ->with([
                'user:id,name,phone',
                'ledgerTransaction:id,transaction_no,amount',
                'customerPackage:id,package_order_id,status,starts_at,expires_at',
            ])
            ->when($openOrderId, fn(Builder $query) => $query->whereKey($openOrderId))
            ->when(!$openOrderId && $packageId, fn(Builder $query) => $query->where('package_id', $packageId))
            ->when(!$openOrderId && $status, fn(Builder $query) => $query->where('status', $status->value))
            ->when(
                !$openOrderId && $from !== '',
                fn(Builder $query) => $query->whereDate('package_orders.created_at', '>=', $from),
            )
            ->when(
                !$openOrderId && $to !== '',
                fn(Builder $query) => $query->whereDate('package_orders.created_at', '<=', $to),
            )
            ->when(!$openOrderId && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('package_orders.id', is_numeric($search) ? (int) $search : -1)
                        ->orWhereLike('package_orders.order_no', "%{$search}%")
                        ->orWhere('package_orders.status', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas(
                            'package',
                            fn(Builder $packageQuery) => $packageQuery->where(
                                'id',
                                is_numeric($search) ? (int) $search : -1,
                            ),
                        )
                        ->orWhereHas(
                            'ledgerTransaction',
                            fn(Builder $transactionQuery) => $transactionQuery->whereLike(
                                'transaction_no',
                                "%{$search}%",
                            ),
                        );
                });
            })
            ->latest('package_orders.id')
            ->paginate(15)
            ->withQueryString();

        $unreadOrderIds = $readStatus->unreadReferenceIds(
            (int) $request->user()->getAuthIdentifier(),
            'package_order',
            $orders->getCollection()->pluck('id')->all(),
        );
        $orders->through(
            fn(PackageOrder $order): array => [
                'id' => $order->id,
                'notification_unread' => in_array($order->id, $unreadOrderIds, true),
                'order_no' => $order->order_no,
                'customer' => [
                    'id' => $order->user?->id,
                    'name' => $order->user?->name,
                    'phone' => $order->user?->phone,
                ],
                'package' => $order->snapshot['product_name'] ?? null,
                'amount' => (string) ($order->snapshot['price'] ?? ($order->ledgerTransaction?->amount ?? '0.00')),
                'status' => (string) $order->getRawOriginal('status'),
                'activation_result_code' => $order->activation_result_code,
                'failure_reason' => $order->failure_reason,
                'transaction_no' => $order->ledgerTransaction?->transaction_no,
                'created_at' => $order->created_at,
                'completed_at' => $order->completed_at,
                'snapshot' => $order->snapshot,
                'external_response' => $order->external_response,
                'customer_package' => $order->customerPackage
                    ? [
                        'status' => $order->customerPackage->status->value,
                        'starts_at' => $order->customerPackage->starts_at,
                        'expires_at' => $order->customerPackage->expires_at,
                    ]
                    : null,
            ],
        );

        $packages = Package::withTrashed()
            ->whereHas('packageOrders')
            ->with(['network', 'speed', 'term'])
            ->orderBy('id')
            ->get()
            ->map(
                fn(Package $package): array => [
                    'id' => $package->id,
                    'label' =>
                        PackageLabel::make($package, app()->getLocale()) ?? __('menu.packages') . ' #' . $package->id,
                ],
            )
            ->values();

        return Inertia::render('PackageOrders/Index', [
            'orders' => $orders,
            'search' => $search,
            'filters' => [
                'package_id' => $packageId,
                'status' => $status?->value ?? '',
                'from' => $from,
                'to' => $to,
            ],
            'filter_options' => [
                'packages' => $packages,
                'statuses' => array_map(
                    fn(PackageOrderStatus $status): string => $status->value,
                    PackageOrderStatus::cases(),
                ),
            ],
            'open_order_id' => $openOrderId,
            'return_to' => is_string($returnTo) ? NavigationStack::sanitize($returnTo) : null,
        ]);
    }

    public function resolveReview(
        Request $request,
        PackageOrder $packageOrder,
        BuyPackageService $packages,
    ): RedirectResponse {
        try {
            $packages->resolveReviewSuccessfully($packageOrder, (int) $request->user()->getAuthIdentifier());
        } catch (PackageOrderReviewConflict) {
            return back()->with('error', 'package_orders.review_unavailable');
        }

        activity(ActivityLogName::PackageOrders->value)
            ->causedBy($request->user())
            ->performedOn($packageOrder)
            ->event(ActivityLogEvent::Completed->value)
            ->withProperties([
                'from_status' => PackageOrderStatus::Review->value,
                'to_status' => PackageOrderStatus::Completed->value,
            ])
            ->log('package_order_completed');

        return back()->with('success', 'package_orders.review_completed');
    }

    public function refundReview(
        Request $request,
        PackageOrder $packageOrder,
        BuyPackageService $packages,
    ): RedirectResponse {
        try {
            $packages->refundReview($packageOrder, (int) $request->user()->getAuthIdentifier());
        } catch (PackageOrderReviewConflict) {
            return back()->with('error', 'package_orders.review_unavailable');
        }

        activity(ActivityLogName::PackageOrders->value)
            ->causedBy($request->user())
            ->performedOn($packageOrder)
            ->event(ActivityLogEvent::Refunded->value)
            ->withProperties([
                'from_status' => PackageOrderStatus::Review->value,
                'to_status' => PackageOrderStatus::Failed->value,
            ])
            ->log('package_order_refunded');

        return back()->with('success', 'package_orders.review_refunded');
    }
}
