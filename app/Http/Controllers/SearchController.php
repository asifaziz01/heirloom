<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, Family $family)
    {
        $term = $request->string('q')->trim()->value();
        if (mb_strlen($term) < 2) {
            return view('search', ['term' => $term, 'results' => null]);
        }
        $like = "%{$term}%";

        return view('search', ['term' => $term, 'results' => [
            'people' => $family->persons()->visibleTo($this->member())->search($term)->with('names')->take(30)->get(),
            'stories' => $family->stories()->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like))->take(20)->get(),
            'media' => $family->media()->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('transcription', 'like', $like)->orWhere('original_name', 'like', $like))->take(24)->get(),
            'events' => $family->events()->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))->take(20)->get(),
            'places' => $family->places()->where('name', 'like', $like)->take(20)->get(),
        ]]);
    }
}
