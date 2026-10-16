<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmer vos vœux universitaires</title>
</head>
<body>
    <h1>Confirmez vos vœux universitaires</h1>
    <p>Votre code de confirmation INSaf est :</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px">{{ $code }}</p>
    <p>Le code expire dans {{ $minutes }} minutes. Vos vœux ne seront soumis qu’après confirmation de ce code.</p>
    <p>Si vous n’avez pas demandé cette confirmation, ignorez cet e-mail.</p>
    <hr>
    <h1>Confirm your university preferences</h1>
    <p>Your INSaf confirmation code is shown above. It expires in {{ $minutes }} minutes. Your preferences will only be submitted after you confirm this code.</p>
    <p>If you did not request this confirmation, you can ignore this email.</p>
</body>
</html>
