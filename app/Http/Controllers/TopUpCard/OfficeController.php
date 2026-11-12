<?php

namespace App\Http\Controllers\TopUpCard;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpCard\StoreOfficeRequest;
use App\Http\Requests\TopUpCard\UpdateOfficeRequest;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $officeQuery = Office::query()
            ->withCount('topUpCards')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    foreach (
                        [
                            'name_en',
                            'name_mm',
                            'name_zh',
                            'cd',
                            'address_en',
                            'address_mm',
                            'address_zh',
                            'phone_num1',
                            'phone_num2',
                            'phone_num3',
                            'telegram_link',
                            'wechat_link',
                            'viber_link',
                        ] as $column
                    ) {
                        $query->orWhereLike($column, "%{$search}%");
                    }
                });
            })
            ->orderBy('created_at');
        $offices = $officeQuery->paginate(15)->withQueryString();
        $officeCds = Office::query()->pluck('cd')->map(fn ($cd): string => (string) $cd)->values();

        return Inertia::render('TopUpCards/Office', [
            'offices' => $offices,
            'officeCds' => $officeCds,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreOfficeRequest $request): RedirectResponse
    {
        $office = Office::query()->create($request->validated());
        activity(ActivityLogName::TopUpCardOffice->value)
            ->causedBy($request->user())
            ->performedOn($office)
            ->event(ActivityLogEvent::Created->value)
            ->log('office_created');

        return back()->with('success', 'top_up_cards.office.created');
    }

    public function update(UpdateOfficeRequest $request, Office $office): RedirectResponse
    {
        $office->update($request->validated());

        activity(ActivityLogName::TopUpCardOffice->value)
            ->causedBy($request->user())
            ->performedOn($office)
            ->event(ActivityLogEvent::Updated->value)
            ->log('office_updated');

        return back()->with('success', 'top_up_cards.office.updated');
    }

    public function destroy(Request $request, Office $office): RedirectResponse
    {
        if ($office->topUpCards()->exists()) {
            return back()->with('error', __('top_up_cards.office.office_cannot_delete'));
        }

        activity(ActivityLogName::TopUpCardOffice->value)
            ->causedBy($request->user())
            ->performedOn($office)
            ->event(ActivityLogEvent::Deleted->value)
            ->log('office_deleted');

        $office->delete();

        return back()->with('success', __('top_up_cards.office.deleted'));
    }
}
