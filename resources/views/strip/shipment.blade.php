<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Shipcash - Payment</title>
		<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">

        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
		<style>body {
			background: #ddd;
			min-height: 100vh;
			vertical-align: middle;
			display: flex
			}
			.card {
			margin: auto;
			width: 600px;
			padding: 3rem 3.5rem;
			box-shadow: 0 6px 20px 0 rgba(0, 0, 0, 0.19)
			}
			.mt-50 {
			margin-top: 50px
			}
			.mb-50 {
			margin-bottom: 50px
			}
			@media(max-width:767px) {
			.card {
			width: 90%;
			padding: 1.5rem
			}
			}
			@media(height:1366px) {
			.card {
			width: 90%;
			padding: 8vh
			}
			}
			.card-title {
			font-weight: 700;
			font-size: 2.5em
			}
			.nav {
			display: flex
			}
			.nav ul {
			list-style-type: none;
			display: flex;
			padding-inline-start: unset;
			margin-bottom: 6vh
			}
			.nav li {
			padding: 1rem
			}
			.nav li a {
			color: black;
			text-decoration: none
			}
			.active {
			border-bottom: 2px solid black;
			font-weight: bold
			}
			input {
			border: none;
			outline: none;
			font-size: 1rem;
			font-weight: 600;
			color: #000;
			width: 100%;
			min-width: unset;
			background-color: transparent;
			border-color: transparent;
			margin: 0
			}
			form a {
			color: grey;
			text-decoration: none;
			font-size: 0.87rem;
			font-weight: bold
			}
			form a:hover {
			color: grey;
			text-decoration: none
			}
			form .row {
			margin: 0;
			overflow: hidden
			}
			form .row-1 {
			border: 1px solid rgba(0, 0, 0, 0.137);
			padding: 0.5rem;
			outline: none;
			width: 100%;
			min-width: unset;
			border-radius: 5px;
			background-color: rgba(221, 228, 236, 0.301);
			border-color: rgba(221, 228, 236, 0.459);
			margin: 2vh 0;
			overflow: hidden
			}
			form .row-2 {
			border: none;
			outline: none;
			background-color: transparent;
			margin: 0;
			padding: 0 0.8rem
			}
			form .row .row-2 {
			border: none;
			outline: none;
			background-color: transparent;
			margin: 0;
			padding: 0 0.8rem
			}
			form .row .col-2,
			.col-7,
			.col-3 {
			display: flex;
			align-items: center;
			text-align: center;
			padding: 0 1vh
			}
			form .row .col-2 {
			padding-right: 0
			}
			#card-header {
			font-weight: bold;
			font-size: 0.9rem
			}
			#card-inner {
			font-size: 0.7rem;
			color: gray
			}
			.three .col-7 {
			padding-left: 0
			}
			.three {
			overflow: hidden;
			justify-content: space-between
			}
			.three .col-2 {
			border: 1px solid rgba(0, 0, 0, 0.137);
			padding: 0.5rem;
			outline: none;
			width: 100%;
			min-width: unset;
			border-radius: 5px;
			background-color: rgba(221, 228, 236, 0.301);
			border-color: rgba(221, 228, 236, 0.459);
			margin: 2vh 0;
			width: fit-content;
			overflow: hidden
			}
			.three .col-2 input {
			font-size: 0.7rem;
			margin-left: 1vh
			}
			.btn {
			width: 100%;
			background-color: rgb(65, 202, 127);
			border-color: rgb(65, 202, 127);
			color: white;
			justify-content: center;
			padding: 2vh 0;
			margin-top: 3vh
			}
			.btn:focus {
			box-shadow: none;
			outline: none;
			box-shadow: none;
			color: white;
			-webkit-box-shadow: none;
			-webkit-user-select: none;
			transition: none
			}
			.btn:hover {
			color: white
			}
			input:focus::-webkit-input-placeholder {
			color: transparent
			}
			input:focus:-moz-placeholder {
			color: transparent
			}
			input:focus::-moz-placeholder {
			color: transparent
			}
			input:focus:-ms-input-placeholder {
			color: transparent
			}
            .payment-details {
                padding: 0;
                line-height: 30px;
                margin:15px 0;
            }
            .payment-details .col-4 {
                font-weight: bold;
            }
            .no-padding {
                padding: 0
            }
            .payment-desc {
                padding: 0;
                font-weight: bold;
            }
		</style>
	</head>
	<body class="snippet-body">
		<div class="card mt-50 mb-50">
			<div class="card-title mx-auto text-center"> 
                <img src="https://beta.shipcash.net/_nuxt/img/logo-bw.2b648ef.png" style="width:50%;"/>
                <br>
            </div>
			<div class="nav">
			</div>
            @if (Session::has('success'))
                <div class="alert alert-success text-center">
                    <a href="#" class="close" data-dismiss="alert" aria-label="close">×</a>
                    <p>{{ Session::get('success') }}</p>
                </div>
            @endif
            <form role="form" method="post" class="require-validation" data-cc-on-file="false"
                data-stripe-publishable-key="{{ env('STRIPE_KEY') }}"
                action="{{ route('stripe.post') }}" id="payment-form">
                @csrf
                <br>

				<div class="row">
                    <div class="col-12 payment-desc">
                        This is payment information details :
                    </div>
                    <div class="col-12 payment-details">
                        <div class="row">
                            <div class="col-4 no-padding">
                                Full Name
                            </div>
                            <div class="col-8 no-padding">
                                {{ $shipment->sender_name }}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4 no-padding">
                                Email
                            </div>
                            <div class="col-8 no-padding">
                                {{ $shipment->sender_email }}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4 no-padding">
                                Phone Number
                            </div>
                            <div class="col-8 no-padding">
                                {{ $shipment->sender_phone }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row-1">
					<div class="row row-2"> <span id="card-inner">Card holder name</span> </div>
					<div class="row row-2"> <input type="text" placeholder="Bojan Viner"> </div>
				</div>
				<div class="row three">
					<div class="col-7">
						<div class="row-1">
							<div class="row row-2"> <span id="card-inner">Card number</span> </div>
							<div class="row row-2"> <input type="text" placeholder="5134-5264-4"> </div>
						</div>
					</div>
					<div class="col-2"> <input type="text" placeholder="MM / YY"> </div>
					<div class="col-2"> <input type="text" placeholder="CVV"> </div>
				</div>
				<button class="btn d-flex mx-auto">Confirm Payment <b>{{ $shipment['payment'] }}</b></button>
			</form>
		</div>
		<script type="text/javascript" src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="https://js.stripe.com/v2/"></script>
        <script type="text/javascript">
            $(function() {
                var $form = $(".require-validation");
                $('[data-toggle="tooltip"]').tooltip()
                $('form.require-validation').bind('submit', function(e) {
                    var $form = $(".require-validation"),
                        inputSelector = ['input[type=email]', 'input[type=password]',
                            'input[type=text]', 'input[type=file]',
                            'textarea'
                        ].join(', '),
                        $inputs = $form.find('.required').find(inputSelector),
                        $errorMessage = $form.find('div.error'),
                        valid = true;
                    $errorMessage.addClass('hide');
                    $('.has-error').removeClass('has-error');
                    $inputs.each(function(i, el) {
                        var $input = $(el);
                        if ($input.val() === '') {
                            $input.parent().addClass('has-error');
                            $errorMessage.removeClass('hide');
                            e.preventDefault();
                        }
                    });
                    if (!$form.data('cc-on-file')) {
                        e.preventDefault();
                        Stripe.setPublishableKey($form.data('stripe-publishable-key'));
                        Stripe.createToken({
                            number: $('.card-number').val(),
                            cvc: $('.card-cvc').val(),
                            exp_month: $('.card-expiry-month').val(),
                            exp_year: $('.card-expiry-year').val()
                        }, stripeResponseHandler);
                    }
                });
            
                function stripeResponseHandler(status, response) {
                    if (response.error) {
                        $('.error')
                            .removeClass('hide')
                            .find('.alert')
                            .text(response.error.message);
                    } else {
                        /* token contains id, last4, and card type */
                        var token = response['id'];
                        $form.find('input[type=text]').empty();
                        $form.append("<input type='hidden' name='stripeToken' value='" + token + "'/>");
                        $form.get(0).submit();
                    }
                }
            });
        </script>
	</body>
</html>