<?php

namespace App\Http\Controllers;

use App\Services\PickingListService;
use Illuminate\View\View;

class PickingListController extends Controller
{
    public function __construct(private readonly PickingListService $pickingListService)
    {
    }

    public function index(): View
    {
        $lists = $this->pickingListService->getAllPickingLists(20);
        $statistics = $this->pickingListService->getPickingListStatistics();

        return view('picking-lists.index', compact('lists', 'statistics'));
    }
} 