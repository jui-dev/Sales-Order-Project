<?php

namespace App\Http\Controllers;

use App\Support\Nav\ActionCatalog;
use Illuminate\View\View;

/**
 * Renders the catalogue of what each action in the application triggers.
 *
 * The same table drives the headline on the panel shown after an action, so
 * the reference and the live notifications cannot drift apart.
 */
class ActionEffectsController extends Controller
{
    public function index(): View
    {
        return view('reference.action-effects', [
            'modules' => ActionCatalog::byModule(),
        ]);
    }
}
