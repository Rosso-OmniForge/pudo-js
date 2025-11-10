<textarea id="<?php
echo $identifier; ?>" name="
	<?php
		echo $identifier;
	?>
	" class="widefat"
			rows="10"
			<?php
			echo $readonly;
			?>
			>
								<?php
								echo htmlentities(
									$value
								);
								?>
	</textarea>
