<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Student Model - A facade for User in the LMS context
 * 
 * This model provides LMS-specific functionality for users who are students.
 * It extends the relationship capabilities without duplicating the User model.
 */
class Student
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
     * Get all enrollments for the student.
     */
    public function enrollments()
    {
        return $this->user->hasMany(Enrollment::class, 'user_id');
    }

    /**
     * Get active enrollments.
     */
    public function activeEnrollments()
    {
        return $this->enrollments()->where('status', 'active');
    }

    /**
     * Get completed enrollments.
     */
    public function completedEnrollments()
    {
        return $this->enrollments()->where('status', 'completed');
    }

    /**
     * Get all certificates earned by the student.
     */
    public function certificates()
    {
        return $this->user->hasMany(Certificate::class, 'user_id');
    }

    /**
     * Check if student is enrolled in a course.
     */
    public function isEnrolledIn(int $courseId): bool
    {
        return $this->user->enrollments()
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get the total courses completed.
     */
    public function getCompletedCoursesCount(): int
    {
        return $this->completedEnrollments()->count();
    }

    /**
     * Get the average progress across all enrollments.
     */
    public function getAverageProgress(): float
    {
        $enrollments = $this->user->enrollments;
        
        if ($enrollments->isEmpty()) {
            return 0;
        }

        return $enrollments->avg('progress_percentage');
    }

    /**
     * Create a student instance from a user.
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
