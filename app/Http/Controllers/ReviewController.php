<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Family;
use App\Services\Governance;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ReviewController extends Controller
{
    public function index(Family $family)
    {
        return view('review.index', [
            'pending' => $family->changeRequests()->where('status', 'pending')->with('user')->oldest()->get(),
            'recent' => $family->changeRequests()->where('status', '!=', 'pending')->with(['user', 'reviewer'])->latest('reviewed_at')->take(20)->get(),
        ]);
    }

    public function approve(Request $request, Family $family, ChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'pending', 409, 'Already reviewed.');
        try {
            Governance::approve($changeRequest, $this->member(), $request->input('note'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['review' => 'Could not apply: '.$e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['review' => 'Could not apply this change — the records it refers to may have been removed.']);
        }

        return back()->with('status', 'Approved and added to the archive.');
    }

    public function reject(Request $request, Family $family, ChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'pending', 409, 'Already reviewed.');
        Governance::reject($changeRequest, $this->member(), $request->input('note'));

        return back()->with('status', 'Change rejected.');
    }
}
