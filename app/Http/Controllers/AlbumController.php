<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Family;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function store(Request $request, Family $family)
    {
        $data = $request->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:2000']);
        $album = $family->albums()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('albums.show', [$family, $album])->with('status', 'Album created. Upload photos into it.');
    }

    public function show(Family $family, Album $album)
    {
        return view('media.album', ['album' => $album, 'media' => $album->media()->latest()->get()]);
    }
}
