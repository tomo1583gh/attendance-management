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

  @if ($errors->any())
    <div class="alert-error" role="alert">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('status'))
    <div class="alert-success" role="status">{{ session('status') }}</div>
  @endif

  @php
    // 関連は Controller 側で with(['user','breaks']) 推奨
    $b0  = $attendance->breaks->get(0);
    $b1  = $attendance->breaks->get(1);

    $in  = optional($attendance->clock_in_at)->format('H:i');
    $out = optional($attendance->clock_out_at)->format('H:i');

    $b1s = optional($b0?->start_at)->format('H:i');
    $b1e = optional($b0?->end_at)->format('H:i');
    $b2s = optional($b1?->start_at)->format('H:i');
    $b2e = optional($b1?->end_at)->format('H:i');

    $userName = optional($attendance->user)->name ?? (auth()->user()->name ?? '');
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
            <span class="time-tilde tilde">〜</span>
            <input type="time" name="clock_out" value="{{ old('clock_out', $out) }}" class="input-time time-input">
          @else
            <span class="detail-text">{{ $in  ?: '—' }}</span>
            <span class="time-tilde tilde">〜</span>
            <span class="detail-text">{{ $out ?: '—' }}</span>
          @endif
        </div>
      </div>

      {{-- 休憩 --}}
      <div class="detail-row">
        <div class="detail-label">休憩</div>
        <div class="detail-field time-range">
          @if($editable)
            <input type="time" name="breaks[0][start]" value="{{ old('breaks.0.start', $b1s) }}" class="input-time time-input">
            <span class="time-tilde tilde">〜</span>
            <input type="time" name="breaks[0][end]"   value="{{ old('breaks.0.end',   $b1e) }}" class="input-time time-input">
          @else
            <span class="detail-text">{{ $b1s ?: '—' }}</span>
            <span class="time-tilde tilde">〜</span>
            <span class="detail-text">{{ $b1e ?: '—' }}</span>
          @endif
        </div>
      </div>

      {{-- 休憩2 --}}
      <div class="detail-row">
        <div class="detail-label">休憩2</div>
        <div class="detail-field time-range">
          @if($editable)
            <input type="time" name="breaks[1][start]" value="{{ old('breaks.1.start', $b2s) }}" class="input-time time-input">
            <span class="time-tilde tilde">〜</span>
            <input type="time" name="breaks[1][end]"   value="{{ old('breaks.1.end',   $b2e) }}" class="input-time time-input">
          @else
            <span class="detail-text">{{ $b2s ?: '—' }}</span>
            <span class="time-tilde tilde">〜</span>
            <span class="detail-text">{{ $b2e ?: '—' }}</span>
          @endif
        </div>
      </div>

      {{-- 備考 --}}
      <div class="detail-row">
        <div class="detail-label">備考</div>
        <div class="detail-field">
          @if($editable)
            <textarea name="note" class="input-note note-input" rows="3" placeholder="備考を入力してください">{{ old('note', $attendance->note) }}</textarea>
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
