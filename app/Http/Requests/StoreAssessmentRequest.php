<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'age_group' => ['required', 'in:child,teen,adult,elderly'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*' => ['in:none,asthma,heart_disease,respiratory,diabetes'],
            'activity' => ['required', 'in:indoor,light_outdoor,moderate_exercise,strenuous_exercise'],
        ];
    }
}
