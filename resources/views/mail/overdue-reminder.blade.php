<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Retour des exemplaires — EST Fès</title></head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;line-height:1.6">
    <div style="max-width:640px;margin:auto;background:#fff;padding:28px;border:1px solid #e2e8f0;border-radius:12px">
        <p style="color:#065f46;font-weight:bold">Bibliothèque · EST Fès</p>
        <h1 style="font-size:24px;line-height:1.3">Retour de vos exemplaires en retard</h1>
        <p>Bonjour {{ $report->student->name }},</p>
        <p>La date de retour des exemplaires ci-dessous est dépassée. Merci de les rapporter à la bibliothèque.</p>
        @foreach($report->loans as $loan)
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0">
                <p style="margin:0;font-weight:bold">{{ $loan->title }}</p>
                <p style="margin:4px 0;color:#475569">{{ $loan->author }}</p>
                <p style="margin:4px 0">Exemplaire : {{ $loan->copyQrCode }}</p>
                <p style="margin:4px 0">Date de retour prévue : {{ $loan->dueAt->setTimezone(config('library.timezone'))->format('d/m/Y à H:i') }} (heure du Maroc)</p>
                <p style="margin:4px 0">{{ $loan->level->label() }} — {{ $loan->daysLate }} {{ $loan->daysLate === 1 ? 'jour' : 'jours' }} de retard.</p>
            </div>
        @endforeach
        <p style="margin-top:24px">L’équipe de la bibliothèque de l’EST Fès</p>
    </div>
</body>
</html>
