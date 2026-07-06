<?php

namespace App\Http\Controllers;

use App\Models\Puesto;
use Illuminate\Http\Request;

class PuestoController extends Controller
{
    public function index()
    {
        return Puesto::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required'
        ]);

        Puesto::create($request->only('nombre', 'descripcion'));
    }

    public function update(Request $request, Puesto $puesto)
    {
        $puesto->update($request->only('nombre', 'descripcion'));
    }

    public function destroy(Puesto $puesto)
    {
        $puesto->delete();
    }
}
