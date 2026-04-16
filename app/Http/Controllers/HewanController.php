<?php

namespace App\Http\Controllers;

use App\Models\Hewan;
use Illuminate\Http\Request;

class HewanController extends Controller
{
    public function index()
    {
        return response()->json(Hewan::with('sohibul')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string',
            'jenis' => 'required|string',
            'kode' => 'required|string|unique:hewan,kode',
            'berat_awal' => 'integer',
            'sohibul_id' => 'required|exists:sohibul,id',
        ]);

        $hewan = Hewan::create($validated);

        return response()->json($hewan, 201);
    }

    public function show(Hewan $hewan)
    {
        return response()->json($hewan->load('sohibul'));
    }

    public function update(Request $request, Hewan $hewan)
    {
        $validated = $request->validate([
            'nama' => 'sometimes|required|string',
            'jenis' => 'sometimes|required|string',
            'kode' => 'sometimes|required|string|unique:hewan,kode,'.$hewan->id,
            'berat_awal' => 'integer',
            'berat_daging' => 'integer',
            'berat_tulang' => 'integer',
            'mulai_jagal' => 'nullable|date',
            'selesai_jagal' => 'nullable|date',
            'mulai_kuliti' => 'nullable|date',
            'selesai_kuliti' => 'nullable|date',
            'mulai_cacah_daging' => 'nullable|date',
            'selesai_cacah_daging' => 'nullable|date',
            'mulai_cacah_tulang' => 'nullable|date',
            'selesai_cacah_tulang' => 'nullable|date',
            'mulai_jeroan' => 'nullable|date',
            'selesai_jeroan' => 'nullable|date',
            'mulai_packing' => 'nullable|date',
            'selesai_packing' => 'nullable|date',
            'total_kantong' => 'integer',
            'distribusi' => 'integer',
            'sohibul_id' => 'sometimes|required|exists:sohibul,id',
        ]);

        $hewan->update($validated);

        return response()->json($hewan);
    }

    public function destroy(Hewan $hewan)
    {
        $hewan->delete();

        return response()->json(null, 204);
    }
}
