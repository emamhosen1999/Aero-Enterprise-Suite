<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_id',
        'enrolled_at',
        'expires_at',
        'price_paid',
        'status',
        'progress_percentage',
        'completed_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'price_paid' => 'decimal:2',
        'progress_percentage' => 'float',
    ];

    /**
     * Get the user for the enrollment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course for the enrollment.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the lesson progress for the enrollment.
     */
    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Get the assessment attempts for the enrollment.
     */
    public function assessmentAttempts()
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * Check if the enrollment is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Check if the enrollment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->completed_at !== null;
    }

    /**
     * Check if the enrollment is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
               ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Mark the enrollment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);
    }

    /**
     * Update the progress percentage.
     */
    public function updateProgress(): void
    {
        $totalLessons = $this->course->modules()->withCount('lessons')->get()->sum('lessons_count');

        if ($totalLessons > 0) {
            $completedLessons = $this->lessonProgress()->where('is_completed', true)->count();
            $this->progress_percentage = ($completedLessons / $totalLessons) * 100;
            $this->save();

            if ($this->progress_percentage >= 100) {
                $this->markAsCompleted();
            }
        }
    }
}
