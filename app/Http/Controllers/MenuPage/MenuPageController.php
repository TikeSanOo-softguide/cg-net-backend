<?php

namespace App\Http\Controllers\MenuPage;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MenuPageController extends Controller
{
    public function __invoke(string $titleKey): Response
    {
        return Inertia::render('Placeholder/Index', [
            'titleKey' => $titleKey,
        ]);
    }
}
