<?php

namespace App\Http\Controllers\Customer;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\BindBroadbandAccountRequest;
use App\Http\Requests\Customer\CustomerData;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerStatusRequest;
use App\Models\BroadbandAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $sortable = ['name', 'phone', 'nrc_number', 'status', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $customers = User::query()
            ->withCount('broadbandAccounts')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('nrc_number', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '' && in_array($status, array_column(UserStatus::cases(), 'value'), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'nrc_number' => $customer->nrc_number,
                'status' => $customer->status->value,
                'accounts_count' => $customer->broadband_accounts_count,
                'created_at' => $customer->created_at?->toDateString(),
            ]);

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

    public function create(): Response
    {
        return Inertia::render('Customer/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $payload = CustomerData::payload($request->validated());

        $customer = DB::transaction(function () use ($payload) {
            $customer = User::query()->create($payload);
            $customer->wallet()->create(['balance_mmk' => 0]);

            return $customer;
        });

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('created')
            ->withProperties($payload)
            ->log('customer_created');

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'customers.created');
    }

    public function show(User $customer): Response
    {
        $customer->load([
            'broadbandAccounts.currentPackage:id,name',
            'customerPackages.package:id,name,speed_mbps,data_gb',
            'customerPackages.broadbandAccount:id,account_number',
            'wallet.transactions' => fn ($query) => $query->latest()->limit(10),
        ]);

        return Inertia::render('Customer/Show', [
            'customer' => $this->customerPayload($customer),
            'broadbandAccounts' => $customer->broadbandAccounts->map(fn (BroadbandAccount $account) => [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'customer_name' => $account->customer_name,
                'status' => $account->status->value,
                'package_name' => $account->currentPackage?->name,
            ]),
            'packages' => $customer->customerPackages
                ->sortByDesc('start_date')
                ->values()
                ->map(fn ($package) => [
                    'id' => $package->id,
                    'package_name' => $package->package?->name,
                    'account_number' => $package->broadbandAccount?->account_number,
                    'start_date' => $package->start_date?->toDateString(),
                    'expiry_date' => $package->expiry_date?->toDateString(),
                    'auto_renew' => $package->auto_renew,
                    'status' => $package->status->value,
                    'speed_mbps' => $package->package?->speed_mbps,
                    'data_gb' => $package->package?->data_gb,
                ]),
            'wallet' => [
                'balance_mmk' => number_format((float) ($customer->wallet?->balance_mmk ?? 0), 2, '.', ''),
                'transactions' => $customer->wallet?->transactions->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type->value,
                    'amount' => number_format((float) $transaction->amount, 2, '.', ''),
                    'reference_id' => $transaction->reference_id,
                    'status' => $transaction->status->value,
                    'created_at' => $transaction->created_at?->toDateString(),
                ])->values() ?? [],
            ],
        ]);
    }

    public function edit(User $customer): Response
    {
        return Inertia::render('Customer/Edit', [
            'customer' => $this->customerPayload($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, User $customer): RedirectResponse
    {
        $payload = CustomerData::payload($request->validated());
        $customer->update($payload);

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('updated')
            ->withProperties($payload)
            ->log('customer_updated');

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'customers.updated');
    }

    public function destroy(Request $request, User $customer): RedirectResponse
    {
        $customer->delete();

        activity('customers')->causedBy($request->user())->performedOn($customer)->event('deleted')->log('customer_deleted');

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
            activity('customers')->causedBy($request->user())->performedOn($customer)->event('deleted')->log('customer_deleted');
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->withErrors(['delete' => 'common.bulk_delete_failed']);
        }

        return redirect()->route('customers.index')
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

        return back()->with('success', $status === UserStatus::Suspended
            ? 'customers.suspended'
            : 'customers.reactivated');
    }

    public function bindAccount(BindBroadbandAccountRequest $request, User $customer): RedirectResponse
    {
        $accountNumber = trim($request->validated('account_number'));
        $account = BroadbandAccount::query()->where('account_number', $accountNumber)->first();

        if (! $account) {
            return back()->withErrors(['account_number' => __('customers.account_not_found')]);
        }

        if ($account->user_id === $customer->id) {
            return back()->withErrors(['account_number' => __('customers.account_already_bound')]);
        }

        if ($account->user_id !== null) {
            return back()->withErrors(['account_number' => __('customers.account_bound_elsewhere')]);
        }

        $account->update([
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
        ]);

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('account_bound')
            ->withProperties([
                'broadband_account_id' => $account->id,
                'account_number' => $account->account_number,
            ])
            ->log('broadband_account_bound');

        return back()->with('success', 'customers.account_bound');
    }

    public function unbindAccount(Request $request, User $customer, BroadbandAccount $account): RedirectResponse
    {
        if ($account->user_id !== $customer->id) {
            abort(404);
        }

        $accountNumber = $account->account_number;

        $account->update(['user_id' => null]);

        activity('customers')
            ->causedBy($request->user())
            ->performedOn($customer)
            ->event('account_unbound')
            ->withProperties([
                'broadband_account_id' => $account->id,
                'account_number' => $accountNumber,
            ])
            ->log('broadband_account_unbound');

        return back()->with('success', 'customers.account_unbound');
    }

    /**
     * @return array{id: int, name: string, phone: string, nrc_number: string, email: string|null, address: string|null, language_pref: string, status: string, created_at: string|null}
     */
    private function customerPayload(User $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'nrc_number' => $customer->nrc_number,
            'email' => $customer->email,
            'address' => $customer->address,
            'language_pref' => $customer->language_pref->value,
            'status' => $customer->status->value,
            'created_at' => $customer->created_at?->toDateString(),
        ];
    }
}
