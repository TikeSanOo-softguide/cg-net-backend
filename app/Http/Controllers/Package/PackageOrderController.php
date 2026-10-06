<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Models\PackageOrder;
use App\Support\NavigationStack;
use App\Support\PackageLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $openOrderId = $request->integer('open_package_order') ?: null;
        $returnTo = $request->query('return_to');

        $orders = PackageOrder::query()
            ->with([
                'user:id,name,phone',
                'package.network',
                'package.speed',
                'package.term',
                'ledgerTransaction:id,transaction_no,amount',
                'customerPackage:id,package_order_id,status,starts_at,expires_at',
            ])
            ->when($openOrderId, fn(Builder $query) => $query->whereKey($openOrderId))
            ->when(!$openOrderId && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('package_orders.id', is_numeric($search) ? (int) $search : -1)
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
            ->withQueryString()
            ->through(
                fn(PackageOrder $order): array => [
                    'id' => $order->id,
                    'customer' => [
                        'id' => $order->user?->id,
                        'name' => $order->user?->name,
                        'phone' => $order->user?->phone,
                    ],
                    'package' => PackageLabel::make($order->package, app()->getLocale()),
                    'amount' => (int) ($order->snapshot['price'] ?? ($order->ledgerTransaction?->amount ?? 0)),
                    'status' => (string) $order->getRawOriginal('status'),
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

        return Inertia::render('PackageOrders/Index', [
            'orders' => $orders,
            'search' => $search,
            'open_order_id' => $openOrderId,
            'return_to' => is_string($returnTo) ? NavigationStack::sanitize($returnTo) : null,
        ]);
    }
}
