@extends('user.attendance.detail')

@section('detail_setup')
  @php
    $editable       = true;  // 管理者は常に編集可
    $action         = route('admin.attendances.update', ['id' => $attendance->id]);
    $method         = 'PUT';
    $pendingMessage = null;  // 管理者は注意書き不要
  @endphp
@endsection