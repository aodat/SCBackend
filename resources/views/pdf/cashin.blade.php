<!DOCTYPE html>
<html lang="en">

<head>

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style type="text/css" media="all">
        html {
            font-family: sans-serif;
            margin: 0;
        }

        table {
            border-collapse: collapse;
        }
        th {
            font-weight: bold !important;
            font-size: 0.75rem !important
        }
        td {
            font-weight: normal !important;
            font-size: 0.75rem !important
        }
        h4,
        .h4 {
            margin-bottom: 0.5rem;
            font-weight: 500;
            line-height: 1.2;
            font-size: 1.5rem;
        }

        .table {
            width: 100%;
            color: #212529;
        }

        .table th,
        .table td{
            padding: 0.65rem;
            vertical-align: middle;
        }

        .box td {
            padding: 0.25rem;
        }

        .table thead th,
        .table tbody td,
        .summary tbody td{
            vertical-align: middle;
            border: 1px solid #dee2e6;
            border-bottom:0 !important;
        }

        .mt-5 {
            margin-top: 3rem !important;
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

        .border-0 {
            border: none !important;
        }

        .box {
            border: 1px solid #dee2e6;
        }

        .divider {
            height: 25px;
            border-bottom: 1px solid #dee2e6;
        }
        .divider-table{
            height: 1px;
            border-top: 1px solid #dee2e6;
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
                <td class="border-0 pl-0" width="450px">
                    <img src="https://shipcash.net/_nuxt/img/logo-bw.2b648ef.png" alt="logo" style="width:150px;">
				</td>
                <td width="20%">
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
                <th width="5%"  scope="col" class="text-center">#</th>
                <th width="20%" scope="col" class="text-center">AWB</th>
                <th width="25%" scope="col" class="text-center">Shipper Name</th>
                <th width="10%" scope="col" class="text-center">City</th>
                <th width="15%" scope="col" class="text-center">Weight (KG)</th>
                <th width="7%" scope="col" class="text-center">COD</th>
                <th width="7%" scope="col" class="text-center">Fees</th>
                <th width="7%" scope="col" class="text-center">Net</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cashin as $value)
                <tr>
                    <td width="5%" class="text-center"><?= $counter++ ?></td>
                    <td width="20%" class="text-center">{{ $value['item_id'] }}</td>
                    <td width="25%" class="text-center">{{ $value['shipment_info']['consignee_name'] ?? '' }}</td>
                    <td width="10%" class="text-center">{{ $value['shipment_info']['consignee_city'] ?? '' }}</td>
                    <td width="15%" class="text-center">{{ $value['shipment_info']['chargable_weight'] }}</td>
                    <td width="7%" class="text-center">{{ $total_cash += $value['shipment_info']['cod'] ?? 0 }}
                    </td>
                    <td width="7%" class="text-center">
                        {{ $total_fees += $value['shipment_info']['fees'] ?? 0 }}
                    </td>
                    <td width="7%" class="text-center">
                        {{ $total_net += $value['shipment_info']['net'] ?? 0 }}
                    </td>
                <tr>
                <tr>
                    <td colspan="8">
                        <span>Details : </span>
                        {{ $value['shipment_info']['consignee_address_description'] ?? '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="divider-table"></div>
    <div class="divider"></div>
    <h4 class="font-bold">Summary</h4>
    <div class="divider no-border"></div>
    <table class="table summary" style="width:100%">
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
    <div class="divider-table"></div>
</body>

</html>
