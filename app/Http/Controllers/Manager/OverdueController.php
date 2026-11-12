<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Services\OverdueReport;
use Illuminate\View\View;

class OverdueController extends Controller
{
    public function index(OverdueReport $report): View
    {
        return view('manager.index', $report->dashboard());
    }

    public function show(int $student, OverdueReport $report): View
    {
        $data = $report->forStudent($student);
        abort_if($data === null, 404);

        return view('manager.student', ['report' => $data]);
    }
}
