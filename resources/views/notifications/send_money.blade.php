<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Money Transaction Initiated</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        th {
            text-align: left;
            background-color: #f2f2f2;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Send Money Transaction Initiated</h1>

        <p>You have successfully initiated a send money process on the Yativo platform.</p>

        <h2>Transaction Details</h2>

        <table>
            <tr>
                <th>Send Amount</th>
                <td>{{ $raw_data->payment_info->send_amount }} {{$raw_data->send_gateway->currency }}</td>
            </tr>
            <tr>
                <th>Transaction Fee</th>
                <td>{{ $raw_data->payment_info->transaction_fee}} {{$raw_data->send_gateway->currency}}</td>
            </tr>
            <tr>
                <th>Estimated Delivery Time</th>
                <td>{{ $raw_data->payment_info->estimate_delivery_time }}</td>
            </tr>
            <tr>
                <th>Payin Method</th>
                <td>{{ $raw_data->payment_info->payin_method }}</td>
            </tr>
            <tr>
                <th>Payout Method</th>
                <td>{{ $raw_data->payment_info->payout_method }}</td>
            </tr>
            <tr>
                <th>Exchange Rate</th>
                <td>{{ $raw_data->payment_info->exchange_rate }}</td>
            </tr>
            <tr>
                <th>Receive Amount</th>
                <td>{{ $raw_data->payment_info->payout_amount }} {{$raw_data->receive_currency }}</td>
            </tr>
        </table>

        <h2>Beneficiary Details</h2>

        <table>
            <tr>
                <th>Name</th>
                <td>{{ $raw_data->beneficiary->customer_name }}</td>
            </tr>
            <tr>
                <th>Email</th>
                <td>{{ $raw_data->beneficiary->customer_email }}</td>
            </tr>
            <tr>
                <th>Address</th>
                <td>{{ $beneficiary->customer_address->address_line_1 }}, {{ $beneficiary->customer_address->city }}, {{ $beneficiary->customer_address->county }}, {{ $beneficiary->customer_address->postal_code }}</td>
            </tr>
        </table>

        <p>
            <a href="{{ $actionUrl }}" class="button">View Transaction</a>
        </p>

        <p>Thank you for using Yativo!</p>

        <p>
            Regards,<br>
            Yativo Team
        </p>
    </div>
</body>
</html>
