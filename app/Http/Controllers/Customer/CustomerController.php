<?php

namespace App\Http\Controllers\Customer;

use App\Enums\UserStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\BindAccountNumberRequest;
use App\Http\Requests\Customer\CustomerData;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerStatusRequest;
use App\Models\User;
use App\Services\BroarbandAccount\BroadbandAccountService;
use App\Services\Ledger\LedgerPoster;
use App\Services\Transaction\TransactionService;
use App\Support\PackageLabel;
use App\Support\ReturnTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['name', 'status', 'wallet', 'created_at'];

        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $customers = User::query()
            ->when(
                $sort === 'wallet',
                fn($query) => $query->select('users.*')->leftJoin('wallets', 'wallets.user_id', '=', 'users.id'),
            )
            ->with([
                'wallet:id,user_id,balance',
                'customerPackages' => fn($query) => $query
                    ->where('status', CustomerPackageStatus::Active->value)
                    ->with(
                        'package.network:id,name_en,name_zh,name_my',
                        'package.speed:id,mbps',
                        'package.term:id,months',
                    ),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('users.name', '%' . $search . '%')
                        ->orWhereLike('users.phone', '%' . $search . '%');
                });
            })
            ->when($status !== '' && in_array($status, array_column(UserStatus::cases(), 'value'), true), function (
                $query,
            ) use ($status): void {
                $query->where('users.status', $status);
            })
            ->when(
                $sort === 'wallet',
                fn($query) => $query->orderBy('wallets.balance', $direction),
                fn($query) => $query->orderBy('users.' . $sort, $direction),
            )
            ->paginate(15)
            ->withQueryString()
            ->through(function (User $customer) {
                $currentPackage = $customer->customerPackages->first()?->package;

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'status' => $customer->status->value,
                    'wallet_balance' => number_format((float) ($customer->wallet?->balance ?? 0), 0, '.', ''),
                    'broadband_connected' => $customer->broadband_account_number !== null,
                    'created_at' => $customer->created_at?->toDateString(),
                ];
            });

        ReturnTo::remember($request, 'customers.show');

        return Inertia::render('Customer/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('customers.index');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $payload = CustomerData::payload($request->validated());

        $customer = DB::transaction(function () use ($payload) {
            $customer = User::query()->create($payload);
            $wallet = $customer->wallet()->create(['balance' => 0]);
            app(LedgerPoster::class)->ensureCustomerLiabilityAccount($wallet);

            return $customer;
        });

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('created')
            ->withProperties(Arr::except($payload, ['password']))
            ->log('customer_created');

        if ($request->headers->has('X-Modal')) {
            return redirect()->route('customers.index')->with('success', 'customers.created');
        }

        return redirect()->route('customers.show', $customer)->with('success', 'customers.created');
    }

    public function show(
        Request $request,
        User $customer,
        TransactionService $transactions,
        BroadbandAccountService $broadbandAccountService,
    ): Response {
        $locale = app()->getLocale();

        $customer->load([
            'customerPackages.package.network:id,name_en,name_zh,name_my',
            'customerPackages.package.speed:id,mbps',
            'customerPackages.package.term:id,months',
            'wallet',
            'wallet.transactions' => fn($query) => $query->latest()->limit(10),
        ]);

        $topUpHistory = $customer
            ->redeemedTopUpCards()
            ->latest('redeemed_at')
            ->paginate(10, ['*'], 'topup_page')
            ->through(
                fn($card) => [
                    'id' => $card->id,
                    'serial_no' => $card->serial_no,
                    'amount' => (int) $card->amount,
                    'status' => $card->status->value,
                    'redeemed_at' => $card->redeemed_at,
                ],
            );

        $walletTransactions = $customer->wallet?->transactions()->get() ?? collect();
        $transactionOverview = collect([
            ['key' => 'topup', 'type' => LedgerTransactionType::Topup],
            ['key' => 'ftth_bill', 'type' => LedgerTransactionType::FtthBill],
            ['key' => 'wifi_package', 'type' => LedgerTransactionType::WifiPackage],
            ['key' => 'refund', 'type' => LedgerTransactionType::Refund],
            ['key' => 'adjustment', 'type' => LedgerTransactionType::Adjustment],
        ])
            ->map(function (array $type) use ($walletTransactions) {
                $filtered = $walletTransactions->filter(fn($transaction) => $transaction->type === $type['type']);

                return [
                    $type['key'] => [
                        'count' => $filtered->count(),
                        'amount' => number_format(
                            (float) $filtered->sum(fn($transaction) => (float) $transaction->amount),
                            0,
                            '.',
                            '',
                        ),
                    ],
                ];
            })
            ->collapse();

        $transactionFilters = [...$transactions->filters($request), 'customer_id' => $customer->id];
        $transactionStatus = $transactionFilters['status'];
        $transactionDirection = $transactionFilters['direction'];
        $transactionFrom = $transactionFilters['from'];
        $transactionTo = $transactionFilters['to'];

        $transactionPage =
            $request->string('transactions')->toString() === 'all'
                ? $transactions->paginate($transactionFilters, 'transaction_page')
                : null;

        $packageRows = $customer->customerPackages->sortByDesc('start_date')->values()->map(
            fn($row) => [
                'id' => $row->id,
                'package_name' => [
                    'en' => PackageLabel::make($row->package, 'en'),
                    'my' => PackageLabel::make($row->package, 'my'),
                    'zh' => PackageLabel::make($row->package, 'zh'),
                ],
                'account_number' => $customer->broadband_account_number,
                'start_date' => $row->start_date,
                'expiry_date' => $row->expiry_date,
                'auto_renew' => $row->auto_renew,
                'status' => $row->status->value,
            ],
        );

        ReturnTo::captureReferer($request, 'customers.show');
        $returnTo = ReturnTo::get('customers.show', route('customers.index', absolute: false));

        $accountBinding = null;
        $broadbandPackage = null;

        if ($customer->broadband_account_number !== null) {
            $accountBinding = $broadbandAccountService->findByAccountNumber($customer->broadband_account_number);

            if ($accountBinding) {
                $currentCustomerPackageId = (int) ($accountBinding['current_customer_package_id'] ?? 0);

                $customerPackage = $customer
                    ->customerPackages()
                    ->with('package')
                    ->whereKey($currentCustomerPackageId)
                    ->first();

                $broadbandPackage = $customerPackage?->package;
            }
        }

        return Inertia::render('Customer/Show', [
            'customer' => $this->customerPayload($customer),
            'accountBinding' => $accountBinding
                ? [
                    'account_number' => $accountBinding['account_number'] ?? null,
                    'customer_name' => $accountBinding['customer_name'] ?? null,
                    'status' => $accountBinding['status'] ?? null,
                    'package_name' => [
                        'en' => PackageLabel::make($broadbandPackage, 'en'),
                        'my' => PackageLabel::make($broadbandPackage, 'my'),
                        'zh' => PackageLabel::make($broadbandPackage, 'zh'),
                    ],
                ]
                : null,
            'packageHistory' => $packageRows->values(),
            'wallet' => [
                'balance' => number_format((float) ($customer->wallet?->balance ?? 0), 0, '.', ''),
                'transaction_overview' => $transactionOverview,
                'transactions' =>
                    $customer->wallet?->transactions
                        ->map(
                            fn($transaction) => [
                                'id' => $transaction->id,
                                'transaction_no' => $transaction->transaction_no,
                                'type' => $transaction->type->value,
                                'status' => $transaction->status->value,
                                'amount' => number_format((float) $transaction->amount, 0, '.', ''),
                                'created_at' => $transaction->created_at?->toDateString(),
                            ],
                        )
                        ->values() ?? collect(),
            ],
            'transactionPage' => $transactionPage,
            'transactionFilters' => [
                'customer_id' => $customer->id,
                'search' => $transactionFilters['search'],
                'actor_type' => '',
                'type' => $transactionFilters['type'],
                'status' => $transactionStatus,
                'direction' => $transactionDirection,
                'from' => $transactionFrom ?? '',
                'to' => $transactionTo ?? '',
            ],
            'transactionFilterOptions' => [
                'actor_types' => [],
                'types' => array_column(LedgerTransactionType::cases(), 'value'),
                'statuses' => array_column(LedgerTransactionStatus::cases(), 'value'),
            ],
            'topUpHistory' => $topUpHistory,
            'return_to' => $returnTo,
        ]);
    }

    public function edit(User $customer): RedirectResponse
    {
        return redirect()->route('customers.show', $customer);
    }

    public function update(UpdateCustomerRequest $request, User $customer): RedirectResponse
    {
        $payload = CustomerData::payload($request->validated());
        $customer->update($payload);

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('updated')
            ->withProperties(Arr::except($payload, ['password']))
            ->log('customer_updated');

        if ($request->headers->has('X-Modal')) {
            return back()->with('success', 'customers.updated');
        }

        return redirect()->route('customers.show', $customer)->with('success', 'customers.updated');
    }

    public function destroy(Request $request, User $customer): RedirectResponse
    {
        $customer->delete();

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('deleted')
            ->log('customer_deleted');

        return redirect()->route('customers.index')->with('success', 'customers.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ])['ids'];

        $deleted = 0;

        foreach (User::query()->whereIn('id', $ids)->get() as $customer) {
            $customer->delete();
            activity('customers')
                ->causedBy($request->user())
                ->performedOn($customer)
                ->event('deleted')
                ->log('customer_deleted');
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->withErrors(['delete' => 'common.bulk_delete_failed']);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deleted);
    }

    public function updateStatus(UpdateCustomerStatusRequest $request, User $customer): RedirectResponse
    {
        $status = UserStatus::from($request->validated('status'));
        $previous = $customer->status;

        if ($previous === $status) {
            return back();
        }

        $customer->update(['status' => $status]);

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('status_changed')
            ->withProperties([
                'from' => $previous->value,
                'to' => $status->value,
            ])
            ->log('customer_status_updated');

        return back()->with(
            'success',
            $status === UserStatus::Suspended ? 'customers.suspended' : 'customers.reactivated',
        );
    }

    public function bindAccount(BindAccountNumberRequest $request, User $customer): RedirectResponse
    {
        $accountNumber = trim($request->validated('account_number'));
        if ($customer->broadband_account_number === $accountNumber) {
            return back()->withErrors(['account_number' => __('customers.account_already_bound')]);
        }

        $customer->update(['broadband_account_number' => $accountNumber]);

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('account_bound')
            ->withProperties([
                'account_number' => $accountNumber,
            ])
            ->log('broadband_account_bound');

        return back()->with('success', 'customers.account_bound');
    }

    public function unbindAccount(Request $request, User $customer): RedirectResponse
    {
        $accountNumber = $customer->broadband_account_number;

        if ($accountNumber === null) {
            abort(404);
        }

        $customer->update(['broadband_account_number' => null]);

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('account_unbound')
            ->withProperties([
                'account_number' => $accountNumber,
            ])
            ->log('broadband_account_unbound');

        return back()->with('success', 'customers.account_unbound');
    }

    /**
     * @return array{id: int, name: string, phone: string, status: string, created_at: string|null}
     */
    private function customerPayload(User $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'status' => $customer->status->value,
            'created_at' => $customer->created_at?->toDateString(),
        ];
    }
}
