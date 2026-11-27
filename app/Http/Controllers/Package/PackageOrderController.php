<?php

namespace App\Http\Controllers\Package;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Enums\CustomerPackageStatus;
use App\Enums\PackageOrderStatus;
use App\Exceptions\PackageOrderReviewConflict;
use App\Exports\PackageOrderExport;
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
use Maatwebsite\Excel\Facades\Excel;

class PackageOrderController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $filters = $this->filters($request);
        $returnTo = $request->query('return_to');

        $orders = $this->query($filters)->paginate(15)->withQueryString();

        $unreadOrderIds = $readStatus->unreadReferenceIds(
            (int) $request->user()->getAuthIdentifier(),
            'package_order',
            $orders->getCollection()->pluck('id')->all(),
        );
        $orders->through(function (PackageOrder $order) use ($unreadOrderIds): array {
            $customerPackage = $order->customerPackage;
            $customerPackageData = null;

            if ($customerPackage !== null) {
                $customerPackageStatus = $customerPackage->status;

                if (
                    $customerPackageStatus === CustomerPackageStatus::Active &&
                    $customerPackage->expires_at !== null &&
                    $customerPackage->expires_at->lte(now())
                ) {
                    $customerPackageStatus = CustomerPackageStatus::Expired;
                }

                $customerPackageData = [
                    'status' => $customerPackageStatus->value,
                    'starts_at' => $customerPackage->starts_at,
                    'expires_at' => $customerPackage->expires_at,
                ];
            }

            return [
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
                'customer_package' => $customerPackageData,
            ];
        });

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
            'search' => $filters['search'],
            'filters' => [
                'package_id' => $filters['package_id'],
                'status' => $filters['status'],
                'from' => $filters['from'],
                'to' => $filters['to'],
            ],
            'filter_options' => [
                'packages' => $packages,
                'statuses' => array_map(
                    fn(PackageOrderStatus $status): string => $status->value,
                    PackageOrderStatus::cases(),
                ),
            ],
            'open_order_id' => $filters['open_order_id'],
            'return_to' => is_string($returnTo) ? NavigationStack::sanitize($returnTo) : null,
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new PackageOrderExport($this->query($this->filters($request))),
            'package_orders_' . now()->format('Ymd_His') . '.xlsx',
        );
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
            ->event(ActivityLogEvent::MarkedCompleted->value)
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

    /**
     * @return array{search: string, open_order_id: ?int, package_id: ?int, status: string, from: string, to: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'open_order_id' => $request->integer('open_package_order') ?: null,
            'package_id' => $request->integer('package_id') ?: null,
            'status' => PackageOrderStatus::tryFrom((string) $request->query('status', ''))?->value ?? '',
            'from' => $request->query('from') === '' ? '' : $request->date('from')?->toDateString() ?? '',
            'to' => $request->query('to') === '' ? '' : $request->date('to')?->toDateString() ?? '',
        ];
    }

    /**
     * @param  array{search: string, open_order_id: ?int, package_id: ?int, status: string, from: string, to: string}  $filters
     */
    private function query(array $filters): Builder
    {
        $openOrderId = $filters['open_order_id'];

        return PackageOrder::query()
            ->with([
                'user:id,name,phone',
                'ledgerTransaction:id,transaction_no,amount',
                'customerPackage:id,package_order_id,status,starts_at,expires_at',
            ])
            ->when($openOrderId, fn(Builder $query) => $query->whereKey($openOrderId))
            ->when(
                !$openOrderId && $filters['package_id'],
                fn(Builder $query) => $query->where('package_id', $filters['package_id']),
            )
            ->when(
                !$openOrderId && $filters['status'] !== '',
                fn(Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                !$openOrderId && $filters['from'] !== '',
                fn(Builder $query) => $query->whereDate('package_orders.created_at', '>=', $filters['from']),
            )
            ->when(
                !$openOrderId && $filters['to'] !== '',
                fn(Builder $query) => $query->whereDate('package_orders.created_at', '<=', $filters['to']),
            )
            ->when(!$openOrderId && $filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $search = $filters['search'];
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
            ->latest('package_orders.id');
    }
}
