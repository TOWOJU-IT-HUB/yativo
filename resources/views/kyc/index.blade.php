<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification</title>
    <style>
        .error-message {
            padding: 20px;
            background-color: #f8d7da;
            color: #721c24;
            margin: 20px;
            border-radius: 4px;
        }
        
        iframe {
            border: none;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }
    </style>
</head>
<body>
    @isset($error)
        <div class="error-message">
            {{ $error }}
        </div>
    @endisset

    @isset($kyc_link)
        <iframe src="{{ $kyc_link }}" frameborder="0" scrolling="no"></iframe>
    @endisset

    @if(!isset($error) && !isset($kyc_link))
        <!-- Add default content or loading message here -->
        <div style="padding: 20px; text-align: center;">
            Loading verification page...
        </div>
    @endif
</body>
</html>