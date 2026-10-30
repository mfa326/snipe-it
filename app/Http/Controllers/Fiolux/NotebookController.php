<?php

namespace App\Http\Controllers\Fiolux;

use App\Actions\Fiolux\RecordMovementAction;
use App\Actions\Fiolux\RecordTechnicalEvaluationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiolux\ChecklistRequest;
use App\Http\Requests\Fiolux\DocumentReviewRequest;
use App\Http\Requests\Fiolux\DocumentUploadRequest;
use App\Http\Requests\Fiolux\TechnicalRequest;
use App\Models\Asset;
use App\Models\FioluxCycle;
use App\Models\FioluxDocument;
use App\Models\FioluxInspection;
use App\Models\FioluxNotebook;
use App\Models\User;
use App\Services\Fiolux\Documents;
use App\Services\Fiolux\NotebookChecklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotebookController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', FioluxCycle::class);
        $notebooks = FioluxNotebook::with('asset')->get()->filter(fn ($item) => $item->asset && Gate::allows('view', $item->asset));
        $cycles = FioluxCycle::with(['inspections.documents', 'evaluations', 'asset', 'user'])
            ->when($request->filled('asset'), fn ($q) => $q->where('asset_id', $request->integer('asset')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->orderByDesc('id')->limit(100)->get()->filter(fn ($cycle) => Gate::allows('view', $cycle));

        return view('fiolux.index', compact('notebooks', 'cycles'));
    }

    public function create(Asset $asset, Request $request): View
    {
        $this->authorize('view', $asset);
        FioluxNotebook::where('asset_id', $asset->id)->firstOrFail();
        $kind = $request->input('kind') === 'return' ? 'return' : 'delivery';
        $cycle = null;
        if ($kind === 'return') {
            $cycle = FioluxNotebook::where('asset_id', $asset->id)->firstOrFail()->lastCycle;
            abort_unless($cycle, 404);
            $this->authorize('checkin', $cycle);
        } else {
            $this->authorize('checkout', FioluxCycle::class);
        }
        $users = User::where('first_name', 'like', 'TESTE FIOLUX%')->get()->filter(fn ($user) => Gate::allows('view', $user));
        $questions = NotebookChecklist::questions();
        $operationKey = (string) Str::uuid();

        return view('fiolux.form', compact('asset', 'kind', 'cycle', 'users', 'questions', 'operationKey'));
    }

    public function store(Asset $asset, ChecklistRequest $request): RedirectResponse
    {
        $inspection = RecordMovementAction::run($asset, $request->user(), $request->validated());

        return redirect()->route('fiolux.show', $inspection->cycle_id)->with('success', trans('fiolux.movement_saved'));
    }

    public function show(FioluxCycle $cycle): View
    {
        $this->authorize('view', $cycle);
        $cycle->load(['inspections.documents', 'evaluations']);
        $operationKey = (string) Str::uuid();
        $canRecordTechnical = $cycle->returned_at && ! $cycle->asset->assigned_to
            && FioluxNotebook::where('asset_id', $cycle->asset_id)->where('last_cycle_id', $cycle->id)->exists();

        return view('fiolux.show', compact('cycle', 'operationKey', 'canRecordTechnical'));
    }

    public function technical(FioluxCycle $cycle, TechnicalRequest $request): RedirectResponse
    {
        RecordTechnicalEvaluationAction::run($cycle, $request->user(), $request->validated());

        return redirect()->route('fiolux.show', $cycle)->with('success', trans('fiolux.technical_saved'));
    }

    public function generate(FioluxInspection $inspection, Documents $documents): RedirectResponse
    {
        $this->authorize('documents', $inspection->cycle);
        $document = $documents->generate($inspection, auth()->user());

        return redirect()->route('fiolux.show', $inspection->cycle_id)->with($document->state === 'ready' ? 'success' : 'error', trans($document->state === 'ready' ? 'fiolux.pdf_saved' : 'fiolux.document_failed'));
    }

    public function upload(FioluxInspection $inspection, DocumentUploadRequest $request, Documents $documents): RedirectResponse
    {
        $this->authorize('documents', $inspection->cycle);
        $document = $documents->upload($inspection, $request->user(), $request->file('file'));

        return redirect()->route('fiolux.show', $inspection->cycle_id)->with($document->state === 'ready' ? 'success' : 'error', trans($document->state === 'ready' ? 'fiolux.upload_saved' : 'fiolux.document_failed'));
    }

    public function review(FioluxDocument $document, DocumentReviewRequest $request, Documents $documents): RedirectResponse
    {
        $this->authorize('documents', $document->inspection->cycle);
        $documents->review($document, $request->user());

        return redirect()->route('fiolux.show', $document->cycle_id)->with('success', trans('fiolux.document_checked'));
    }

    public function download(FioluxDocument $document): StreamedResponse
    {
        $this->authorize('view', $document->inspection->cycle);
        abort_unless($document->state === 'ready' && $document->path && Storage::exists($document->path), 404);
        abort_unless(hash('sha256', Storage::get($document->path)) === $document->sha256, 409);

        return Storage::download($document->path, 'TESTE-FIOLUX-'.$document->cycle_id.'-'.$document->id.'.'.pathinfo($document->path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
