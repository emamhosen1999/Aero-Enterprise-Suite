<?php

namespace App\Models;

use App\Models\HRM\JobApplication as HRMJobApplication;

/**
 * JobApplication Model - Alias for HRM\JobApplication
 * 
 * This is a convenience alias to allow shorter imports.
 * Use App\Models\JobApplication instead of App\Models\HRM\JobApplication.
 */
class JobApplication extends HRMJobApplication
{
    // This class inherits all functionality from HRM\JobApplication
    // No additional implementation needed
}
