@extends('layouts.app')
@section('title', 'Config History')
@section('page-title', 'Config History')
@section('breadcrumb')
    <a href="{{ route('master.configs.index') }}">Configs</a>
    <span class="breadcrumb-sep">/</span><span>History: {{ $config->config_key }}</span>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Change History — <code>{{ $config->config_key }}</code></span>
        <a href="{{ route('master.configs.edit', Hashids::encode($config->id)) }}" class="btn btn-secondary btn-sm">Edit Config</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Previous Value</th><th>New Value</th><th>Changed By</th><th>Reason</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    @forelse($history as $ver)
                    <tr>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $ver->id }}</td>
                        <td style="font-size:.75rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;">
                            {{ $ver->previous_value ?? '—' }}
                        </td>
                        <td style="font-size:.75rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $ver->new_value ?? '—' }}
                        </td>
                        <td style="font-size:.75rem;">{{ $ver->changed_by ?? '—' }}</td>
                        <td style="font-size:.75rem;color:#64748b;">{{ $ver->change_reason ?? '—' }}</td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $ver->created_at->format('d M Y H:i') }}</td>
                        <td>
                            @if($ver->previous_value)
                            <form method="POST" action="{{ route('master.configs.rollback', Hashids::encode($config->id)) }}" data-confirm="Rollback ke versi ini? Nilai saat ini akan digantikan." data-confirm-type="warning" data-confirm-title="Rollback Config" data-confirm-ok="Ya, Rollback">
                                @csrf
                                <input type="hidden" name="version_id" value="{{ $ver->id }}">
                                <button type="submit" class="btn btn-warning btn-sm">Rollback</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">No history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($history->hasPages())<div style="padding:.75rem 1.25rem;">{{ $history->links() }}</div>@endif
    </div>
</div>
@endsection
