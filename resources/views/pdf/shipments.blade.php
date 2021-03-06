<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        
        <title>Shipments Report</title>
    </head>
    <body>
        <div class="container mt-5">
            <center><h4>Shipments</h4></center>
            <table class="table table-bordered mb-5" border="1" width="100%">
                <thead>
                    <tr class="table-danger">
                        <th scope="col">Sender Name</th>
                        <th scope="col">Consignee Name</th>
                        <th scope="col">Consignee phone</th>
                        <th scope="col">City</th>
                        <th scope="col">Area</th>
                        <th scope="col">Address</th>
                        <th scope="col">COD</th>
                        <th scope="col">Delivery Date</th>
                        <th scope="col">Pieces</th>
                        <th scope="col">Shipment Content</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shipments as $shipment)
                        <tr>
                            <td> {{ $shipment->sender_name }}</td>
                            <td> {{ $shipment->consignee_name }} </td>
                            <td> {{ $shipment->consignee_phone }} </td>
                            <td> {{ $shipment->consignee_city }} </td>
                            <td> {{ $shipment->consignee_area }} </td>
                            <td> {{ $shipment->consignee_address_description  }} </td>
                            <td> {{ $shipment->cod }} </td>
                            <td> {{ date('Y-m-d', strtotime($shipment->delivered_at)) }} </td>
                            <td> {{ $shipment->pieces }} </td>
                            <td> {{ $shipment->content }} </td>
                            <td> {{ $shipment->status }} </td>
                            <td> {{ date('Y-m-d', strtotime($shipment->created_at)) }} </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </body>
</html>