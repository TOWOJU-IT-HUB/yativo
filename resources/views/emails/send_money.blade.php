<!DOCTYPE html>
<html>
<head>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <p>Hello!</p>
    <p>You have successfully initiated a send money process on the Yativo platform.</p>
    <p>Here are the details of your transaction:</p>
    
    <table>
        <tr>
            <th>Payin Method</th>
            <td>{{ $payment_info['payin_method'] }}</td>
        </tr>
        <tr>
            <th>Exchange Rate</th>
            <td>{{ $payment_info['exchange_rate'] }}</td>
        </tr>
        <tr>
            <th>Payout Amount</th>
            <td>{{ $payment_info['payout_amount'] }}</td>
        </tr>
        <tr>
            <th>Payout Method</th>
            <td>{{ $payment_info['payout_method'] }}</td>
        </tr>
        <tr>
            <th>Total Amount Due</th>
            <td>{{ $payment_info['total_amount_due'] }}</td>
        </tr>
        <tr>
            <th>Estimated Delivery Time</th>
            <td>{{ $payment_info['estimate_delivery_time'] }}</td>
        </tr>
    </table>
    <p><a href="{{ $actionUrl }}">View Transaction</a></p>
    <p>Thank you for using Yativo!</p>
</body>
</html>
