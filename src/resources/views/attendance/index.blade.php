<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('勤怠管理') }}
        </h2>
    </x-slot>

    <div class="py-5 bg-light">
        <div class="container">
            <!-- 大きな時計カード -->
            <div class="card text-center shadow" style="background-color: #b5d334; color: white;">
                <div class="card-body py-5">
                    <div style="font-size: 1.5rem;">{{ now()->timezone('Asia/Tokyo')->format('Y年 m月d日 l') }}</div>
                    <div id="current-time" style="font-size: 3rem; font-weight: bold;"></div>
                </div>
            </div>

            <!-- 打刻カード -->
            <div class="card shadow-lg text-center">
                <div class="card-body p-5">
                    <h3 class="text-muted mb-4">打刻</h3>
                    <div class="d-flex justify-content-center gap-4 mb-4 flex-wrap">
                        <!-- 出勤打刻 -->
                        <form method="POST" action="{{ route('attendance.clockin') }}">
                            @csrf
                            <button type="submit" class="btn btn-success d-flex flex-column align-items-center p-3">
                                出勤
                            </button>
                        </form>

                        <!-- 退勤打刻 -->
                        <form method="POST" action="{{ route('attendance.clockout') }}">
                            @csrf
                            <button type="submit" class="btn btn-danger d-flex flex-column align-items-center p-3">
                                退勤
                            </button>
                        </form>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- 今月の勤怠情報 -->
            <div class="card mt-4 shadow">
                <div class="card-header text-center">
                    <strong>今月の勤怠</strong>
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
                                <th>操作</th>
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
                                    <td>
                                        @if ($attendance->id)
                                            <a href="{{ route('attendance.edit', $attendance->id) }}" class="btn btn-sm btn-primary">編集</a>

                                            <form action="{{ route('attendance.destroy', $attendance->id) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('本当に削除しますか？');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">削除</button>
                                            </form>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            <!-- 合計行 -->
                            <tr>
                                <td colspan="4" class="text-end"><strong>合計勤務時間</strong></td>
                                <td><strong>{{ $totalTime }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            const formatted = now.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('current-time').textContent = formatted;
        }
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</x-app-layout>
