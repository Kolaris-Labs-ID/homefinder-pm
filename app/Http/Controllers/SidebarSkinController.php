<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SidebarSkinController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'skin' => 'required|in:light,slate,navy',
        ]);

        $request->user()->update(['sidebar_skin' => $data['skin']]);

        return response()->noContent();
    }
}
