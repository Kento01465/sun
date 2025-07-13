<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('ダッシュボード') }}
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

            <div class="card mt-4 shadow">
                <div class="card-header text-center">
                    <strong>{{ now()->timezone('Asia/Tokyo')->format('Y年m月d日') }}の勤怠</strong>
                </div>
		        <div class="card-body p-5">
		            <table class="table table-striped m-0 text-center">
		                <thead>
		                    <tr>
		                        <th>名前</th>
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
		                            <td>{{ $attendance->user->name ?? '不明' }}</td>
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
		                                    {{ $attendance->clock_out->diff($attendance->clock_in)->format('%H:%I') }}
		                                @else
		                                    -
		                                @endif
		                            </td>
		                            <td>{{ $attendance->notes ?? '-' }}</td>
		                        </tr>
		                    @endforeach
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
