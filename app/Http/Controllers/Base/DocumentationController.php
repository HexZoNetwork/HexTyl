<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;

class DocumentationController extends Controller
{
    public function index(): View
    {
        return view('docs.index');
    }
}
