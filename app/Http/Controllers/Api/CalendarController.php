<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OperationalCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function __construct(
        public OperationalCalendarService $calendarService,
    ) {}

    /**
     * Retrieve day type classification for a specific date.
     * Used by overtime submission forms to automatically classify dates into HKN or HLR.
     */
    public function show(Request $request, string $date): JsonResponse
    {
        try {
            $carbonDate = Carbon::parse($date);
            $normalizedDate = $carbonDate->format('Y-m-d');
        } catch (\Throwable) {
            return response()->json([
                'message' => __('Format tanggal tidak valid. Gunakan format YYYY-MM-DD.'),
            ], 422);
        }

        $calendar = $this->calendarService->getByDate($normalizedDate);

        return response()->json([
            'date' => $calendar->calendar_date->format('Y-m-d'),
            'day_type' => $calendar->day_type,
            'is_holiday' => (bool) $calendar->is_holiday,
            'holiday_name' => $calendar->holiday_name,
            'description' => $calendar->description,
        ]);
    }
}
