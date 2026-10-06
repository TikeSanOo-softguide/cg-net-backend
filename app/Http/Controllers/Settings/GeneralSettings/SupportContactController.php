<?php

namespace App\Http\Controllers\Settings\GeneralSettings;

use App\Http\Controllers\Controller;
use App\Models\SupportContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $supportContact = SupportContact::query()->create($request->validate($this->rules()));

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($supportContact)
            ->event('created')
            ->log('support_contact_created');

        return redirect()
            ->route('settings.general.index')
            ->with('success', 'settings.general_settings.support_contact_created');
    }

    public function update(Request $request, SupportContact $supportContact): RedirectResponse
    {
        $supportContact->update($request->validate($this->rules()));

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
        $supportContact->delete();

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($supportContact)
            ->event('deleted')
            ->log('support_contact_deleted');

        return redirect()
            ->route('settings.general.index')
            ->with('success', 'settings.general_settings.support_contact_deleted');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
