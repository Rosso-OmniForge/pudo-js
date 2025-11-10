<h2>The Courier Guy Locker-Woocommerce Plugin Settings</h2>
<form action="options.php" method="post">
	<?php
	settings_fields( 'pudo_woocommerce' );

	?>
	<hr>
	<h5>The Courier Guy Locker API URL</h5>
	<input type="text"
			id="api-url"
			name="pudo_api_url"
			style="width:600px;"
			value="<?php echo $apiURL ?? ''; ?>"
			onchange="credentialsValidation()">
	<br>
	<br>
	<h5>The Courier Guy Locker API Key</h5>
	<input type="text"
			id="api-key"
			name="pudo_account_key"
			style="width:600px;"
			value="<?php echo $apiKey ?? ''; ?>"
			onchange="credentialsValidation()">
	<span id="credentials-validation-span"></span>
	<br>
	<br>
	<hr>
	<h5>OSM Map Email</h5>
	<input type="text"
			name="pudo_osm_email"
			style="width:600px;"
			value="<?php echo( $pudoOSMEmail ?? '' ); ?>">
	<br>
	<hr>
	<h5>Use OSM Map for Locker Selection</h5>
	<select name="pudo_use_osm_map">
		<option value="true"
			<?php
			echo ( ( $pudoUseOSMMap ?? '' ) === 'true' ) ? 'selected' : '';
			?>
		>True
		</option>
		<option value="false"
			<?php
			echo ( ( $pudoUseOSMMap ?? '' ) === 'false' || ( $pudoUseOSMMap ?? '' ) === '' ) ? 'selected' : '';
			?>
		>False
		</option>
	</select>
	<hr>

	<input name="submit"
			class="button button-primary"
			type="submit"
			value="<?php esc_attr_e( 'Save' ); ?>"
	/>
</form>

<script>
	function credentialsValidation() {
	let apiKey = document.getElementById('api-key').value
	let apiUrl = document.getElementById('api-url').value

	if (apiKey && apiUrl) {
		var xmlHttp = new XMLHttpRequest()
		xmlHttp.open('GET', apiUrl + '/api/v1/lockers-data', false) // false for synchronous request
		xmlHttp.setRequestHeader('Authorization', 'Bearer ' + apiKey)
		xmlHttp.send(null)
		if (xmlHttp.status === 200) {
		document.getElementById('credentials-validation-span').innerHTML = '&#9745;'
		} else {
		document.getElementById('credentials-validation-span').innerHTML = '&#10005;'
		}
	}
	}

	credentialsValidation()
</script>
