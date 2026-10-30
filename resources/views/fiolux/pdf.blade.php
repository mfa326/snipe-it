<h1>{{ trans('fiolux.'.$inspection->kind) }} — Fiolux</h1>
<p>{{ trans('fiolux.demo') }}</p>
<p>{{ trans('fiolux.documents') }}: FIOLUX-{{ $inspection->cycle_id }}-{{ $inspection->id }}<br>
{{ trans('fiolux.cycle') }}: {{ $inspection->cycle_id }} | {{ trans('fiolux.inspection') }}: {{ $inspection->id }}<br>
{{ trans('fiolux.template') }}: {{ $inspection->template_version }}</p>
<p>{{ trans('fiolux.asset') }}: {{ $inspection->snapshot['asset']['tag'] }}<br>
{{ $inspection->snapshot['asset']['manufacturer'] }} — {{ $inspection->snapshot['asset']['model'] }} — {{ $inspection->snapshot['asset']['serial'] }}<br>
{{ trans('fiolux.employee') }}: {{ $inspection->snapshot['user']['name'] }}<br>
{{ trans('fiolux.operator') }}: {{ $inspection->snapshot['operator']['name'] }}<br>
{{ trans('fiolux.date') }}: {{ $inspection->snapshot['recorded_at'] }}</p>
<table border="1" cellpadding="6">
@foreach ($inspection->snapshot['questions'] as $question)
<tr><th>{{ $question['label'] }}</th><td>{{ $question['answer_label'] }}</td></tr>
@endforeach
</table>
<h2>{{ trans('fiolux.notes') }}</h2>
<p>{!! nl2br(e($inspection->snapshot['notes'])) !!}</p>
<h2>{{ trans('fiolux.reason') }}</h2>
<p>{!! nl2br(e($inspection->snapshot['reason'])) !!}</p>
@if ($inspection->kind === 'return')
<p>{{ trans('fiolux.original') }}: {{ trans('fiolux.cycle') }} #{{ $inspection->cycle_id }}. {{ trans('fiolux.comparison_note') }}</p>
@endif
<p><br><br>________________________________________<br>{{ trans('fiolux.signature_from') }}</p>
<p><br><br>________________________________________<br>{{ trans('fiolux.signature_to') }}</p>