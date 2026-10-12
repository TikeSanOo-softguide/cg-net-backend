<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class CreateServiceRequest extends FormRequest
{
    /**
     * @return class-string<Model>
     */
    abstract protected function requestModel(): string;

    protected function pendingRequestColumn(): string
    {
        return 'broadband_account_number';
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $requestModel = $this->requestModel();
            $column = $this->pendingRequestColumn();
            $identifier = $column === 'user_id' ? $user?->getAuthIdentifier() : $this->input($column);

            if (
                $user !== null &&
                $identifier !== null &&
                $identifier !== '' &&
                $requestModel::query()
                    ->where($column, $identifier)
                    ->where('status', RequestStatus::UnderReview->value)
                    ->exists()
            ) {
                $validator->errors()->add('request', __('service_request.pending_request_exists'));
            }
        });
    }
}
