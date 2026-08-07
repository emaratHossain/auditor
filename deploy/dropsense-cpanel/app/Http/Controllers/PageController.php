<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\PageEraser;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function index()
    {
        return PageResource::collection(
            Page::with(['audits' => fn ($q) => $q->latest('id')->limit(1)])
                // The confirm dialog says how many reports are about to go, so
                // it has to know before anyone presses anything.
                ->withCount('audits')
                ->latest('id')
                ->get()
        );
    }

    public function store(StorePageRequest $request)
    {
        $page = Page::create($request->validated());

        return (new PageResource($page))->response()->setStatusCode(201);
    }

    /** The page, its audits, its reports and its screenshots. All of it. */
    public function destroy(Page $page, PageEraser $eraser)
    {
        $eraser->erase($page);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
