<?php

namespace App\Http\Controllers;

use App\Models\Sohibul;
use Illuminate\Http\Request;

class SohibulController extends Controller
{
    public function index()
    {
        return response()->json(Sohibul::with('hewan')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|array',
            'request' => 'nullable|string',
        ]);

        $sohibul = Sohibul::create($validated);

        return response()->json($sohibul, 201);
    }

    public function show(Sohibul $sohibul)
    {
        return response()->json($sohibul->load('hewan'));
    }

    public function update(Request $request, Sohibul $sohibul)
    {
        $validated = $request->validate([
            'nama' => 'sometimes|required|array',
            'request' => 'nullable|string',
        ]);

        $sohibul->update($validated);

        return response()->json($sohibul);
    }

    public function destroy(Sohibul $sohibul)
    {
        $sohibul->delete();

        return response()->json(null, 204);
    }
}
