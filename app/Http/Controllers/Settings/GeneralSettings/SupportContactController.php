<?php

namespace App\Http\Controllers\Settings\GeneralSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GeneralSettings\StoreSupportContactRequest;
use App\Http\Requests\Settings\GeneralSettings\UpdateSupportContactRequest;
use App\Models\SupportContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportContactController extends Controller
{
    public function store(StoreSupportContactRequest $request): RedirectResponse
    {
        $supportContact = SupportContact::query()->create($request->validated());

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($supportContact)
            ->event('created')
            ->log('support_contact_created');

        return redirect()
            ->route('settings.general.index')
            ->with('success', 'settings.general_settings.support_contact_created');
    }

    public function update(UpdateSupportContactRequest $request, SupportContact $supportContact): RedirectResponse
    {
        $supportContact->update($request->validated());

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($supportContact)
            ->event('updated')
            ->log('support_contact_updated');

        return redirect()
            ->route('settings.general.index')
            ->with('success', 'settings.general_settings.support_contact_updated');
    }

    public function destroy(Request $request, SupportContact $supportContact): RedirectResponse
    {
        activity('settings')
            ->causedBy($request->user())
            ->performedOn($supportContact)
            ->event('deleted')
            ->log('support_contact_deleted');

        $supportContact->forceDelete();

        return redirect()
            ->route('settings.general.index')
            ->with('success', 'settings.general_settings.support_contact_deleted');
    }
}
