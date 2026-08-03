@extends('deploy-supervisor::mail.layout', [
    'titre' => 'Déploiement démarré',
    'couleur' => '#2563eb',
    'uid' => $deploiement->uid,
])

@section('content')
<p style="margin:0 0 20px; font-size:15px; color:#334155; line-height:1.6;">
    Un déploiement vient de démarrer sur les cibles suivantes :
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
    <tr>
        <td style="background-color:#eff6ff; border-radius:8px; padding:16px 20px;">
            <p style="margin:0; font-size:15px; font-weight:600; color:#1e3a8a;">
                {{ $cibles }}
            </p>
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#475569;">
    <tr>
        <td style="padding:6px 0; width:160px; color:#94a3b8;">Déclenché par</td>
        <td style="padding:6px 0; font-weight:600;">
            {{ $declenchePar['nom'] ?? 'Déclenché automatiquement (webhook)' }}
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0; color:#94a3b8;">Démarré le</td>
        <td style="padding:6px 0; font-weight:600;">
            {{ $deploiement->demarre_le?->format('d/m/Y H:i:s') }}
        </td>
    </tr>
</table>

@if ($lienDetail)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
    <tr>
        <td align="center">
            <a href="{{ $lienDetail }}" style="display:inline-block; background-color:#2563eb; color:#ffffff; text-decoration:none; font-weight:600; font-size:14px; padding:12px 24px; border-radius:8px;">
                Suivre le déploiement
            </a>
        </td>
    </tr>
</table>
@endif
@endsection
