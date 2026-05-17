<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'module_id',
        'title',
        'description',
        'passing_score',
        'time_limit_minutes',
        'is_final_exam',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit_minutes' => 'integer',
        'is_final_exam' => 'boolean',
    ];

    /**
     * Get the course for the assessment.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the module for the assessment.
     */
    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }

    /**
     * Get the questions for the assessment.
     */
    public function questions()
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('order');
    }

    /**
     * Get the attempts for the assessment.
     */
    public function attempts()
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * Get the total points for the assessment.
     */
    public function getTotalPointsAttribute()
    {
        return $this->questions()->sum('points');
    }

    /**
     * Get the total number of questions.
     */
    public function getTotalQuestionsAttribute()
    {
        return $this->questions()->count();
    }

    /**
     * Check if the assessment has a time limit.
     */
    public function hasTimeLimit(): bool
    {
        return $this->time_limit_minutes !== null && $this->time_limit_minutes > 0;
    }

    /**
     * Get the pass threshold score.
     */
    public function getPassThresholdAttribute()
    {
        return ($this->passing_score / 100) * $this->total_points;
    }
}
