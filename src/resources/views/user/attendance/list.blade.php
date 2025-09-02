@extends('layouts.app')

@section('title', '勤怠一覧')
@section('state', 'before') {{-- レイアウトのクラス用（見た目だけなので固定でOK） --}}

@section('content')
  {{-- タイトル（左に細いバー） --}}
  <h1 class="section-title">勤怠一覧</h1>

  {{-- 月切替：前月 / YYYY/MM / 翌月 --}}
  <div class="month-switch">
    <a class="month-btn" href="{{ route('attendance.list', ['month' => $prevMonth]) }}">← 前月</a>

    <div class="month-display">
      <span aria-hidden="true">📅</span>
      <span>{{ $currentYm }}</span> {{-- 例: 2023/06 --}}
    </div>

    <a class="month-btn" href="{{ route('attendance.list', ['month' => $nextMonth]) }}">翌月 →</a>
  </div>

  {{-- 一覧テーブル --}}
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
            // $row->date は Carbon 前提
            $w = ['日','月','火','水','木','金','土'][$row->date->dayOfWeek];
          @endphp
          <tr>
            <td>{{ $row->date->format('m/d') }}({{ $w }})</td>
            <td>{{ $row->in_time  ?? '' }}</td>
            <td>{{ $row->out_time ?? '' }}</td>
            <td>{{ $row->break_text ?? '' }}</td>  {{-- 例: 1:00 --}}
            <td>{{ $row->total_text ?? '' }}</td>  {{-- 例: 8:00 --}}
            <td>
              @if(!empty($row->attendance_id))
                <a class="link-detail" href="{{ route('attendance.detail', $row->attendance_id) }}">詳細</a>
              @else
                {{-- レコード未作成日。必要なら date をクエリで渡す --}}
                <a class="link-detail" href="{{ route('attendance.detail', 0) }}?date={{ $row->date->toDateString() }}">詳細</a>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td class="empty" colspan="6">データがありません</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
