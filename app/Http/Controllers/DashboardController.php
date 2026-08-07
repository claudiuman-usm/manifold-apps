<?php

namespace App\Http\Controllers;

use App\Modules\ModuleRegistry;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ModuleRegistry $registry): View
    {
        return view('dashboard', [
            'modules' => $registry->all(),
        ]);
    }
}
