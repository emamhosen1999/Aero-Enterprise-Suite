<?php

namespace App\Models\LMS;

use App\Models\User;

/**
 * Instructor Model - A facade for User in the LMS context
 * 
 * This model provides LMS-specific functionality for users who are instructors.
 * It extends the relationship capabilities without duplicating the User model.
 */
class Instructor
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the underlying user instance.
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Get all courses taught by the instructor.
     */
    public function courses()
    {
        return $this->user->hasMany(Course::class, 'instructor_id');
    }

    /**
     * Get published courses.
     */
    public function publishedCourses()
    {
        return $this->courses()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Get active courses (courses with active enrollments).
     */
    public function activeCourses()
    {
        return $this->courses()
            ->whereHas('enrollments', function ($query) {
                $query->where('status', 'active');
            });
    }

    /**
     * Get total students taught.
     */
    public function getTotalStudentsCount(): int
    {
        return Enrollment::whereHas('course', function ($query) {
            $query->where('instructor_id', $this->user->id);
        })->distinct('user_id')->count('user_id');
    }

    /**
     * Get active students count.
     */
    public function getActiveStudentsCount(): int
    {
        return Enrollment::whereHas('course', function ($query) {
            $query->where('instructor_id', $this->user->id);
        })->where('status', 'active')
        ->distinct('user_id')
        ->count('user_id');
    }

    /**
     * Get total courses count.
     */
    public function getTotalCoursesCount(): int
    {
        return $this->courses()->count();
    }

    /**
     * Get published courses count.
     */
    public function getPublishedCoursesCount(): int
    {
        return $this->publishedCourses()->count();
    }

    /**
     * Get average rating across all courses.
     */
    public function getAverageRating(): float
    {
        // This would require a ratings table which doesn't exist in the migration
        // Placeholder for future implementation
        return 0.0;
    }

    /**
     * Get total revenue generated (if applicable).
     */
    public function getTotalRevenue(): float
    {
        return Enrollment::whereHas('course', function ($query) {
            $query->where('instructor_id', $this->user->id);
        })->sum('price_paid');
    }

    /**
     * Check if user can be an instructor.
     */
    public static function canBeInstructor(User $user): bool
    {
        // Add your own logic here, e.g., check roles/permissions
        return $user->hasRole('instructor') || $user->hasRole('admin');
    }

    /**
     * Create an instructor instance from a user.
     */
    public static function fromUser(User $user): self
    {
        return new self($user);
    }

    /**
     * Magic method to access user properties.
     */
    public function __get(string $name)
    {
        return $this->user->$name;
    }

    /**
     * Magic method to call user methods.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->user->$method(...$parameters);
    }
}
