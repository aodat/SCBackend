<!DOCTYPE html>
<html lang="en">

<head>

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style type="text/css" media="all">
        html {
            font-family: sans-serif;
            line-height: 1.15;
            margin: 0;
        }

        strong {
            font-weight: bolder;
        }

        img {
            vertical-align: middle;
            border-style: none;
        }

        table {
            border-collapse: collapse;
        }

        th {
            text-align: inherit;
        }

        h4,
        .h4 {
            margin-bottom: 0.5rem;
            font-weight: 500;
            line-height: 1.2;
        }

        h4,
        .h4 {
            font-size: 1.5rem;
        }

        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
        }

        .table th,
        .table td,
        .box td {
            padding: 0.75rem;
            vertical-align: top;
        }

        .table.table-items td {
            border-top: 1px solid #dee2e6;
        }

        .table thead th,
        .table tbody td,
        .summary tbody td{
            vertical-align: bottom;
            border: 1px solid #dee2e6;
        }

        .mt-5 {
            margin-top: 3rem !important;
        }

        .pr-0,
        .px-0 {
            padding-right: 0 !important;
        }

        .pl-0,
        .px-0 {
            padding-left: 0 !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-uppercase {
            text-transform: uppercase !important;
        }

        body,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        table,
        th,
        tr,
        td,
        p,
        div {
            line-height: 1.1;
        }

        .party-header {
            font-size: 1.5rem;
            font-weight: 400;
        }

        .total-amount {
            font-size: 12px;
            font-weight: 700;
        }

        .border-0 {
            border: none !important;
        }

        .cool-gray {
            color: #6B7280;
        }

        .box {
            border: 1px solid #dee2e6;
        }

        .divider {
            height: 25px;
            border-bottom: 1px solid #dee2e6;
        }

        .font-bold {
            font-weight: bold;
        }

    </style>
</head>

<body>
    <table class="mt-5" width="100%">
        <tbody>
            <tr>
                <td class="border-0 pl-0" width="10%">
                    <img src="https://shipcash.net/_nuxt/img/logo-bw.2b648ef.png" alt="logo" style="width:150px;">
                </td>
                <td class="border-0 pl-0" width="60%"></td>
                <td class="table" width="30%">
                    <table width="100%" class="box">
                        <tr>
                            <td width="40%" class="font-bold">ID</td>
                            <td>{{ $header['merchecnt_id'] }}</td>
                        </tr>
                        <tr>
                            <td width="40%" class="font-bold">Name</td>
                            <td style="white-space: nowrap">{{ $header['merchecnt_name'] }}</td>
                        </tr>
                        <tr>
                            <td width="40%" class="font-bold">Date</td>
                            <td>{{ $header['date'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
    $counter = 1;
    $total_cash = $total_fees = $total_net = 0;
    ?>
    <table class="table mt-5">
        <thead>
            <tr>
                <th width="15px" scope="col" class="text-center">#</th>
                <th width="25%" scope="col" class="text-center">AWB</th>
                <th width="27%" scope="col" class="text-center">Shipper Name</th>
                <th width="15%" scope="col" class="text-center">City</th>
                <th width="10%" scope="col" class="text-center">COD</th>
                <th width="10%" scope="col" class="text-center">Fees</th>
                <th width="10%" scope="col" class="text-center">Net</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cashin as $value)
                <tr>
                    <td width="15px" class="text-center"><?= $counter++ ?></td>
                    <td width="25%" class="text-center">{{ $value['item_id'] }}</td>
                    <td width="27%" class="text-center">{{ $value['shipment_info']['consignee_name'] ?? '' }}</td>
                    <td width="15%" class="text-center">{{ $value['shipment_info']['consignee_city'] ?? '' }}</td>
                    <td width="10%" class="text-center">{{ $total_cash += $value['shipment_info']['cod'] ?? 0 }}
                    </td>
                    <td width="10%" class="text-center">
                        {{ $total_fees += $value['shipment_info']['fees'] ?? 0 }}
                    </td>
                    <td width="10%" class="text-center">
                        {{ $total_net += $value['shipment_info']['net'] ?? 0 }}
                    </td>
                <tr>
                <tr class="border-bottom">
                    <td class="text-center" width="20%">Details</td>
                    <td width="80%" colspan="6" class="text-left">
                        {{ $value['shipment_info']['consignee_address_description'] ?? '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="divider"></div>
    <h4 class="mt-5 font-bold">Summary</h4>
    <table class="table mt-5 summary" style="width:100%">
        <tbody>
            <tr>
                <td width="30%" class="font-bold">Total Cash</td>
                <td><?= $total_cash ?></td>
            </tr>
            <tr>
                <td width="30%" class="font-bold">Total Fees</td>
                <td><?= $total_fees ?></td>
            </tr>
            <tr>
                <td width="40%" class="font-bold">Net</td>
                <td><?= $total_net ?></td>
            </tr>
        </tbody>
    </table>
</body>

</html>
