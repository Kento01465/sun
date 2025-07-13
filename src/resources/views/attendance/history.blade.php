<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('勤怠履歴') }}
        </h2>
    </x-slot>

    <div class="py-5 bg-light">
        <div class="container">

            <div class="card shadow-sm mb-4">
                <!-- 月選択フォーム -->
                <div class="card-body">
                    <form method="GET" action="{{ route('attendance.history') }}" class="row g-3 align-items-end">
                        <div class="col-auto">
                            <label for="year" class="form-label">年</label>
                            <select name="year" id="year" class="form-select">
                                @for ($y = now()->year; $y >= 2020; $y--)
                                    <option value="{{ $y }}" @if ($year == $y) selected @endif>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-auto">
                            <label for="month" class="form-label">月</label>
                            <select name="month" id="month" class="form-select">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @if ($month == $m) selected @endif>{{ $m }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">表示</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 勤怠履歴テーブル -->
            <div class="card mt-4 shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong class="mx-auto">{{ $year }}年{{ $month }}月の勤怠履歴</strong>
                    <form method="GET" action="{{ route('attendance.export') }}">
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button type="submit" class="btn btn-success">CSV出力</button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped m-0 text-center">
                        <thead class="table-light">
                            <tr>
                                <th>日付</th>
                                <th>出勤時間</th>
                                <th>退勤時間</th>
                                <th>休憩時間</th>
                                <th>勤務時間</th>
                                <th>備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendances as $attendance)
                                <tr>
                                    <td>
                                        @if ($attendance->clock_in)
                                            {{ $attendance->clock_in->format('Y-m-d') }}
                                        @else
                                            {{ $attendance->date->format('Y-m-d') }}
                                        @endif
                                    </td>
                                    <td>{{ optional($attendance->clock_in)->format('H:i') ?? '-' }}</td>
                                    <td>{{ optional($attendance->clock_out)->format('H:i') ?? '-' }}</td>
                                    <td>
                                        @if ($attendance->break_duration)
                                            {{ sprintf('%02d:%02d', intval($attendance->break_duration / 60), $attendance->break_duration % 60) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($attendance->clock_in && $attendance->clock_out)
                                            @php
                                                $totalSeconds = $attendance->clock_in->diffInSeconds($attendance->clock_out);
                                                // 実際の休憩時間を秒に変換（break_durationが分単位の場合）
                                                $breakSeconds = $attendance->break_duration ? $attendance->break_duration * 60 : 0;
                                                $workSeconds = max($totalSeconds - $breakSeconds, 0);

                                                $hours = floor($workSeconds / 3600);
                                                $minutes = floor(($workSeconds % 3600) / 60);
                                                $formattedTime = str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
                                            @endphp
                                            {{ $formattedTime }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $attendance->notes ?? '-' }}</td>
                                </tr>
                            @endforeach

                            <!-- 合計行 -->
                            <tr>
                                <td colspan="4" class="text-end"><strong>合計勤務時間</strong></td>
                                <td><strong>{{ $totalTime }}</strong></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 戻るボタン -->
            <div class="text-center mt-3">
                <a href="{{ route('attendance.index') }}" class="btn btn-secondary">戻る</a>
            </div>

        </div>
    </div>
</x-app-layout>
