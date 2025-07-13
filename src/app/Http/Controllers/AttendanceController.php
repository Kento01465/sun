<?php

namespace App\Http\Controllers;

use App\Models\TimeRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function __construct()
    {
        // 認証チェック
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 定数の初期化
        $this->user = Auth::user();
        $this->currentDate = Carbon::now();
    }

    /**
     * 共通：勤務時間計算
     */
    protected function calculateWorkTime($clockIn, $clockOut, $breakMinutes = 0)
    {
        if (!$clockIn || !$clockOut) {
            return null;
        }

        $totalSeconds = $clockIn->diffInSeconds($clockOut);
        $breakSeconds = $breakMinutes * 60;
        $workSeconds = max($totalSeconds - $breakSeconds, 0);

        return [
            'total_seconds' => $totalSeconds,
            'work_seconds' => $workSeconds,
            'formatted' => $this->formatTime($workSeconds)
        ];
    }

    /**
     * 共通：時間フォーマット
     */
    protected function formatTime($totalSeconds)
    {
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
    }

    /**
     * 共通：期間の日付範囲生成
     */
    protected function generateDateRange($start, $end)
    {
        $dates = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates->push($date->copy());
        }
        return $dates;
    }

    /**
     * 共通：勤怠データマッピング
     */
    protected function mapAttendanceData($dates, $records)
    {
        return $dates->map(function($date) use ($records) {
            $record = $records->first(function($r) use ($date) {
                return $r->clock_in && $r->clock_in->toDateString() === $date->toDateString();
            });

            if ($record) {
                return $record;
            } else {
                return (object)[
                    'clock_in' => null,
                    'clock_out' => null,
                    'break_duration' => null,
                    'notes' => null,
                    'id' => null,
                    'date' => $date,
                ];
            }
        });
    }

    /**
     * 共通：合計勤務時間計算
     */
    protected function calculateTotalWorkTime($attendances)
    {
        $totalSeconds = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->clock_in && $attendance->clock_out) {
                $workTime = $this->calculateWorkTime(
                    $attendance->clock_in,
                    $attendance->clock_out,
                    $attendance->break_duration ?? 0
                );
                if ($workTime) {
                    $totalSeconds += $workTime['work_seconds'];
                }
            }
        }

        return $this->formatTime($totalSeconds);
    }

    /**
     * 共通：出勤中レコード取得
     */
    protected function getCurrentWorkingRecord()
    {
        return TimeRecord::where('user_id', $this->user->id)
            ->whereNull('clock_out')
            ->latest()
            ->first();
    }

    /**
     * 共通：月間レコード取得
     */
    protected function getMonthlyRecords($year = null, $month = null)
    {
        $year = $year ?? $this->currentDate->year;
        $month = $month ?? $this->currentDate->month;

        $start = Carbon::create($year, $month)->startOfMonth();
        $end = Carbon::create($year, $month)->endOfMonth();

        return [
            'records' => TimeRecord::where('user_id', $this->user->id)
                ->whereBetween('clock_in', [$start, $end])
                ->orderBy('clock_in', 'asc')
                ->get(),
            'start' => $start,
            'end' => $end
        ];
    }

    /**
     * ダッシュボード画面
     */
    public function index()
    {
        $monthlyData = $this->getMonthlyRecords();
        $dates = $this->generateDateRange($monthlyData['start'], $monthlyData['end']);
        $attendances = $this->mapAttendanceData($dates, $monthlyData['records']);
        $totalTime = $this->calculateTotalWorkTime($attendances);

        return view('attendance.index', compact('attendances', 'totalTime'));
    }

    /**
     * 出勤打刻
     */
    public function clockIn(Request $request)
    {
        $existingRecord = $this->getCurrentWorkingRecord();

        if ($existingRecord) {
            return redirect()->back()->with('error', 'すでに出勤中です。');
        }

        $timeRecord = new TimeRecord();
        $timeRecord->user_id = $this->user->id;
        $timeRecord->clock_in = $this->currentDate;
        $timeRecord->save();

        return redirect()->back()->with('success', '出勤打刻しました。');
    }

    /**
     * 退勤打刻
     */
    public function clockOut(Request $request)
    {
        $record = $this->getCurrentWorkingRecord();

        if (!$record) {
            return redirect()->back()->with('error', '出勤記録が見つかりません。');
        }

        $record->clock_out = $this->currentDate;
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
        $attendance->break_duration = $request->input('break_duration');
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
        $today = $this->currentDate->startOfDay();

        // チームメンバーを取得
        $teamMembers = User::where('department', $this->user->department)->get();

        $attendances = [];
        foreach ($teamMembers as $member) {
            $attendance = TimeRecord::where('user_id', $member->id)
                ->whereDate('clock_in', $today)
                ->first();

            if (!$attendance) {
                $attendance = new TimeRecord();
                $attendance->user_id = $member->id;
                $attendance->user = $member;
                $attendance->clock_in = null;
                $attendance->clock_out = null;
                $attendance->break_duration = null;
                $attendance->notes = null;
                $attendance->date = $today;
            } else {
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
        $year = $request->input('year', $this->currentDate->year);
        $month = $request->input('month', $this->currentDate->month);

        $monthlyData = $this->getMonthlyRecords($year, $month);
        $dates = $this->generateDateRange($monthlyData['start'], $monthlyData['end']);
        $attendances = $this->mapAttendanceData($dates, $monthlyData['records']);
        $totalTime = $this->calculateTotalWorkTime($attendances);

        return view('attendance.history', compact('attendances', 'year', 'month', 'totalTime'));
    }

    /**
     * CSVエクスポート
     */
    public function export(Request $request)
    {
        $year = $request->input('year', $this->currentDate->year);
        $month = $request->input('month', $this->currentDate->month);

        $monthlyData = $this->getMonthlyRecords($year, $month);
        $records = $monthlyData['records']->groupBy(function ($item) {
            return $item->clock_in->format('Y-m-d');
        });

        $csvData = $this->generateCSVData($records, $monthlyData['start']);
        $csvContent = $this->generateCSVContent($csvData);
        $filename = "attendance_{$year}_{$month}.csv";

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    /**
     * CSV用データ生成
     */
    protected function generateCSVData($records, $start)
    {
        $csvHeader = ["氏名", "部署", "日付", "出勤時間", "退勤時間", "勤務時間", "休憩時間", "備考"];
        $csvData = [$csvHeader];

        $daysInMonth = $start->daysInMonth;

        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $start->copy()->addDays($i);
            $dateKey = $date->format('Y-m-d');

            if (isset($records[$dateKey])) {
                $record = $records[$dateKey]->first();
                $workTime = $this->calculateWorkTime(
                    $record->clock_in,
                    $record->clock_out,
                    $record->break_duration ?? 0
                );

                $breakHours = floor(($record->break_duration ?? 0) / 60);
                $breakMinutes = ($record->break_duration ?? 0) % 60;
                $breakFormatted = str_pad($breakHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($breakMinutes, 2, '0', STR_PAD_LEFT);

                $csvData[] = [
                    $this->user->name,
                    $this->user->department ?? "-",
                    $dateKey,
                    $record->clock_in ? $record->clock_in->format('H:i') : '-',
                    $record->clock_out ? $record->clock_out->format('H:i') : '-',
                    $workTime ? $workTime['formatted'] : '-',
                    $breakFormatted ? $breakFormatted : '-',
                    $record->notes ?? "-",
                ];
            } else {
                $csvData[] = [
                    $this->user->name,
                    $this->user->department ?? "-",
                    $dateKey,
                    "-", "-", "-", "-", "-",
                ];
            }
        }

        return $csvData;
    }

    /**
     * CSV内容生成
     */
    protected function generateCSVContent($csvData)
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        return ob_get_clean();
    }
}
