@extends('layouts/default')

@section('title')
    {{ trans('fiolux.'.$kind) }} — {{ $asset->asset_tag }}
    @parent
@stop

@section('content')
<div class="box box-default">
    <div class="box-header"><h2 class="box-title">{{ trans('fiolux.'.$kind) }} — {{ $asset->asset_tag }}</h2></div>
    <div class="box-body">
        @if ($errors->any())
            <div class="alert alert-danger"><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        @if ($cycle)
            @php($original = $cycle->inspections()->where('kind', 'delivery')->first())
            <h3>{{ trans('fiolux.original') }}</h3>
            <p>{{ $original->snapshot['user']['name'] }} — {{ $original->snapshot['recorded_at'] }}</p>
            <ul>@foreach ($original->snapshot['questions'] as $question)<li>{{ $question['label'] }}: {{ $question['answer_label'] }}</li>@endforeach</ul>
            <p style="white-space:pre-wrap">{{ $original->snapshot['notes'] }}</p>
            <a href="{{ route('fiolux.show', $cycle) }}">{{ trans('fiolux.documents') }}</a>
        @endif
        <form method="POST" action="{{ route('fiolux.store', $asset) }}">
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
            <input type="hidden" name="kind" value="{{ $kind }}">
            @if ($cycle)
                <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
            @else
                <div class="form-group">
                    <label for="user_id">{{ trans('fiolux.employee') }}</label>
                    <select class="form-control" id="user_id" name="user_id" required>
                        <option value="">{{ trans('fiolux.choose') }}</option>
                        @foreach ($users as $employee)<option value="{{ $employee->id }}" @selected(old('user_id') == $employee->id)>{{ $employee->first_name }} {{ $employee->last_name }}</option>@endforeach
                    </select>
                </div>
            @endif
            @foreach ($questions as $key => $question)
                <div class="form-group">
                    <label for="answer_{{ $key }}">{{ $question['label'] }}</label>
                    <select class="form-control" id="answer_{{ $key }}" name="answers[{{ $key }}]" required>
                        <option value="">{{ trans('fiolux.choose') }}</option>
                        @foreach ($question['options'] as $value => $label)<option value="{{ $value }}" @selected(old('answers.'.$key) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
            @endforeach
            <div class="form-group"><label for="reason">{{ trans('fiolux.reason') }}</label><textarea class="form-control" id="reason" name="reason" maxlength="5000">{{ old('reason') }}</textarea></div>
            <div class="form-group"><label for="notes">{{ trans('fiolux.notes') }} — {{ trans('fiolux.'.$kind) }}</label><textarea class="form-control" id="notes" name="notes" maxlength="10000">{{ old('notes') }}</textarea></div>
            <div class="checkbox"><label><input type="checkbox" name="confirmed" value="1" required> {{ trans('fiolux.confirm') }}</label></div>
            <button class="btn btn-primary" type="submit">{{ trans('fiolux.save') }}</button>
            <a href="{{ route('fiolux.index') }}">{{ trans('fiolux.back') }}</a>
        </form>
    </div>
</div>
@stop