<?php

namespace App\Http\Controllers\Settings\GeneralSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GeneralSettings\StoreTermAndConditionRequest;
use App\Http\Requests\Settings\GeneralSettings\UpdateTermAndConditionRequest;
use App\Models\TermAndCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TermAndConditionController extends Controller
{
    public function store(StoreTermAndConditionRequest $request): RedirectResponse
    {
        $termAndCondition = TermAndCondition::query()->create($request->validated());

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($termAndCondition)
            ->event('created')
            ->log('term_and_condition_created');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.terms_created');
    }

    public function update(UpdateTermAndConditionRequest $request, TermAndCondition $termAndCondition): RedirectResponse
    {
        $termAndCondition->update($request->validated());

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($termAndCondition)
            ->event('updated')
            ->log('term_and_condition_updated');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.terms_updated');
    }

    public function destroy(Request $request, TermAndCondition $termAndCondition): RedirectResponse
    {
        activity('settings')
            ->causedBy($request->user())
            ->performedOn($termAndCondition)
            ->event('deleted')
            ->log('term_and_condition_deleted');

        $termAndCondition->forceDelete();

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.terms_deleted');
    }
}
