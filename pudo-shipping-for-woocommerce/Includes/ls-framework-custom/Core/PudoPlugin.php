<?php

/**
 * @author The Courier Guy
 * @package ls-framework/core
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

class PudoPlugin {



	private $pluginTextDomain = '';
	private $pluginName       = '';
	private $pluginBaseName   = '';
	private $pluginData       = array();
	private $pluginUrl        = '';
	private $pluginPath       = '';
	private $pluginUploadUrl  = '';
	private $pluginUploadPath = '';
	private $file             = '';
	private $version          = '';
	private $options          = array();

	/**
	 * PudoPlugin constructor.
	 *
	 * @param $file
	 */
	public function __construct( $file ) {
		$pluginData = get_plugin_data( $file, true, false );
		$this->setFile( $file );
		$this->setPluginData( $pluginData );
		$this->setPluginName( $pluginData['Name'] );
		$this->setPluginTextDomain( $pluginData['TextDomain'] );
		$this->setPluginBaseName( plugin_basename( $file ) );
		$this->setPluginUrl( trailingslashit( plugins_url( '', $plugin = $file ) ) );
		$this->setPluginPath( trailingslashit( dirname( $file ) ) );
		$this->setVersion( $pluginData['Version'] );
		$this->registerModel();
		add_action( 'init', array( &$this, 'init' ), 999 );
		add_action( 'admin_init', array( &$this, 'initAdmin' ), 999 );
	}

	/**
	 *
	 */
	public function init() {
		$textDomain = $this->getPluginTextDomain();
		$this->setOptions( get_option( $textDomain . '_options', array() ) );
		do_action( $textDomain . '_init' );
	}

	/**
	 *
	 */
	public function initAdmin() {
	}

	/**
	 *
	 */
	public function activatePlugin() {
		update_option( $this->getPluginTextDomain() . '_installed', 1 );
		flush_rewrite_rules();
	}

	/**
	 *
	 */
	public function deactivatePlugin() {
		delete_option( $this->getPluginTextDomain() . '_installed' );
	}

	/**
	 * @return string
	 */
	public function getPluginBaseName() {
		return $this->pluginBaseName;
	}

	/**
	 * @return string
	 */
	public function getPluginUrl() {
		return $this->pluginUrl;
	}

	/**
	 * @return string
	 */
	public function getPluginPath() {
		return $this->pluginPath;
	}

	/**
	 * @return string
	 */
	public function getPluginUploadPath() {
		if ( empty( $this->pluginUploadPath ) ) {
			$this->buildPluginUploadPath();
		}

		return $this->pluginUploadPath;
	}

	/**
	 * @return string
	 */
	public function getPluginUploadUrl() {
		if ( empty( $this->pluginUploadUrl ) ) {
			$this->buildPluginUploadPath();
		}

		return $this->pluginUploadUrl;
	}

	/**
	 * @return string
	 */
	public function getFile() {
		return $this->file;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * @return array
	 */
	public function getPluginData() {
		return $this->pluginData;
	}

	/**
	 * @return string
	 */
	public function getPluginName() {
		return $this->pluginName;
	}

	/**
	 * @return string
	 */
	public function getPluginTextDomain() {
		return $this->pluginTextDomain;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 *
	 */
	protected function registerModel() {
		die( 'function PudoPlugin::registerModel() must be over-ridden in a sub-class.' );
	}

	/**
	 * @param string $resourceFileName
	 */
	protected function registerCSSResource( $resourceFileName ) {
		$textDomain = $this->getPluginTextDomain();
		wp_register_style(
			$textDomain . '-' . $resourceFileName,
			$this->getPluginUrl() . 'dist/css/' . $resourceFileName,
			array(),
			$this->getVersion(),
			'all'
		);
		wp_enqueue_style( $textDomain . '-' . $resourceFileName );
	}

	/**
	 * @param string $resourceFileName
	 * @param array  $dependencies
	 */
	protected function registerJavascriptResource( $resourceFileName, $dependencies = array() ) {
		$textDomain = $this->getPluginTextDomain();
		wp_register_script(
			$textDomain . '-' . $resourceFileName,
			$this->getPluginUrl() . 'dist/js/' . $resourceFileName,
			$dependencies,
			$this->getVersion(),
			true
		);
		wp_enqueue_script( $textDomain . '-' . $resourceFileName );
	}

	/**
	 * @param string $pluginBaseName
	 */
	private function setPluginBaseName( $pluginBaseName ) {
		$this->pluginBaseName = $pluginBaseName;
	}

	/**
	 * @param string $pluginUrl
	 */
	private function setPluginUrl( $pluginUrl ) {
		$this->pluginUrl = $pluginUrl;
	}

	/**
	 * @param string $pluginPath
	 */
	private function setPluginPath( $pluginPath ) {
		$this->pluginPath = $pluginPath;
	}

	/**
	 *
	 */
	private function buildPluginUploadPath() {
		$uploadsDirectoryInfo = wp_upload_dir();
		if ( ! empty( $uploadsDirectoryInfo ) ) {
			$uploadsPath       = $uploadsDirectoryInfo['basedir'];
			$pluginUploadsPath = $uploadsPath . '/' . $this->getPluginTextDomain();
			if ( ! is_dir( $pluginUploadsPath ) ) {
				mkdir( $pluginUploadsPath );
			}
			if ( is_dir( $pluginUploadsPath ) ) {
				$this->setPluginUploadPath( $pluginUploadsPath );
				$this->setPluginUploadUrl( $uploadsDirectoryInfo['url'] . '/' . $this->getPluginTextDomain() );
			}
		}
	}

	/**
	 * @param string $pluginUploadPath
	 */
	private function setPluginUploadPath( $pluginUploadPath ) {
		$this->pluginUploadPath = $pluginUploadPath;
	}

	/**
	 * @param string $pluginUploadUrl
	 */
	private function setPluginUploadUrl( $pluginUploadUrl ) {
		$this->pluginUploadUrl = $pluginUploadUrl;
	}

	/**
	 * @param string $file
	 */
	private function setFile( $file ) {
		$this->file = $file;
	}

	/**
	 * @param array $options
	 */
	private function setOptions( $options ) {
		$this->options = $options;
	}

	/**
	 * @param array $pluginData
	 */
	private function setPluginData( $pluginData ) {
		$this->pluginData = $pluginData;
	}

	/**
	 * @param string $pluginName
	 */
	private function setPluginName( $pluginName ) {
		$this->pluginName = $pluginName;
	}

	/**
	 * @param string $pluginTextDomain
	 */
	private function setPluginTextDomain( $pluginTextDomain ) {
		$this->pluginTextDomain = $pluginTextDomain;
	}

	/**
	 * @param string $version
	 */
	private function setVersion( $version ) {
		$this->version = $version;
	}
}
