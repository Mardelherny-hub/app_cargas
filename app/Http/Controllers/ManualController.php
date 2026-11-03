<?php

namespace App\Http\Controllers;

use App\Services\Manual\ManualRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualController extends Controller
{
    public function __construct(private ManualRepository $manual)
    {
    }

    public function index(Request $request): View
    {
        $articulos = $this->manual->indiceBusqueda($request->user());

        return view('manual.index', [
            'articulos' => $articulos,
            'temas' => array_column($articulos, 'tema_nombre', 'tema'),
            'sinonimos' => $this->manual->sinonimos(),
        ]);
    }

    public function show(Request $request, string $tema, string $slug): View
    {
        $articulo = $this->manual->articulo($request->user(), $tema, $slug);

        abort_if($articulo === null, 404);

        return view('manual.show', ['articulo' => $articulo]);
    }
}
