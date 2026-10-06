<?php

namespace App\Http\Controllers\Settings\GeneralSettings;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $faq = Faq::query()->create($request->validate($this->rules()));

        activity('settings')->causedBy($request->user())->performedOn($faq)->event('created')->log('faq_created');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.faq_created');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->validate($this->rules()));

        activity('settings')->causedBy($request->user())->performedOn($faq)->event('updated')->log('faq_updated');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.faq_updated');
    }

    public function destroy(Request $request, Faq $faq): RedirectResponse
    {
        $faq->delete();

        activity('settings')->causedBy($request->user())->performedOn($faq)->event('deleted')->log('faq_deleted');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.faq_deleted');
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
