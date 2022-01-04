<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Shipments</title>

        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
    </head>
    <body>
        <div class="container mt-5">
            <center><h4>Transactions</h4></center>
            <table class="table table-bordered mb-5" border="1" width="100%">
                <thead>
                    <tr class="table-danger">
                        <th scope="col">Type</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Balance After</th>
                        <th scope="col">Description</th>
                        <th scope="col">Notes</th>
                        <th scope="col">Status</th>
                        <th scope="col">Source</th>
                        <th scope="col">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                        <tr>
                            <td> {{ $transaction['type'] }} </td>
                            <td> {{ $transaction['amount'] }} </td>
                            <td> {{ $transaction['balance_after'] }} </td>
                            <td> {{ $transaction['description'] }} </td>
                            <td> {{ $transaction['notes'] }} </td>
                            <td> {{ $transaction['status']  }} </td>
                            <td> {{ $transaction['source'] }} </td>
                            <td> {{ date('Y-m-d', strtotime($transaction['created_at'])) }} </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </body>
</html>