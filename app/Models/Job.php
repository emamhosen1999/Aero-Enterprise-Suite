<?php

namespace App\Models;

use App\Models\HRM\Job as HRMJob;

/**
 * Job Model - Alias for HRM\Job
 * 
 * This is a convenience alias to allow shorter imports.
 * Use App\Models\Job instead of App\Models\HRM\Job.
 */
class Job extends HRMJob
{
    // This class inherits all functionality from HRM\Job
    // No additional implementation needed
}
