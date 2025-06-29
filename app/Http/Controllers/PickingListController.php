<?php

namespace App\Http\Controllers;

use App\Models\PickingList;
use Illuminate\View\View;

class PickingListController extends Controller
{
    public function index(): View
    {
        $lists = PickingList::with(['items.product'])->latest('picking_date')->paginate(20);
        return view('picking-lists.index', compact('lists'));
    }
} 