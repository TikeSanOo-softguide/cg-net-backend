<?php

namespace App\Http\Controllers\Settings\GeneralSettings;

use App\Http\Controllers\Controller;
use App\Models\TermAndCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TermAndConditionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $termAndCondition = TermAndCondition::query()->create($request->validate($this->rules()));

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($termAndCondition)
            ->event('created')
            ->log('term_and_condition_created');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.terms_created');
    }

    public function update(Request $request, TermAndCondition $termAndCondition): RedirectResponse
    {
        $termAndCondition->update($request->validate($this->rules()));

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($termAndCondition)
            ->event('updated')
            ->log('term_and_condition_updated');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.terms_updated');
    }

    public function destroy(Request $request, TermAndCondition $termAndCondition): RedirectResponse
    {
        $termAndCondition->delete();

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($termAndCondition)
            ->event('deleted')
            ->log('term_and_condition_deleted');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.terms_deleted');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:120'],
            'title_zh' => ['required', 'string', 'max:120'],
            'title_my' => ['required', 'string', 'max:120'],
            'description_en' => ['required', 'string'],
            'description_zh' => ['required', 'string'],
            'description_my' => ['required', 'string'],
        ];
    }
}
