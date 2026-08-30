<?php

declare(strict_types=1);

namespace App\Http\Controllers\Aeon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AeonPageController extends Controller
{
    /**
     * Render the dedicated full-page Aeon Copilot console.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Aeon/Index', [
            'title' => 'Aeon Copilot',
        ]);
    }
}
