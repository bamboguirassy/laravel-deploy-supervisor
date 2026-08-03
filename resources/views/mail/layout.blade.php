<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $titre ?? 'Déploiement' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(15,23,42,0.08);">
                <tr>
                    <td style="background-color:{{ $couleur ?? '#2563eb' }}; padding:28px 32px;">
                        <p style="margin:0; font-size:13px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(255,255,255,0.75); font-weight:600;">
                            Laravel Deploy Supervisor
                        </p>
                        <h1 style="margin:6px 0 0; font-size:22px; color:#ffffff; font-weight:700;">
                            {{ $titre ?? 'Déploiement' }}
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px; background-color:#f8fafc; border-top:1px solid #e2e8f0;">
                        <p style="margin:0; font-size:12px; color:#94a3b8;">
                            Envoyé automatiquement par Laravel Deploy Supervisor — UID <code style="color:#64748b;">{{ $uid ?? '' }}</code>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
