<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (strtolower((string) auth()->user()?->email) !== 'admin@email.com') {
            return redirect()->route('reports.konsolidasi');
        }

        return view('admin.dashboard');
    }
}
