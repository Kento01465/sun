<?php

namespace App\Http\Controllers;

use App\Models\TimeRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * ダッシュボード画面
     */
    public function index()
    {
        $user = Auth::user();

        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $records = TimeRecord::where('user_id', $user->id)
            ->whereBetween('clock_in', [$start, $end])
            ->orderBy('clock_in', 'asc')
            ->get();

        $dates = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates->push($date->copy());
        }

        $attendances = $dates->map(function($date) use ($records) {
            $record = $records->first(function($r) use ($date) {
                return $r->clock_in->toDateString() === $date->toDateString();
            });

            if ($record) {
                return $record;
            } else {
                return (object)[
                    'clock_in' => null,
                    'clock_out' => null,
                    'note' => null,
                    'id' => null,
                    'date' => $date,
                ];
            }
        });

        $totalSeconds = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->clock_in && $attendance->clock_out) {
                $seconds = $attendance->clock_in->diffInSeconds($attendance->clock_out);
                $workSeconds = max($seconds - 3600, 0); // 休憩1時間引く
                $totalSeconds += $workSeconds;
            }
        }

        $totalHours = floor($totalSeconds / 3600);
        $totalMinutes = floor(($totalSeconds % 3600) / 60);
        $totalTime = str_pad($totalHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($totalMinutes, 2, '0', STR_PAD_LEFT);

        return view('attendance.index', compact('attendances', 'totalTime'));
    }

    /**
     * 出勤打刻
     */
    public function clockIn(Request $request)
    {
        $user = Auth::user();

        // すでに出勤中かどうかを確認（例: clock_outがnullのレコードがあれば出勤中）
        $existingRecord = TimeRecord::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest()
            ->first();

        if ($existingRecord) {
            return redirect()->back()->with('error', 'すでに出勤中です。');
        }

        $timeRecord = new TimeRecord();
        $timeRecord->user_id = $user->id;
        $timeRecord->clock_in = now();
        $timeRecord->save();

        return redirect()->back()->with('success', '出勤打刻しました。');
    }

    /**
     * 退勤打刻
     */
    public function clockOut(Request $request)
    {
        $user = Auth::user();

        // 最後の出勤中レコードを取得
        $record = TimeRecord::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest()
            ->first();

        if (!$record) {
            return redirect()->back()->with('error', '出勤記録が見つかりません。');
        }

        $record->clock_out = now();
        $record->save();

        return redirect()->back()->with('success', '退勤打刻しました。');
    }

    public function edit($id)
    {
        $attendance = TimeRecord::findOrFail($id);
        return view('attendance.edit', compact('attendance'));
    }

    public function update(Request $request, $id)
    {
        $attendance = TimeRecord::findOrFail($id);
        $attendance->clock_in = $request->input('clock_in');
        $attendance->clock_out = $request->input('clock_out');
        $attendance->notes = $request->input('notes');
        $attendance->save();

        return redirect()->route('attendance.index')->with('success', '勤怠情報を更新しました。');
    }

    public function destroy($id)
    {
        $attendance = TimeRecord::findOrFail($id);
        $attendance->delete();

        return redirect()->route('attendance.index')->with('success', '勤怠情報を削除しました。');
    }

    /**
     * ダッシュボード
     */
    public function dashboard()
    {
        $today = now()->startOfDay();

        // ログインユーザーのチームメンバーを取得（例：同部署のユーザー）
        $teamMembers = User::where('department', auth()->user()->department)->get();

        // 各メンバーの本日の勤怠を取得（存在しなければnull）
        $attendances = [];

        foreach ($teamMembers as $member) {
            $attendance = TimeRecord::where('user_id', $member->id)
                ->whereDate('clock_in', $today)
                ->first();

            if (!$attendance) {
                // 空データを用意（必要に応じて）
                $attendance = new TimeRecord();
                $attendance->user_id = $member->id;
                $attendance->user = $member;
                $attendance->clock_in = null;
                $attendance->clock_out = null;
                $attendance->notes = null;
                $attendance->date = $today;
            } else {
                // リレーションでユーザー情報がない場合はセット
                $attendance->user = $member;
            }

            $attendances[] = $attendance;
        }

        return view('attendance.dashboard', compact('attendances'));
    }


    /**
     * 履歴表示
     */
    public function history(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $startOfMonth = Carbon::create($year, $month)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month)->endOfMonth();

        $dates = collect();
        for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
            $dates->push($date->copy());
        }

        $records = TimeRecord::where('user_id', Auth::id())
            ->whereBetween('clock_in', [$startOfMonth, $endOfMonth])
            ->get();

        $attendances = $dates->map(function ($date) use ($records) {
            $record = $records->first(function ($r) use ($date) {
                return $r->clock_in && $r->clock_in->format('Y-m-d') === $date->format('Y-m-d');
            });

            if ($record) {
                return $record;
            } else {
                $dummy = new \stdClass();
                $dummy->date = $date;
                $dummy->clock_in = null;
                $dummy->clock_out = null;
                $dummy->notes = null;
                $dummy->id = null;
                return $dummy;
            }
        });

        $totalSeconds = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->clock_in && $attendance->clock_out) {
                $seconds = $attendance->clock_in->diffInSeconds($attendance->clock_out);
                $workSeconds = max($seconds - 3600, 0); // 休憩1時間引く
                $totalSeconds += $workSeconds;
            }
        }

        $totalHours = floor($totalSeconds / 3600);
        $totalMinutes = floor(($totalSeconds % 3600) / 60);
        $totalTime = str_pad($totalHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($totalMinutes, 2, '0', STR_PAD_LEFT);

        return view('attendance.history', compact('attendances', 'year', 'month', 'totalTime'));
    }


    /**
     * CSVエクスポート
     */
    public function export(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $user = Auth::user();

        $records = TimeRecord::where('user_id', $user->id)
            ->whereBetween('clock_in', [$start, $end])
            ->orderBy('clock_in', 'asc')
            ->get()
            ->groupBy(function ($item) {
                return $item->clock_in->format('Y-m-d');
            });

        $csvHeader = ["氏名", "部署", "日付", "出勤時間", "退勤時間", "勤務時間", "休憩時間", "備考"];
        $csvData = [];

        $daysInMonth = $start->daysInMonth;

        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $start->copy()->addDays($i);
            $dateKey = $date->format('Y-m-d');

            if (isset($records[$dateKey])) {
                $record = $records[$dateKey]->first();
                $clockIn = $record->clock_in ? $record->clock_in->format('H:i') : '-';
                $clockOut = $record->clock_out ? $record->clock_out->format('H:i') : '-';

                // 勤務時間計算
                if ($record->clock_in && $record->clock_out) {
                    $totalSeconds = $record->clock_in->diffInSeconds($record->clock_out);
                    $breakSeconds = 3600; // 1時間固定
                    $workSeconds = max($totalSeconds - $breakSeconds, 0);
                    $hours = floor($workSeconds / 3600);
                    $minutes = floor(($workSeconds % 3600) / 60);
                    $workTime = sprintf('%02d:%02d', $hours, $minutes);
                    $breakTime = "01:00";
                } else {
                    $workTime = "-";
                    $breakTime = "-";
                }

                $note = $record->notes ?? "-";
            } else {
                $clockIn = "-";
                $clockOut = "-";
                $workTime = "-";
                $breakTime = "-";
                $note = "-";
            }

            $csvData[] = [
                $user->name,
                $user->department ?? "-",
                $dateKey,
                $clockIn,
                $clockOut,
                $workTime,
                $breakTime,
                $note,
            ];
        }

        $filename = "attendance_{$year}_{$month}.csv";

        ob_start();
        $handle = fopen('php://output', 'w');
        fputcsv($handle, $csvHeader);
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $csvContent = ob_get_clean();

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
}
