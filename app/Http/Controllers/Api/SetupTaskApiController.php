<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SetupTaskResource;
use App\Models\Setup;
use App\Models\SetupTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Checklist steps inside one setup. Tasks are scoped to their setup in routes/api.php. */
class SetupTaskApiController extends Controller
{
    public function store(Request $request, Setup $setup): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:250']]);

        $task = $setup->tasks()->create([
            'title' => $data['title'],
            'position' => (int) $setup->tasks()->max('position') + 1,
        ]);

        return SetupTaskResource::make($task)->response()->setStatusCode(201);
    }

    public function update(Request $request, Setup $setup, SetupTask $task): SetupTaskResource
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:250'],
            'done' => ['sometimes', 'required', 'boolean'],
        ]);

        if (isset($data['title'])) {
            $task->title = $data['title'];
        }
        if (array_key_exists('done', $data) && (bool) $data['done'] !== ($task->done_at !== null)) {
            $task->forceFill(['done_at' => $data['done'] ? now() : null, 'done_by' => null]);
        }
        $task->save();

        if ($setup->status === 'todo' && $task->done_at) {
            $setup->update(['status' => 'in_progress']);
        }

        return SetupTaskResource::make($task);
    }

    public function destroy(Setup $setup, SetupTask $task): JsonResponse
    {
        $task->delete();

        return response()->json(null, 204);
    }
}
