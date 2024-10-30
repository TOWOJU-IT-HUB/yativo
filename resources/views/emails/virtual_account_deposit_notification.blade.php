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
    <p>Hello {{ $beneficiaryName }}!</p>
    <p>You have successfully received a deposit to your virtual account on the Yativo platform.</p>
    <p>Here are the details of your transaction:</p>

    <table>
        <tr>
            <th>Transaction Type</th>
            <td>{{ $transactionType }}</td>
        </tr>
        <tr>
            <th>External ID</th>
            <td>{{ $externalId }}</td>
        </tr>
        <tr>
            <th>Amount</th>
            <td>{{ $amount }} {{ $currency }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>{{ $statusDescription }}</td>
        </tr>
        <tr>
            <th>Payer</th>
            <td>{{ $payerName }}</td>
        </tr>
        <tr>
            <th>Reference Code</th>
            <td>{{ $referenceCode }}</td>
        </tr>
        <tr>
            <th>Creation Date</th>
            <td>{{ $creationDate }}</td>
        </tr>
    </table>

    <p><a href="{{ $actionUrl }}">View Transaction</a></p>
    <p>Thank you for using Yativo!</p>
</body>
</html>
