<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentResult extends Model
{
    use HasFactory;

    protected $table = 'assessment_attempts';

    protected $fillable = [
        'enrollment_id',
        'assessment_id',
        'started_at',
        'submitted_at',
        'score',
        'percentage',
        'passed',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'integer',
        'percentage' => 'float',
        'passed' => 'boolean',
    ];

    /**
     * Get the enrollment for the assessment result.
     */
    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Get the assessment for the result.
     */
    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the answers for the attempt.
     */
    public function answers()
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }

    /**
     * Check if the attempt is completed.
     */
    public function isCompleted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Check if the attempt is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->started_at !== null && $this->submitted_at === null;
    }

    /**
     * Get the duration of the attempt in minutes.
     */
    public function getDurationAttribute()
    {
        if ($this->started_at && $this->submitted_at) {
            return $this->started_at->diffInMinutes($this->submitted_at);
        }
        return null;
    }

    /**
     * Calculate and update the score.
     */
    public function calculateScore(): void
    {
        $totalPoints = $this->answers()->sum('points_earned');
        $possiblePoints = $this->assessment->total_points;

        if ($possiblePoints > 0) {
            $this->score = $totalPoints;
            $this->percentage = ($totalPoints / $possiblePoints) * 100;
            $this->passed = $this->percentage >= $this->assessment->passing_score;
            $this->save();
        }
    }

    /**
     * Submit the attempt.
     */
    public function submit(): void
    {
        if ($this->isInProgress()) {
            $this->submitted_at = now();
            $this->calculateScore();
            $this->save();
        }
    }
}
