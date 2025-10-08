<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrectionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clock_in_at'  => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'breaks'       => ['array'], 
            'reason'       => ['required', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $in  = $this->input('clock_in_at');
            $out = $this->input('clock_out_at');
            if ($in && $out && $in > $out) {
                $v->errors()->add('clock_in_at', '出勤時間もしくは退勤時間が不適切な値です');
            }
            foreach ($this->input('breaks', []) as $br) {
                if (!empty($br['start']) && !empty($br['end']) && $out && $br['end'] > $out) {
                    $v->errors()->add('breaks', '休憩時間もしくは退勤時間が不適切な値です');
                }
                if (!empty($br['start']) && $in && $br['start'] < $in) {
                    $v->errors()->add('breaks', '休憩時間が不適切な値です');
                }
            }
        });
    }
    public function messages(): array
    {
        return [
            'reason.required' => '備考を記入してください',
        ];
    }
}
