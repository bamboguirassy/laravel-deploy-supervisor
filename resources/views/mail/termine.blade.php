@extends('deploy-supervisor::mail.layout', [
    'titre' => $succes ? 'Déploiement réussi' : 'Déploiement en échec',
    'couleur' => $succes ? '#16a34a' : '#dc2626',
    'uid' => $deploiement->uid,
])

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#475569; margin-bottom:24px;">
    <tr>
        <td style="padding:6px 0; width:160px; color:#94a3b8;">Déclenché par</td>
        <td style="padding:6px 0; font-weight:600;">
            {{ $declenchePar['nom'] ?? 'Déclenché automatiquement (webhook)' }}
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0; color:#94a3b8;">Durée totale</td>
        <td style="padding:6px 0; font-weight:600;">{{ $dureeTotale ?? '—' }}</td>
    </tr>
</table>

@foreach ($cibles as $cible)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">
    <tr>
        <td style="padding:12px 16px; background-color:#f8fafc; border-bottom:1px solid #e2e8f0;">
            <span style="font-weight:700; font-size:14px; color:#1e293b;">{{ $cible['label'] }}</span>
            <span style="float:right; font-size:12px; font-weight:600; color:{{ $cible['statut'] === 'succes' ? '#16a34a' : ($cible['statut'] === 'echec' ? '#dc2626' : '#94a3b8') }};">
                {{ strtoupper($cible['statut']) }}
            </span>
        </td>
    </tr>
    @if ($cible['erreur'])
    <tr>
        <td style="padding:12px 16px;">
            <p style="margin:0; font-size:13px; color:#dc2626;">{{ $cible['erreur'] }}</p>
        </td>
    </tr>
    @endif
    @foreach ($cible['steps'] as $step)
    <tr>
        <td style="padding:10px 16px; {{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-size:13px; color:#334155;">
                        @if ($step['statut'] === 'succes')
                            <span style="color:#16a34a;">&#10003;</span>
                        @elseif ($step['statut'] === 'echec')
                            <span style="color:#dc2626;">&#10007;</span>
                        @else
                            <span style="color:#94a3b8;">&ndash;</span>
                        @endif
                        {{ $step['label'] }}
                    </td>
                    <td style="font-size:12px; color:#94a3b8; text-align:right;">
                        {{ $step['duration_ms'] }} ms
                    </td>
                </tr>
            </table>
            @if ($step['statut'] === 'echec' && ! empty($step['output_tail']))
            <pre style="margin:8px 0 0; background-color:#0f172a; color:#e2e8f0; font-size:12px; line-height:1.5; padding:12px; border-radius:6px; overflow-x:auto; white-space:pre-wrap;">{{ $step['output_tail'] }}</pre>
            @endif
        </td>
    </tr>
    @endforeach
</table>
@endforeach

@if ($lienDetail)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
    <tr>
        <td align="center">
            <a href="{{ $lienDetail }}" style="display:inline-block; background-color:{{ $succes ? '#16a34a' : '#dc2626' }}; color:#ffffff; text-decoration:none; font-weight:600; font-size:14px; padding:12px 24px; border-radius:8px;">
                Voir le détail complet
            </a>
        </td>
    </tr>
</table>
@endif
@endsection
