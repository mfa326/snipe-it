<?php

namespace App\Services\Fiolux;

use App\Http\Requests\Fiolux\DocumentUploadRequest;
use App\Models\FioluxDocument;
use App\Models\FioluxInspection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class Documents
{
    public function generate(FioluxInspection $inspection, User $operator): FioluxDocument
    {
        Gate::authorize('documents', $inspection->cycle);

        return $this->store($inspection, $operator, 'generated', fn () => [$this->renderPdf($inspection), 'pdf', 'application/pdf']);
    }

    public function upload(FioluxInspection $inspection, User $operator, UploadedFile $file): FioluxDocument
    {
        Gate::authorize('documents', $inspection->cycle);
        Validator::make(['file' => $file], (new DocumentUploadRequest)->rules())->validate();

        return $this->store($inspection, $operator, 'signed', fn () => [file_get_contents($file->getRealPath()), $file->getMimeType() === 'application/pdf' ? 'pdf' : ($file->getMimeType() === 'image/png' ? 'png' : 'jpg'), $file->getMimeType()]);
    }

    public function renderPdf(FioluxInspection $inspection): string
    {
        $pdf = new \TCPDF;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('TESTE FIOLUX');
        $pdf->SetTitle('TESTE FIOLUX - '.$inspection->id);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();
        $pdf->writeHTML(view('fiolux.pdf', compact('inspection'))->render());

        return $pdf->Output('', 'S');
    }

    public function review(FioluxDocument $document, User $operator): void
    {
        Gate::authorize('documents', $document->inspection->cycle);
        DB::transaction(function () use ($document, $operator): void {
            $locked = FioluxDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($locked->kind !== 'signed' || $locked->state !== 'ready' || ! $locked->path || ! Storage::exists($locked->path) || hash('sha256', Storage::get($locked->path)) !== $locked->sha256) {
                throw ValidationException::withMessages(['fiolux' => trans('fiolux.document_invalid')]);
            }
            if (! $locked->checked_at) {
                $locked->checked_at = now();
                $locked->checked_by = $operator->id;
                $locked->saveOrFail();
            }
        });
    }

    private function store(FioluxInspection $inspection, User $operator, string $kind, \Closure $content): FioluxDocument
    {
        $document = FioluxDocument::create([
            'cycle_id' => $inspection->cycle_id, 'inspection_id' => $inspection->id, 'kind' => $kind,
            'state' => 'pending', 'operator_id' => $operator->id,
        ]);
        try {
            [$bytes, $extension, $mime] = $content();
            if (! is_string($bytes) || $bytes === '') {
                throw new \RuntimeException('Empty document');
            }
            $path = 'private_uploads/fiolux/'.$inspection->cycle_id.'/'.$inspection->id.'/'.Str::uuid().'.'.$extension;
            if (! Storage::put($path, $bytes)) {
                throw new \RuntimeException('Document write failed');
            }
            $document->fill(['path' => $path, 'sha256' => hash('sha256', $bytes), 'mime' => $mime, 'state' => 'ready'])->saveOrFail();
        } catch (Throwable $exception) {
            report($exception);
            $document->fill(['state' => 'failed', 'error' => trans('fiolux.document_failed')])->saveOrFail();
        }

        return $document;
    }
}
