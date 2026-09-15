<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaServico;

class CategoriaServicoController extends Controller
{
    public function index()
    {
        return CategoriaServico::orderBy('nome')->get();
    }
}
