<?php

namespace App\Services\Attendance\Contracts;

use App\Services\Attendance\DTO\ShiftSchedule;
use Carbon\CarbonInterface;

interface ScheduleResolver
{
    public function resolve(int|string $userId, CarbonInterface $date): ShiftSchedule;
}
