<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\Settings\GeneralSettings\FaqResource;
use App\Http\Resources\Settings\GeneralSettings\SupportContactResource;
use App\Http\Resources\Settings\GeneralSettings\TermAndConditionResource;
use App\Models\Faq;
use App\Models\SupportContact;
use App\Models\TermAndCondition;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralSettingController extends Controller
{
    public function termAndCondition(): JsonResource
    {
        $termAndConditions = TermAndCondition::query()->get();

        return TermAndConditionResource::collection($termAndConditions);
    }

    public function faq(): JsonResource
    {
        $faqs = Faq::query()->get();

        return FaqResource::collection($faqs);
    }

    public function supportContact(): JsonResource
    {
        $supportContacts = SupportContact::query()->get();

        return SupportContactResource::collection($supportContacts);
    }
}
