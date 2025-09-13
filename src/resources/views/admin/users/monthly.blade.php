@extends('layouts.app')

@section('title', $titleName . 'さんの勤怠')
@section('state', 'before')

@section('content')
  {{-- タイトル（一般のlist.blade.phpと同じ見出しスタイル） --}}
  <h1 class="section-title">{{ $titleName }}さんの勤怠</h1>

  {{-- 月切替：前月 / YYYY/MM / 翌月（クラス名も流用） --}}
  <div class="month-switch-area">
    <div class="month-switch">
      <a class="month-btn" href="{{ route('attendance.list', ['month' => $prevMonth]) }}">← 前月</a>

      <div class="month-display">
        <span aria-hidden="true">📅</span>
        <span>{{ $currentYm }}</span>
      </div>

      <a class="month-btn" href="{{ route('attendance.list', ['month' => $nextMonth]) }}">翌月 →</a>
    </div>
  </div>

  {{-- 一覧テーブル（クラス名を共通化） --}}
  <div class="card table-wrap">
    <table class="table table--attend">
      <thead>
        <tr>
          <th>日付</th>
          <th>出勤</th>
          <th>退勤</th>
          <th>休憩</th>
          <th>合計</th>
          <th>詳細</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $row)
          @php
            // 一般側 list.blade.php と同じロジックで日本語曜日を算出
            $w = ['日','月','火','水','木','金','土'][$row->date->dayOfWeek];
          @endphp
          <tr>
            <td>{{ $row->date->format('m/d') }}（{{ $w }}）</td>
            <td>{{ $row->in_time  ?? '' }}</td>
            <td>{{ $row->out_time ?? '' }}</td>
            <td>{{ $row->break_text ?? '' }}</td>
            <td>{{ $row->total_text ?? '' }}</td>
            <td>
              @if(!empty($row->attendance_id))
                <a class="link-detail"
                   href="{{ route('admin.attendances.show', ['id' => $row->attendance_id]) }}">詳細</a>
              @else
                <span class="link-detail" style="opacity:.5;cursor:not-allowed;">詳細</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td class="empty" colspan="6">表示する勤怠がありません</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- CSV出力（共通スタイル + 追加クラス） --}}
  <div class="footer-actions footer-actions--right">
    <a class="btn-outline"
       href="{{ route('admin.users.attendances.csv', ['user' => $user->id, 'month' => $month]) }}">CSV出力</a>
  </div>
@endsection
