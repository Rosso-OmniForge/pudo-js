<?php

$checked = ( ! empty( $value ) ? ' checked' : '' );
?>
<input type="checkbox" id="
<?php
echo $identifier;
?>
" name="
	<?php
		echo $identifier;
	?>
	"
	<?php
		echo $checked;
	?>
	<?php
		echo $readonly;
	?>
	/>
