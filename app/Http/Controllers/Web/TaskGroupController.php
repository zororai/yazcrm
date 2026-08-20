<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\TaskGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskGroupController extends Controller
{
    public function store(Request $request, Board $board): RedirectResponse
    {
        $this->authorize('manageGroups', $board);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'nullable|string|max:32',
        ]);

        $board->groups()->create($data + [
            'position' => $board->groups()->max('position') + 1,
        ]);

        return back()->with('success', 'Group created.');
    }

    public function update(Request $request, Board $board, TaskGroup $group): RedirectResponse
    {
        $this->authorize('manageGroups', $board);
        abort_unless($group->board_id === $board->id, 404);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'color'       => 'nullable|string|max:32',
            'position'    => 'sometimes|integer|min:0',
            'is_archived' => 'sometimes|boolean',
        ]);

        $group->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Board $board, TaskGroup $group): RedirectResponse
    {
        $this->authorize('manageGroups', $board);
        abort_unless($group->board_id === $board->id, 404);

        $group->delete();

        return back()->with('success', 'Group deleted.');
    }
}
