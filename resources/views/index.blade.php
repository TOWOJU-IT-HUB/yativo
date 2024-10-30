<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading.....</title>
    <style>
        /* Styles for the loading icon */
        #loadingIcon {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 48px;
            color: #000;
            display: none;
        }

        /* Optional: Style the background overlay */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            z-index: 999;
        }
    </style>
</head>

<body>
    <!-- Background overlay -->
    <div id="loadingOverlay"></div>

    <!-- Loading icon -->
    <div id="loadingIcon">
        &#128274; <!-- Lock icon as a placeholder for login icon -->
    </div>

    <form method="post" action="https://account.volet.com/sci/" id="advcashPayinForm">
        <input type="hidden" name="ac_account_email" value="michael@zeenah.app" />
        <input type="hidden" name="ac_sci_name" value="Yativo.com" />
        <input type="hidden" name="ac_amount" value="{{ $deposit['txn_amount'] }}" />
        <input type="hidden" name="ac_currency" value="{{ $deposit['txn_currency'] }}" />
        <input type="hidden" name="ac_order_id" value="{{ $deposit['txn_id'] }}" />
        <input type="hidden" name="ac_sign" value="44634f8b692e11cc1a91e6b5b966d6cadcff3146f38dd6f3f7c147b410f97fb0" />
        <!-- Optional Fields -->
        <input type="hidden" name="ac_success_url" value="https://api.yativo.com/volet/payin/success/" />
        <input type="hidden" name="ac_success_url_method" value="POST" />
        <input type="hidden" name="ac_fail_url" value="https://api.yativo.com/volet/payin/fail/" />
        <input type="hidden" name="ac_fail_url_method" value="POST" />
        <input type="hidden" name="ac_status_url" value="{{ route('advcash.payin.webhook', ['txn_type' => $deposit['txn_type'], 'user_id' => $deposit['user_id']]) }}" />
        <input type="hidden" name="ac_status_url_method" value="POST" />
        <input type="hidden" name="ac_comments" value="Comment" />
        {{-- <input type="submit" /> --}}
    </form>
    <script type="text/javascript">
        // Show the loading icon and overlay, then submit the form
        document.addEventListener("DOMContentLoaded", function () {
            // Display the loading icon and overlay
            document.getElementById('loadingIcon').style.display = 'block';
            document.getElementById('loadingOverlay').style.display = 'block';

            // Submit the form
            document.getElementById('advcashPayinForm').submit();
        });
    </script>
</body>

</html>
