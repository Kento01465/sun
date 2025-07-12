<x-app-layout>
    <x-slot name="header">
        <h2>勤怠編集</h2>
    </x-slot>

    <form action="{{ route('attendance.update', $attendance->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label>出勤時間</label>
            <input type="datetime-local" name="clock_in" value="{{ $attendance->clock_in ? $attendance->clock_in->format('Y-m-d\TH:i') : '' }}" class="form-control">
        </div>

        <div class="mb-3">
            <label>退勤時間</label>
            <input type="datetime-local" name="clock_out" value="{{ $attendance->clock_out ? $attendance->clock_out->format('Y-m-d\TH:i') : '' }}" class="form-control">
        </div>

        <div class="mb-3">
            <label>備考</label>
            <textarea name="notes" class="form-control">{{ $attendance->notes }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">更新</button>
        <a href="{{ route('attendance.index') }}" class="btn btn-secondary">キャンセル</a>
    </form>
</x-app-layout>
