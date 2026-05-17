<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'objectives',
        'level',
        'duration_minutes',
        'thumbnail',
        'instructor_id',
        'price',
        'is_featured',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
    ];

    /**
     * Get the instructor for the course.
     */
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the enrollments for the course.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the assessments for the course.
     */
    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Get the categories for the course.
     */
    public function categories()
    {
        return $this->belongsToMany(
            CourseCategory::class,
            'course_category',
            'course_id',
            'course_category_id'
        );
    }

    /**
     * Get the modules for the course.
     */
    public function modules()
    {
        return $this->hasMany(CourseModule::class)->orderBy('order');
    }

    /**
     * Check if the course is published.
     */
    public function isPublished(): bool
    {
        return $this->is_active && $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Check if the course is free.
     */
    public function isFree(): bool
    {
        return $this->price == 0;
    }

    /**
     * Get the total number of lessons in the course.
     */
    public function getTotalLessonsAttribute()
    {
        return $this->modules()->withCount('lessons')->get()->sum('lessons_count');
    }

    /**
     * Get the enrolled students count.
     */
    public function getEnrolledStudentsCountAttribute()
    {
        return $this->enrollments()->where('status', 'active')->count();
    }
}
