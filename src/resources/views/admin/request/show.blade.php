@extends('layouts.app')

@section('title', '勤怠詳細')

@section('content')
  <h1 class="section-title">勤怠詳細</h1>

  {{-- 承認状態で切替（boolean または status文字列など、プロジェクト側の実装に合わせて） --}}
  @php
    // 例：$requestItem->approved が true なら承認済み
    $isApproved = (bool)($requestItem->approved ?? false);
  @endphp

  {{-- 明細カード --}}
  <div class="detail-card">
    <div class="detail-row">
      <div class="detail-label">名前</div>
      <div class="detail-value">{{ $requestItem->user_name }}</div>
    </div>

    <div class="detail-row">
      <div class="detail-label">日付</div>
      <div class="detail-value">
        {{ \Carbon\Carbon::parse($requestItem->date)->format('Y年') }}
        <span class="detail-gap"></span>
        {{ \Carbon\Carbon::parse($requestItem->date)->format('n月j日') }}
      </div>
    </div>

    <div class="detail-row">
      <div class="detail-label">出勤・退勤</div>
      <div class="detail-value">
        {{ \Carbon\Carbon::parse($requestItem->clock_in)->format('H:i') }}
        <span class="tilde">〜</span>
        {{ \Carbon\Carbon::parse($requestItem->clock_out)->format('H:i') }}
      </div>
    </div>

    <div class="detail-row">
      <div class="detail-label">休憩</div>
      <div class="detail-value">
        @if($requestItem->break_start && $requestItem->break_end)
          {{ \Carbon\Carbon::parse($requestItem->break_start)->format('H:i') }}
          <span class="tilde">〜</span>
          {{ \Carbon\Carbon::parse($requestItem->break_end)->format('H:i') }}
        @endif
      </div>
    </div>

    <div class="detail-row">
      <div class="detail-label">休憩2</div>
      <div class="detail-value">
        @if($requestItem->break2_start && $requestItem->break2_end)
          {{ \Carbon\Carbon::parse($requestItem->break2_start)->format('H:i') }}
          <span class="tilde">〜</span>
          {{ \Carbon\Carbon::parse($requestItem->break2_end)->format('H:i') }}
        @endif
      </div>
    </div>

    <div class="detail-row memo-row">
      <div class="detail-label">備考</div>
      <div class="detail-value">{{ $requestItem->note ?? '' }}</div>
    </div>
  </div>

  {{-- ボタン領域（右下） --}}
  <div class="detail-actions">
    @if(!$isApproved)
      <form action="{{ route('admin.request.approve', ['id' => $requestItem->id]) }}" method="POST" onsubmit="return confirm('この申請を承認しますか？');">
        @csrf
        <button type="submit" class="btn-primary">承認</button>
      </form>
    @else
      <button type="button" class="btn-disabled" disabled>承認済み</button>
    @endif
  </div>
@endsection
