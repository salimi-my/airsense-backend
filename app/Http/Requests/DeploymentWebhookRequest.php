<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeploymentWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:success,failed'],
            'message' => ['required', 'string', 'max:1000'],
            'duration' => ['required', 'integer', 'min:0'],
            'timestamp' => ['required', 'string'],
            'app_name' => ['nullable', 'string', 'max:100'],
            'commit_url' => ['nullable', 'string', 'url', 'max:500'],
            'commit_author' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Deployment status is required.',
            'status.in' => 'Status must be either "success" or "failed".',
            'message.required' => 'Deployment message is required.',
            'duration.required' => 'Deployment duration is required.',
            'duration.integer' => 'Duration must be a valid integer.',
            'timestamp.required' => 'Deployment timestamp is required.',
        ];
    }
}
