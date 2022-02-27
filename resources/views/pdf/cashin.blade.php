<!DOCTYPE html>
<html lang="en">

<head>

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style>
        
		html {
			font-family: sans-serif;
			line-height: 1.15;
			margin: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
			font-weight: 400;
			line-height: 1.5;
			color: #212529;
			text-align: left;
			background-color: #fff;
			font-size: 10px;
			margin: 36pt;
		}

		h4 {
			margin-top: 0;
			margin-bottom: 0.5rem;
		}

		p {
			margin-top: 0;
			margin-bottom: 1rem;
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
		.table td {
			padding: 0.5rem;
		}

		.table thead th {
			vertical-align: bottom;
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

		* {
			font-family: "DejaVu Sans";
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

		.font-bold {
			font-weight: bold;
		}
    </style>
</head>

<body>
    <table class="table mt-5" width="100%">
        <tbody>
            <tr>
                <td class="border-0 pl-0" width="20%">
                    <img src="https://shipcash.net/_nuxt/img/logo-bw.2b648ef.png" alt="logo" style="width:150px;">
                </td>
                <td class="border-0 pl-0" width="40%"></td>
                <td class="DEpl-0" width="40%">
                    <table width="100%">
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
    @foreach ($cashin as $value)
        <table class="table mt-5" width="100%" border="1">
            <thead>
                <tr>
                    <th width="5%" scope="col" class="text-center">#</th>
                    <th width="25%" scope="col" class="text-center">AWB</th>
                    <th width="25%" scope="col" class="text-center">Shipper Name</th>
                    <th width="15%" scope="col" class="text-center">City</th>
                    <th scope="col" class="text-center">COD</th>
                    <th scope="col" class="text-center">Fees</th>
                    <th scope="col" class="text-center">Net</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center"><?= $counter++ ?></td>
                    <td class="text-center">{{ $value['item_id'] }}</td>
                    <td class="text-center">{{ $value['shipment_info']['consignee_name'] ?? '' }}</td>
                    <td class="text-center">{{ $value['shipment_info']['consignee_city'] ?? '' }}</td>
                    <td class="text-center">{{ $total_cash += $value['shipment_info']['cod'] ?? 0 }}</td>
                    <td class="text-center">{{ $total_fees += $value['shipment_info']['fees'] ?? 0 }}</td>
                    <td class="text-center">{{ $total_net += $value['shipment_info']['net'] ?? 0 }}</td>
                <tr>
            </tbody>
            <tfoot>
                <tr>
                    <td width="20%">Details</td>
                    <td width="80%" colspan="6" class="text-left">{{ $value['shipment_info']['consignee_address_description'] ?? ''}}</td>
                </tr>
            </tfoot>
        </table>
    @endforeach

    <table class="table mt-5" style="width:50%" border="1">
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
    </table>
</body>

</html>
