@extends('layouts/default')

@section('title')
    {{ trans('fiolux.title') }}
    @parent
@stop

@section('content')
<div class="box box-default">
    <div class="box-header"><h2 class="box-title">{{ trans('fiolux.title') }}</h2></div>
    <div class="box-body">
        <p class="text-muted">{{ trans('fiolux.demo') }}</p>
        @foreach ($notebooks as $notebook)
            <p>
                <a href="{{ route('hardware.show', $notebook->asset_id) }}">{{ $notebook->asset->asset_tag }}</a>
                @can('checkout', \App\Models\FioluxCycle::class)
                    <a class="btn btn-primary btn-sm" href="{{ route('fiolux.create', ['asset' => $notebook->asset_id]) }}">{{ trans('fiolux.delivery') }}</a>
                @endcan
                @if ($notebook->lastCycle && !$notebook->lastCycle->returned_at)
                    @can('checkin', $notebook->lastCycle)
                        <a class="btn btn-warning btn-sm" href="{{ route('fiolux.create', ['asset' => $notebook->asset_id, 'kind' => 'return']) }}">{{ trans('fiolux.return') }}</a>
                    @endcan
                @endif
            </p>
        @endforeach
        <table class="table table-striped">
            <thead><tr><th>{{ trans('fiolux.cycle') }}</th><th>{{ trans('fiolux.asset') }}</th><th>{{ trans('fiolux.employee') }}</th><th>{{ trans('fiolux.documents') }}</th></tr></thead>
            <tbody>
            @foreach ($cycles as $cycle)
                <tr>
                    <td><a href="{{ route('fiolux.show', $cycle) }}">#{{ $cycle->id }}</a></td>
                    <td>{{ $cycle->inspections->first()?->snapshot['asset']['tag'] }}</td>
                    <td>{{ $cycle->inspections->first()?->snapshot['user']['name'] }}</td>
                    <td>{{ trans($cycle->signaturePending() ? 'fiolux.signature_pending' : 'fiolux.signature_complete') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@stop