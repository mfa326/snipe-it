@extends('layouts/default')

@section('title')
    {{ trans('fiolux.cycle') }} #{{ $cycle->id }}
    @parent
@stop

@section('content')
@php
    $delivery = $cycle->inspections->firstWhere('kind', 'delivery');
    $returned = $cycle->inspections->firstWhere('kind', 'return');
    $questions = array_unique(array_merge(array_keys($delivery->snapshot['questions']), array_keys($returned?->snapshot['questions'] ?? [])));
@endphp
<div class="box box-default">
    <div class="box-header"><h2 class="box-title">{{ trans('fiolux.cycle') }} #{{ $cycle->id }} — {{ $delivery->snapshot['asset']['tag'] }}</h2></div>
    <div class="box-body">
        <p>{{ trans('fiolux.employee') }}: {{ $delivery->snapshot['user']['name'] }}</p>
        <p class="{{ $cycle->signaturePending() ? 'text-danger' : 'text-success' }}">{{ trans($cycle->signaturePending() ? 'fiolux.signature_pending' : 'fiolux.signature_complete') }}</p>
        <p>{{ trans('fiolux.comparison_note') }}</p>
        <table class="table table-bordered">
            <thead><tr><th>{{ trans('fiolux.checklist') }}</th><th>{{ trans('fiolux.delivery') }}</th><th>{{ trans('fiolux.return') }}</th></tr></thead>
            <tbody>
            @foreach ($questions as $key)
                @php
                    $left = $delivery->snapshot['questions'][$key] ?? null;
                    $right = $returned?->snapshot['questions'][$key] ?? null;
                    $different = $right && (!$left || $left['answer'] !== $right['answer']);
                @endphp
                <tr class="{{ $different ? 'warning' : '' }}">
                    <th>{{ $left['label'] ?? $right['label'] }}</th>
                    <td>{{ $left['answer_label'] ?? '—' }}</td>
                    <td>{{ $right['answer_label'] ?? trans('fiolux.no_return') }} @if ($different)<strong>({{ trans('fiolux.different') }})</strong>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="row">
        @foreach ($cycle->inspections as $inspection)
            <div class="col-md-6">
                <h3>{{ trans('fiolux.'.$inspection->kind) }} — {{ trans('fiolux.inspection') }} #{{ $inspection->id }}</h3>
                <p>{{ trans('fiolux.operator') }}: {{ $inspection->snapshot['operator']['name'] }}<br>{{ $inspection->snapshot['recorded_at'] }}<br>{{ trans('fiolux.template') }}: {{ $inspection->template_version }}</p>
                <p style="white-space:pre-wrap">{{ $inspection->snapshot['notes'] }}</p>
                <p style="white-space:pre-wrap">{{ $inspection->snapshot['reason'] }}</p>
                <h4>{{ trans('fiolux.documents') }}</h4>
                @if (!$inspection->documents->where('kind','generated')->where('state','ready')->count() || !$inspection->documents->where('kind','signed')->where('state','ready')->count())
                    <p class="text-warning">{{ trans('fiolux.document_pending') }}</p>
                @endif
                @foreach ($inspection->documents as $document)
                    <p>#{{ $document->id }} — {{ trans('fiolux.'.$document->kind) }} — {{ trans('fiolux.'.$document->state) }}</p>
                    @if ($document->state === 'ready')
                        <a href="{{ route('fiolux.download', $document) }}">{{ trans('fiolux.download') }} #{{ $document->id }}</a>
                        @if ($document->kind === 'signed')
                            <p>{{ trans('fiolux.unverified') }}</p>
                            @if ($document->checked_at)
                                <p>{{ trans('fiolux.checked') }} — #{{ $document->checked_by }} — {{ $document->checked_at }}</p>
                            @else
                                @can('documents', $cycle)
                                    <form method="POST" action="{{ route('fiolux.review', $document) }}">@csrf
                                        <label><input type="checkbox" name="confirmed" value="1" required> {{ trans('fiolux.review_confirm') }}</label>
                                        <button type="submit" class="btn btn-default btn-sm">{{ trans('fiolux.review') }}</button>
                                    </form>
                                @endcan
                            @endif
                        @endif
                    @endif
                @endforeach
                @can('documents', $cycle)
                    <form method="POST" action="{{ route('fiolux.pdf', $inspection) }}">@csrf<button class="btn btn-default" type="submit">{{ trans('fiolux.generate') }}</button></form>
                    <form method="POST" action="{{ route('fiolux.upload', $inspection) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group"><label for="file_{{ $inspection->id }}">{{ trans('fiolux.upload') }}</label><input type="file" id="file_{{ $inspection->id }}" name="file" accept=".pdf,.png,.jpg,.jpeg" required><p class="help-block">{{ trans('fiolux.upload_help') }}</p></div>
                        <button class="btn btn-default" type="submit">{{ trans('fiolux.upload') }}</button>
                    </form>
                @endcan
            </div>
        @endforeach
        </div>
        <hr>
        <h3>{{ trans('fiolux.technical') }}</h3>
        @foreach ($cycle->evaluations as $evaluation)
            <p>{{ $evaluation->operator_snapshot['name'] }} — {{ $evaluation->created_at }} — <strong>{{ trans('fiolux.'.$evaluation->result) }}</strong></p>
            <p style="white-space:pre-wrap">{{ $evaluation->notes }}</p>
        @endforeach
        @if ($canRecordTechnical)
            @can('technical', $cycle)
                <form method="POST" action="{{ route('fiolux.technical', $cycle) }}">
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
                    <div class="form-group"><label for="result">{{ trans('fiolux.technical') }}</label><select class="form-control" name="result" id="result" required><option value="">{{ trans('fiolux.choose') }}</option>@foreach (['available','preparation','maintenance','investigation','pending'] as $result)<option value="{{ $result }}">{{ trans('fiolux.'.$result) }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="technical_notes">{{ trans('fiolux.notes') }}</label><textarea class="form-control" name="notes" id="technical_notes" required maxlength="10000"></textarea></div>
                    <label><input type="checkbox" name="confirmed" value="1" required> {{ trans('fiolux.technical_confirm') }}</label>
                    <button type="submit" class="btn btn-primary">{{ trans('fiolux.technical_save') }}</button>
                </form>
            @endcan
        @elseif (! $cycle->returned_at)
            @can('checkin', $cycle)<a class="btn btn-warning" href="{{ route('fiolux.create', ['asset' => $cycle->asset_id, 'kind' => 'return']) }}">{{ trans('fiolux.return') }}</a>@endcan
        @endif
        <p><a href="{{ route('fiolux.index') }}">{{ trans('fiolux.back') }}</a></p>
    </div>
</div>
@stop
