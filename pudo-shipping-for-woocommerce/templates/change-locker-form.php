<html>
<head>
	<title>TCG Locker Create Shipment</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
			integrity="sha512-MV7K8+y+gLIBoVD59lQIYicR65iaqukzvf/nwasF0nqhPay5w/9lJmVM2hMDcnK1OnMGCdVK+iQrJ7lzPJQd1w=="
			crossorigin="anonymous" referrerpolicy="no-referrer"/>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet"/>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
	<style>
		.container {
			margin-bottom: 5%;
		}

		#locker-full-div {
			margin-top: 5%;
			text-align: center;
		}

		#try-again-div {
			background: #ff6d00;
			border-top-left-radius: 0.5rem;
			border-bottom-left-radius: 0.5rem;
			color: #ffffff;
			margin-top: 5%;
			padding: 4%;
			text-align: left;
		}

		#form-tagline h2 {
			margin-bottom: 15%;
		}

		#form-content-pudo {
			background: #f4f4f4;
			border-top-right-radius: 0.5rem;
			border-bottom-right-radius: 0.5rem;
			margin-top: 5%;
			padding: 3%;
		}

		.form-group {
			margin-top: 5%;
		}

		div .submit-button {
			margin-top: 3%;
			text-align: right;
		}

		button#submit {
			white-space: normal;
			width: auto;
			background: #ff6d00;
			color: #ffffff;
			font-weight: 600;
			width: 25%;
		}

		.pudo-hidden {
			display: none;
		}

		/* Spinner animation */
		@keyframes spin {
			0% {
				transform: rotate(0deg);
			}
			100% {
				transform: rotate(360deg);
			}
		}

		/* Spinner styles */
		.spinner {
			border: 4px solid rgba(0, 0, 0, 0.1);
			border-left-color: #333;
			border-radius: 50%;
			width: 50px;
			height: 50px;
			animation: spin 1s linear infinite;
			margin: 0 auto;
		}

		#spinner {
			margin-top: 2vh;
			text-align: center;
		}
	</style>
</head>
<body>
<div class="container"> <!-- open container -->
	<div class="row"> <!--  open row -->

		<div class="col-12" id="locker-full-div">
			<img style="width:300px"
				src="/wp-content/plugins/pudo-shipping-for-woocommerce/dist/tcg_lockers.png">
			<br>
		</div>
	</div> <!--  close row -->

	<div class="row"> <!--  open row -->
		<div id="try-again-div" class="col-md-4">
		</div>
		<div id="form-content-pudo" class="col-md-8">
			<form id="submit_shipment_form" role="form" class="form-horizontal text-left" name="tcg_form" method="post">
				<input type="hidden" name="orderID" value="<?php
				echo $orderId ?? 0 ?>">
				<input id="service-level-code" type="hidden" name="serviceLevelCode">
				<div class="form-group z-formgroup">
					<strong>Select Method</strong><br>
					<input disabled type="radio" name="pudo-method"
							id="pudo-method-l2l"
							value="l2l"/>
					<label>Locker to Locker</label><br>
					<input disabled type="radio" name="pudo-method"
							id="pudo-method-l2d"
							value="l2d"/>
					<label>Locker to Door</label><br>
					<input disabled type="radio" name="pudo-method"
							id="pudo-method-d2l"
							value="d2l"/>
					<label>Door to Locker</label><br>
					<input disabled type="radio" name="pudo-method"
							id="pudo-method-d2d"
							value="d2d"/>
					<label>Door to Door</label>
				</div>
				<div id="selectionContainer">
					<div class="form-group z-formgroup pudo-source-locker pudo-hidden">
						<strong>Select Source Locker</strong><br>
						<select class="form-control" name="pudo-source-locker" id="pudo-source-locker">
							<?php
							foreach ( $lockers ?? array() as $locker ) {
								if ( $locker['code'] === ( $lockerOriginCode ?? '' ) ) {
									echo '<option selected value="' . $locker['code'] . '">' . $locker['name'] . '</option>';
								} else {
									echo '<option value="' . $locker['code'] . '">' . $locker['name'] . '</option>';
								}
							}
							?>
						</select>
					</div>
					<div class="form-group pudo-destination-locker pudo-hidden z-formgroup">
						<strong>Select Destination Locker</strong><br>
						<select class="form-control" name="pudo-destination-locker" id="pudo-destination-locker">
							<?php
							foreach ( $lockers ?? array() as $locker ) {
								if ( $locker['code'] === ( $lockerDestinationCode ?? '' ) ) {
									echo '<option selected value="' . $locker['code'] . '">' . $locker['name'] . '</option>';
								} else {
									echo '<option value="' . $locker['code'] . '">' . $locker['name'] . '</option>';
								}
							}
							?>
						</select>
					</div>
					<div class="form-group pudo-d2d-pricing pudo-hidden z-formgroup">
						<strong id="d2d-rate-name"></strong><br>
						<div id="d2d-rate"></div>
						<select name="serviceLevelCode" id="d2d-select"></select>
					</div>
					<div class="form-group z-formgroup pudo-locker-size pudo-hidden">
						<strong>Select Locker Type</strong><br>
						<select name="pudo-locker-size" id="pudo-locker-size">
						</select>
					</div>
					<div class="form-group z-formgroup pudo-hidden pudo-submit">
						<div class="col">
							<input type="submit"
									class="btn btn-primary form-control save-account-settings"
									name="btnSubmit"
									value="Continue"
									id="pudo-submit-btn"
						</div>
					</div>
				</div>
			</form>
			<div id="spinner" style="display: none;">
				<div class="spinner"></div>
				<span>Loading...</span>
			</div>
		</div> <!-- close form content div -->
	</div> <!-- close row -->
</div><!--  close container -->
</body>
<script>
	$(document).ready(async function () {
	const submitShipmentForm = $('#submit_shipment_form')
	const radioMethods = $('input[name="pudo-method"]')
	const pudoSourceDiv = $('div.pudo-source-locker')
	const pudoDestinationDiv = $('div.pudo-destination-locker')
	const pudoSubmitDiv = $('div.pudo-submit')
	const lockers =
		<?php
		echo json_encode(
			$lockers ?? array(),
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
		);
		?>

	const orderID = <?php echo $orderId ?? 0; ?>;
	const pudoLockerTypeDiv = $('div.pudo-locker-size')
	const serviceLevelCodeInput = $('#service-level-code')
	const pudoD2DPricingDiv = $('div.pudo-d2d-pricing')

	const pudoSubmitBtn = jQuery('#pudo-submit-btn')

	let method = radioMethods.val() || 'l2l'

	const radioButtons = document.querySelectorAll('input[name="pudo-method"]')
	const pudoSourceLockerSelect = $('select[name="pudo-source-locker"]')
	const pudoDestinationLockerSelect = $('select[name="pudo-destination-locker"]')

	pudoSourceLockerSelect.select2()
	pudoDestinationLockerSelect.select2()

	let pudoSourceLocker = pudoSourceLockerSelect.val()
	let pudoDestinationLocker = pudoDestinationLockerSelect.val()

	const pudoLockerTypeSelect = $('select[name="pudo-locker-size"]')
	const spinner = $('#spinner')
	const selectionContainer = $('#selectionContainer')

	const checkoutLockerType = '<?php echo $checkoutLockerType ?? ''; ?>'

	let pudoLockerType = checkoutLockerType || pudoLockerTypeSelect.val()
	const orderServiceLevelCode = '<?php echo $orderServiceLevelCode ?? ''; ?>'

	let rates
	let locker = lockers[pudoSourceLocker]

	const spinner2 = $('<i class="fas fa-spinner fa-spin"></i>')

	radioButtons.forEach(function (radioButton) {
		radioButton.removeAttribute('disabled')
	})

	// Listeners
	radioMethods.on('change', async function (e) {
		e.preventDefault()
		$('#form-content-pudo').append(spinner2)
		pudoSourceDiv.addClass('pudo-hidden')
		pudoDestinationDiv.addClass('pudo-hidden')
		method = $(this).val()
		await loadMethod(method, pudoSourceLocker, pudoDestinationLocker)
		pudoSubmitDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
		spinner2.remove()
	})

	pudoSourceLockerSelect.on('change', async function (e) {
		e.preventDefault()
		pudoSourceLocker = $(this).val()
		if (method === 'l2l' || method === 'l2d') {
		pudoSourceDiv.addClass('pudo-hidden')
		pudoDestinationDiv.addClass('pudo-hidden')
		await loadMethod(method, pudoSourceLocker, pudoDestinationLocker)
		}
	})

	pudoDestinationLockerSelect.on('change', async function (e) {
		e.preventDefault()
		pudoDestinationLocker = $(this).val()
		if (method === 'l2l' || method === 'd2l') {
		pudoSourceDiv.addClass('pudo-hidden')
		pudoDestinationDiv.addClass('pudo-hidden')
		await loadMethod(method, pudoSourceLocker, pudoDestinationLocker)
		}
	})

	submitShipmentForm.on('submit', async function (event) {
		event.preventDefault()

		spinner.show()
		pudoSubmitBtn.prop('disabled', true)

		try {
		const ajaxSuccess = await new Promise((resolve, reject) => {
			$.ajax({
			url: '/wp-admin/admin-ajax.php',
			type: 'post',
			data: {
				action: 'pudo_submit_shipment',
				pudoPostData: $(this).serialize(),
			},
			success: function (response) {
				const bookingResult = JSON.parse(response)

				if (bookingResult.success === true) {
				alert('Booking confirmed!')
				resolve(true)
				} else {
				alert(`Unsuccessful booking!: ${bookingResult.result.message}`)
				pudoSubmitBtn.prop('disabled', false)
				resolve(false)
				}
			},
			error: function (xhr, status, error) {
				console.error('AJAX error:', error)
				alert('An error occurred while processing the request.')
				reject(false)
			},
			})
		})

		spinner.hide()
		selectionContainer.show()

		if (ajaxSuccess) {
			window.location.href = <?php echo json_encode( $redirectBackUrl ?? '' ); ?>;
		}
		} catch (error) {
		console.error('Error in form submission:', error)
		spinner.hide()
		}
	})

	pudoLockerTypeSelect.on('change', function (e) {
		e.preventDefault()
		pudoLockerType = $(this).val()

		setServiceLevelCode()
	})

	async function loadMethod(method, pudoSourceLocker, pudoDestinationLocker) {
		switch (method) {
		case 'l2l':
			pudoSourceDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
			pudoDestinationDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
			pudoD2DPricingDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			locker = lockers[pudoSourceLocker]
			break
		case 'l2d':
			pudoSourceDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
			pudoDestinationDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			pudoD2DPricingDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			locker = lockers[pudoSourceLocker]
			break
		case 'd2l':
			pudoSourceDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			pudoDestinationDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
			pudoD2DPricingDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			locker = lockers[pudoDestinationLocker]
			break
		case 'd2d':
			pudoSourceDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			pudoDestinationDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			pudoD2DPricingDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
			break
		}

		pudoLockerTypeDiv.addClass('pudo-hidden').removeClass('pudo-displayed')
		pudoSubmitDiv.addClass('pudo-hidden').removeClass('pudo-displayed')

		if (method === 'l2l') {
		if (await getRates('L2L', pudoSourceLocker, pudoDestinationLocker)) {
			populateLockerTypes(locker, method)
			pudoSubmitDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
		}
		setServiceLevelCode()
		} else if (method === 'd2l') {
		if (await getRates('D2L', null, pudoDestinationLocker)) {
			populateLockerTypes(locker, method)
			pudoSubmitDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
		}
		setServiceLevelCode()
		} else if (method === 'l2d') {
		if (await getRates('L2D', pudoSourceLocker, null)) {
			populateLockerTypes(locker, method)
			pudoSubmitDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
		}
		setServiceLevelCode()
		} else if (method === 'd2d') {
		if (await getRates('D2D', null, null)) {
			pudoD2DPricingDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
			populateD2DRates()
			pudoSubmitDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
		}
		}
	}

	function setServiceLevelCode() {
		const selectedOption = pudoLockerTypeSelect.find('option:selected')
		const serviceLevelCode = selectedOption.data('servicelevelcode')
		serviceLevelCodeInput.val(serviceLevelCode)
	}

	async function getRates(method, collectionAddress, deliveryAddress) {
		const data = {
		collectionAddress: collectionAddress,
		deliveryAddress: deliveryAddress,
		method: method,
		orderID: orderID,
		lockerSize: pudoLockerTypeSelect.val()
		}

		try {
		selectionContainer.hide()
		$('#form-content-pudo').append(spinner2)

		return await new Promise((resolve, reject) => {
			$.ajax({
			url: '/wp-admin/admin-ajax.php',
			type: 'post',
			data: {
				action: 'pudo_get_rates',
				pudoPost: data
			},
			success: function (rateResponse) {
				try {
				const rateResult = JSON.parse(rateResponse)

				if (!rateResult.success) {
					alert(rateResult.message)
					reject(`Failed to get rate: ${rateResult.message}`)
				}

				rates = JSON.parse(rateResult.rates)
				resolve(true) // Indicate success
				} catch (parseError) {
				reject(`Error parsing rate response: ${parseError.message}`)
				}
			},
			error: function (xhr, status, error) {
				reject(`AJAX error: ${error}`)
			}
			})
		})
		} catch (error) {
		console.error('Error in getRates:', error)
		alert(error)
		return false
		} finally {
		selectionContainer.show()
		spinner2.remove()
		}
	}

	const populateD2DRates = function () {
		$('#d2d-select').empty()

		rates.rates.forEach(rate => {
		const serviceLevel = rate.service_level

		const option = $('<option>', {
			value: serviceLevel.code,
			text: `${serviceLevel.name} - ${rate.rate}`
		})

		$('#d2d-select').append(option)
		})
	}

	const populateLockerTypes = function () {
		pudoLockerTypeSelect.empty()

		rates.rates.forEach((rate) => {
		let price = rate.rate
		let serviceLevelCode = rate.service_level.code
		let boxType = rate.service_level.box_type
		let name = rate.service_level.box_type_name
		let option = `<option value=${boxType} data-price=${price}
data-serviceLevelCode='${serviceLevelCode}' ${serviceLevelCode === orderServiceLevelCode ? 'selected' : ''}>
${name}: R${price}</option>`
		pudoLockerTypeSelect.append(option)
		})
		pudoLockerTypeDiv.removeClass('pudo-hidden').addClass('pudo-displayed')
	}
	})
</script>
</html>
