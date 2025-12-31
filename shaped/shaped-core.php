<?php
/**
 * Shaped Core - MPHB Customizations
 *
 * Replaces MPHB's datepicker with Litepicker for better UX.
 *
 * @package ShapedCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shaped Core Main Class
 */
class ShapedCore {

    /**
     * Litepicker CDN URL (using jsDelivr for reliability)
     */
    const LITEPICKER_JS_URL = 'https://cdn.jsdelivr.net/npm/litepicker@2.0.12/dist/litepicker.js';

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Plugin directory path
     */
    private $plugin_path;

    /**
     * Plugin directory URL
     */
    private $plugin_url;

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->plugin_path = plugin_dir_path( dirname( __FILE__ ) ) . 'shaped/';
        $this->plugin_url  = plugin_dir_url( dirname( __FILE__ ) ) . 'shaped/';

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Dequeue MPHB datepicker scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_mphb_datepicker' ), 100 );

        // Enqueue Litepicker and adapter
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_litepicker' ), 101 );

        // Add body class for styling hooks
        add_filter( 'body_class', array( $this, 'add_body_class' ) );
    }

    /**
     * Dequeue MPHB's datepicker scripts and styles
     */
    public function dequeue_mphb_datepicker() {
        // We don't want to completely remove the datepicker since MPHB's JS
        // still references it for date parsing. Instead, we'll let our adapter
        // take over the UI while keeping the jQuery.datepick library for utilities.

        // Dequeue the datepicker theme CSS (we'll use our own styling)
        wp_dequeue_style( 'mphb-kbwood-datepick-theme' );
        wp_deregister_style( 'mphb-kbwood-datepick-theme' );
    }

    /**
     * Enqueue Litepicker and adapter scripts
     */
    public function enqueue_litepicker() {
        // Only enqueue on frontend, not admin
        if ( is_admin() ) {
            return;
        }

        // Register Litepicker from CDN
        wp_register_script(
            'shaped-litepicker',
            self::LITEPICKER_JS_URL,
            array(),
            '2.0.12',
            true
        );

        // Register our adapter
        wp_register_script(
            'shaped-litepicker-adapter',
            $this->plugin_url . 'assets/js/litepicker-adapter.js',
            array( 'shaped-litepicker', 'mphb' ),
            '1.0.0',
            true
        );

        // Register our styles
        wp_register_style(
            'shaped-litepicker-styles',
            $this->plugin_url . 'assets/css/litepicker.css',
            array( 'mphb' ),
            '1.0.0'
        );

        // Enqueue
        wp_enqueue_script( 'shaped-litepicker' );
        wp_enqueue_script( 'shaped-litepicker-adapter' );
        wp_enqueue_style( 'shaped-litepicker-styles' );
    }

    /**
     * Add body class for styling hooks
     */
    public function add_body_class( $classes ) {
        $classes[] = 'shaped-litepicker-active';
        return $classes;
    }
}

// Initialize
ShapedCore::get_instance();
