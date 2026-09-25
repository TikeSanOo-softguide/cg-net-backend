<?php

namespace App\Http\Requests\TopUpCard;

use Illuminate\Foundation\Http\FormRequest;

class DeleteBatchCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('top-up-cards.delete') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
