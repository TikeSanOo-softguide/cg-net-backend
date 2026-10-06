<?php

namespace App\Http\Controllers\Settings\GeneralSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GeneralSettings\StoreFaqRequest;
use App\Http\Requests\Settings\GeneralSettings\UpdateFaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $faq = Faq::create($request->validated());

        activity('settings')->causedBy($request->user())->performedOn($faq)->event('created')->log('faq_created');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.faq_created');
    }

    public function update(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->validated());

        activity('settings')->causedBy($request->user())->performedOn($faq)->event('updated')->log('faq_updated');

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.faq_updated');
    }

    public function destroy(Request $request, Faq $faq): RedirectResponse
    {
        activity('settings')->causedBy($request->user())->performedOn($faq)->event('deleted')->log('faq_deleted');

        $faq->forceDelete();

        return redirect()->route('settings.general.index')->with('success', 'settings.general_settings.faq_deleted');
    }
}
