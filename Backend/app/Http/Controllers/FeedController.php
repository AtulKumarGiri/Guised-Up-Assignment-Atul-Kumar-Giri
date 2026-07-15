<?php

namespace App\Http\Controllers;

use App\Services\FeedRankingService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(protected FeedRankingService $rankingService)
    {
    }

    public function index(Request $request)
    {
        $page = (int) $request->query('page', 1);

        $result = $this->rankingService->feedFor($request->user(), perPage: 20, page: $page);

        return response()->json($result);
    }
}
