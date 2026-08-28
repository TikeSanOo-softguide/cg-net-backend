<?php

namespace App\Http\Controllers\Api\Contact;

use App\Http\Controllers\Controller;
use App\Http\Resources\Contact\ContactResource;
use App\Models\Contact;

class ContactController extends Controller
{
    public function show()
    {
        $contact = Contact::query()->get();

        return ContactResource::collection($contact);
    }
}