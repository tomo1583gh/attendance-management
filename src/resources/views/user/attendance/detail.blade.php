@extends('layouts.app')
@section('title', '勤怠詳細')

@php
  // 既存のロジックを尊重（無ければ false）
  $isPending = isset($isPending)
      ? (bool)$isPending
      : ((session('pending') === true) || ($hasPending ?? false));

  // デフォルト設定（管理者側で上書き可能）
  $editable       = $editable       ?? ! $isPending;                          // 一般は承認待ちなら編集不可
  $action         = $action         ?? route('request.store', $attendance->id);
  $method         = $method         ?? 'POST';
  $pendingMessage = $pendingMessage ?? ($isPending ? '※ 承認待ちのため修正はできません。' : null);
@endphp

{{-- 派生側（管理者）がここで変数を上書きできる --}}
@yield('detail_setup')

@section('content')
<div class="container">
  <h1 class="section-title">勤怠詳細</h1>

  @if (session('status'))
    <div class="alert-success" role="status">{{ session('status') }}</div>
  @endif

  @php
  // 出退勤の表示用
  $in  = optional($attendance->clock_in_at)->format('H:i');
  $out = optional($attendance->clock_out_at)->format('H:i');

  $userName = optional($attendance->user)->name ?? (auth()->user()->name ?? '');

  // Controller から $prefillBreaks, $nextIndex が来ていればそれを優先
  // 無ければここで $attendance->breaks から配列化
  $prefill = $prefillBreaks
      ?? $attendance->breaks->map(function ($b) {
            return [
              'id'    => $b->id,
              'start' => optional($b->start_at)->format('H:i'),
              'end'   => optional($b->end_at)->format('H:i'),
            ];
        })->toArray();

  // バリデーションで戻ったときは old() を優先
  $rows = old('breaks', $prefill);

  // “空1行”のインデックス
  $new  = isset($nextIndex) ? $nextIndex : count($rows);
@endphp

  <form action="{{ $action }}" method="post" class="detail-form" @if(!$editable) aria-disabled="true" @endif>
    @csrf
    @isset($method) @method($method) @endisset

    <div class="detail-card">
      {{-- 名前 --}}
      <div class="detail-row">
        <div class="detail-label">名前</div>
        <div class="detail-field"><span class="detail-text">{{ $userName }}</span></div>
      </div>

      {{-- 日付 --}}
      <div class="detail-row">
        <div class="detail-label">日付</div>
        <div class="detail-field date-split">
          <span class="date-year">{{ optional($attendance->work_date)->format('Y') }}年</span>
          <span class="date-monthday">{{ optional($attendance->work_date)->format('n月j日') }}</span>
        </div>
      </div>

      {{-- 出勤・退勤 --}}
      <div class="detail-row">
        <div class="detail-label">出勤・退勤</div>
        <div class="detail-field time-range">
          @if($editable)
            <input type="time" name="clock_in"  value="{{ old('clock_in',  $in)  }}" class="input-time time-input">
            @error('clock_in')  <p class="form-error">{{ $message }}</p> @enderror
            <span class="time-tilde tilde">〜</span>
            <input type="time" name="clock_out" value="{{ old('clock_out', $out) }}" class="input-time time-input">
            @error('clock_out') <p class="form-error">{{ $message }}</p> @enderror
          @else
            <span class="detail-text">{{ $in  ?: '—' }}</span>
            <span class="time-tilde tilde">〜</span>
            <span class="detail-text">{{ $out ?: '—' }}</span>
          @endif
        </div>
      </div>

      {{-- 休憩（可変行） --}}
@foreach ($rows as $i => $b)
  <div class="detail-row">
    <div class="detail-label">休憩{{ $i + 1 }}</div>
    <div class="detail-field time-range">
      @if($editable)
        <input type="hidden" name="breaks[{{ $i }}][id]" value="{{ $b['id'] ?? '' }}">
        <input type="time" name="breaks[{{ $i }}][start]" value="{{ $b['start'] ?? '' }}" class="input-time time-input">
        @error("breaks.$i.start") <p class="form-error">{{ $message }}</p> @enderror
        <span class="time-tilde tilde">〜</span>
        <input type="time" name="breaks[{{ $i }}][end]"   value="{{ $b['end']   ?? '' }}" class="input-time time-input">
        @error("breaks.$i.end")   <p class="form-error">{{ $message }}</p> @enderror
      @else
        <span class="detail-text">{{ ($b['start'] ?? '') ?: '—' }}</span>
        <span class="time-tilde tilde">〜</span>
        <span class="detail-text">{{ ($b['end']   ?? '') ?: '—' }}</span>
      @endif
    </div>
  </div>
@endforeach

{{-- 空1行（新規入力用） --}}
<div class="detail-row">
  <div class="detail-label">休憩{{ $new + 1 }}</div>
  <div class="detail-field time-range">
    @if($editable)
      <input type="time" name="breaks[{{ $new }}][start]" value="{{ old('breaks.'.$new.'.start', '') }}" class="input-time time-input">
      <span class="time-tilde tilde">〜</span>
      <input type="time" name="breaks[{{ $new }}][end]"   value="{{ old('breaks.'.$new.'.end',   '') }}" class="input-time time-input">

      @error("breaks.$new.start") <p class="form-error">{{ $message }}</p> @enderror
      @error("breaks.$new.end")   <p class="form-error">{{ $message }}</p> @enderror
    @else
      <span class="detail-text">—</span>
      <span class="time-tilde tilde">〜</span>
      <span class="detail-text">—</span>
    @endif
  </div>
</div>

      {{-- 備考 --}}
      <div class="detail-row">
        <div class="detail-label">備考</div>
        <div class="detail-field stack">
          @if($editable)
            <textarea name="note" class="input-note note-input" rows="3" placeholder="備考を入力してください">{{ old('note', $attendance->note) }}</textarea>
            @error('note') <p class="form-error">{{ $message }}</p> @enderror
          @else
            <span class="detail-text">{{ $attendance->note ?: '—' }}</span>
          @endif
        </div>
      </div>
    </div>

    @if($editable)
      <div class="detail-actions">
        <button type="submit" class="btn-primary">修正</button>
      </div>
    @elseif(!empty($pendingMessage))
      <div class="detail-footer">
        <p class="pending-note" role="alert">{{ $pendingMessage }}</p>
      </div>
    @endif
  </form>
</div>
@endsection
