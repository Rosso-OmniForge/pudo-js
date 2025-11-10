<?php

/**
 * @author The Courier Guy
 * @package ls-framework/core
 */
class PudoPluginDependencies {



	private $activePlugins      = array();
	private $invalidatedPlugins = array();
	private $pluginFile;

	/**
	 * PudoPluginDependencies constructor.
	 *
	 * @param $pluginFile
	 */
	public function __construct( $pluginFile ) {
		$this->setPluginFile( $pluginFile );
		if ( ! $this->activePlugins ) {
			$this->activePlugins = (array) get_option( 'active_plugins', array() );
			if ( is_multisite() ) {
				$this->activePlugins = array_merge(
					$this->activePlugins,
					get_site_option( 'active_sitewide_plugins', array() )
				);
			}
		}
		add_action( 'activated_plugin', array( $this, 'installPluginAfterWoocommerce' ) );
	}

	/**
	 *
	 */
	public function installPluginAfterWoocommerce() {
		$pluginFileBaseName    = plugin_basename( $this->getPluginFile() );
		$woocommercePluginPath = 'woocommerce/woocommerce.php';
		$plugins               = get_option( 'active_plugins' );
		if ( ! empty( $plugins ) ) {
			$pluginKey            = array_search( $pluginFileBaseName, $plugins );
			$woocommercePluginKey = array_search( $woocommercePluginPath, $plugins );
			if ( $pluginKey < $woocommercePluginKey ) {
				array_splice( $plugins, $pluginKey, 1, $woocommercePluginPath );
				array_splice( $plugins, $woocommercePluginKey, 1, $pluginFileBaseName );
				update_option( 'active_plugins', $plugins );
			}
		}
	}

	/**
	 * @param array $dependencies
	 *
	 * @return bool
	 */
	public function checkDependencies( $dependencies = array() ) {
		$dependenciesValidated = true;
		array_walk(
			$dependencies,
			function ( $dependencyValues, $dependencyPath ) {
				if ( ! $this->validateDependency( $dependencyPath ) ) {
					$this->addInvalidatedPlugins( $dependencyPath, $dependencyValues );
				}
			}
		);
		$invalidPlugins = $this->getInvalidatedPlugins();
		if ( ! empty( $invalidPlugins ) ) {
			$dependenciesValidated = false;
			add_action( 'admin_notices', array( $this, 'addInvalidatedPluginNotice' ) );
		}

		return $dependenciesValidated;
	}

	/**
	 *
	 */
	public function addInvalidatedPluginNotice() {
		$invalidatedPlugins = $this->getInvalidatedPlugins();
		array_walk(
			$invalidatedPlugins,
			function ( $invalidatedPlugin ) {
				$notice = sprintf( __( $invalidatedPlugin['notice'] ) );
				?>
				<div id="message" class="error">
					<p>
						<?php
						echo $notice;
						?>
					</p>
				</div>
				<?php
			}
		);
	}

	/**
	 * @param string $dependencyPath
	 *
	 * @return bool
	 */
	private function validateDependency( $dependencyPath ) {
		return in_array( $dependencyPath, $this->activePlugins ) || array_key_exists(
			$dependencyPath,
			$this->activePlugins
		);
	}

	/**
	 * @return array
	 */
	private function getInvalidatedPlugins() {
		return $this->invalidatedPlugins;
	}

	/**
	 * @param array $invalidatedPlugins
	 */
	private function setInvalidatedPlugins( $invalidatedPlugins ) {
		$this->invalidatedPlugins = $invalidatedPlugins;
	}

	/**
	 * @param string $invalidatedPluginPath
	 * @param array  $invalidatedPluginValues
	 */
	private function addInvalidatedPlugins( $invalidatedPluginPath, $invalidatedPluginValues ) {
		$invalidatedPlugins                           = $this->getInvalidatedPlugins();
		$invalidatedPlugins[ $invalidatedPluginPath ] = $invalidatedPluginValues;
		$this->setInvalidatedPlugins( $invalidatedPlugins );
	}

	/**
	 * @return string
	 */
	private function getPluginFile() {
		return $this->pluginFile;
	}

	/**
	 * @param string $pluginFile
	 */
	private function setPluginFile( $pluginFile ) {
		$this->pluginFile = $pluginFile;
	}
}
