<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreContactRequest;
use App\Http\Requests\Cms\UpdateContactRequest;
use App\Models\Contact;
use App\Support\CmsListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            Contact::query(),
            ['contact_point'],
            ['contact_point', 'created_at'],
            hasLang: false,
        );

        return Inertia::render('Cms/Contacts/Index', [
            'items' => $listing['paginator']->through(fn (Contact $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/Contacts/Create');
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $contact = Contact::query()->create($request->validated());

        activity('cms')->causedBy($request->user())->performedOn($contact)->event('created')->log('contact_created');

        return redirect()->route('cms.contacts.index')->with('success', 'cms.created');
    }

    public function edit(Contact $contact): Response
    {
        return Inertia::render('Cms/Contacts/Edit', [
            'item' => $this->payload($contact),
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        activity('cms')->causedBy($request->user())->performedOn($contact)->event('updated')->log('contact_updated');

        return redirect()->route('cms.contacts.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $contact->delete();

        activity('cms')->causedBy($request->user())->performedOn($contact)->event('deleted')->log('contact_deleted');

        return redirect()->route('cms.contacts.index')->with('success', 'cms.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'contact_point' => $contact->contact_point,
            'created_at' => $contact->created_at?->toDateString(),
        ];
    }
}
