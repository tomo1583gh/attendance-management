<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class AttendanceDetailUpdateRequest extends FormRequest
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
            // time入力なら H:i を想定。datetime-local なら date_format:Y-m-d\TH:i に変更
            'clock_in'  => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],

            'breaks'              => ['array'],
            'breaks.*.id'         => ['nullable', 'integer'],
            'breaks.*.start'      => ['nullable', 'date_format:H:i'],
            'breaks.*.end'        => ['nullable', 'date_format:H:i'],

            // 4) 備考は必須（要件より）
            'note'                => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // 1) 出勤/退勤の逆転
            'clock_out.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',

            // 4) 備考未入力
            'note.required' => '備考を記入してください',

            // date_format のメッセージはUI上は不要なので省略（ブラウザのバリデーションに任せる）
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $in  = $this->input('clock_in');   // "HH:MM"
            $out = $this->input('clock_out');  // "HH:MM"

            $toMin = function (?string $hm) {
                if (!$hm) return null;
                [$h, $m] = array_pad(explode(':', $hm), 2, 0);
                return ((int)$h) * 60 + (int)$m;
            };

            $inMin  = $toMin($in);
            $outMin = $toMin($out);

            // 1) 出勤 > 退勤 or 退勤 < 出勤
            if (!is_null($inMin) && !is_null($outMin) && $inMin > $outMin) {
                // どちらに出してもOK。ここでは退勤側に付けます
                $v->errors()->add('clock_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            $breaks = $this->input('breaks', []);

            foreach ($breaks as $idx => $b) {
                $bs = $toMin($b['start'] ?? null);
                $be = $toMin($b['end'] ?? null);

                // 2) 休憩開始が出勤より前 or 退勤より後
                if (!is_null($bs)) {
                    if (!is_null($inMin)  && $bs <  $inMin) {
                        $v->errors()->add("breaks.$idx.start", '休憩時間が不適切な値です');
                    }
                    if (!is_null($outMin) && $bs >  $outMin) {
                        $v->errors()->add("breaks.$idx.start", '休憩時間が不適切な値です');
                    }
                }

                // 3) 休憩終了が退勤より後
                if (!is_null($be) && !is_null($outMin) && $be > $outMin) {
                    $v->errors()->add("breaks.$idx.end", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }
}
