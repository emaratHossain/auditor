<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;

class PageController extends Controller
{
    public function index()
    {
        return PageResource::collection(
            Page::with(['audits' => fn ($q) => $q->latest('id')->limit(1)])->latest('id')->get()
        );
    }

    public function store(StorePageRequest $request)
    {
        $page = Page::create($request->validated());

        return (new PageResource($page))->response()->setStatusCode(201);
    }
}
