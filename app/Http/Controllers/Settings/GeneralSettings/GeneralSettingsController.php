<?php

namespace App\Http\Controllers\Settings\GeneralSettings;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\SupportContact;
use App\Models\TermAndCondition;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/GeneralSettings/Index', [
            'termsAndConditions' => TermAndCondition::query()
                ->orderBy('id')
                ->get(['id', 'title_en', 'title_zh', 'title_my', 'description_en', 'description_zh', 'description_my']),
            'faqs' => Faq::query()
                ->orderBy('id')
                ->get(['id', 'title_en', 'title_zh', 'title_my', 'description_en', 'description_zh', 'description_my']),
            'supportContacts' => SupportContact::query()
                ->orderBy('id')
                ->get(['id', 'phone']),
        ]);
    }
}
