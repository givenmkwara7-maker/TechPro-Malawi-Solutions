<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function techmalawi_setup() {
  add_theme_support( 'title-tag' );
  add_theme_support( 'post-thumbnails' );
  add_theme_support( 'woocommerce' );
  add_theme_support( 'wc-product-gallery-lightbox' );
  add_theme_support( 'wc-product-gallery-slider' );
  register_nav_menus( array( 'primary' => __( 'Primary Navigation', 'techmalawi' ) ) );
}
add_action( 'after_setup_theme', 'techmalawi_setup' );

function techmalawi_assets() {
  wp_enqueue_style( 'techmalawi-style', get_stylesheet_uri(), array(), '1.0.0' );
  wp_enqueue_script( 'techmalawi', get_template_directory_uri() . '/assets/site.js', array(), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'techmalawi_assets' );

function techmalawi_product_categories() { return array( 'smartphones' => array('📱','Smartphones'), 'laptops' => array('💻','Laptops'), 'audio' => array('🎧','Audio'), 'accessories' => array('🔌','Accessories') ); }

function techmalawi_nav_fallback() { foreach ( techmalawi_product_categories() as $slug => $cat ) echo '<a href="' . esc_url( get_term_link( $slug, 'product_cat' ) ) . '">' . esc_html( $cat[1] ) . '</a>'; }

// Keep carts available for guests and return correct fragments after add-to-cart.
add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) { ob_start(); ?><span class="cart-count"><?php echo WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?></span><?php $fragments['span.cart-count'] = ob_get_clean(); return $fragments; } );

// WhatsApp purchase request, with product and local currency price pre-filled.
add_action( 'woocommerce_after_add_to_cart_button', function() {
  global $product; if ( ! $product ) return;
  $message = sprintf( 'Hi, I am interested in %s listed at %s. Is it available?', $product->get_name(), wp_strip_all_tags( $product->get_price_html() ) );
  $number = preg_replace( '/\D/', '', get_option( 'techmalawi_whatsapp', '265990000000' ) );
  echo '<a class="button whatsapp-buy" target="_blank" rel="noopener" href="https://wa.me/' . esc_attr( $number ) . '?text=' . rawurlencode( $message ) . '">Chat to Buy</a>';
} );

function techmalawi_currency( $currencies ) { $currencies['MWK'] = __( 'Malawian Kwacha (MWK)', 'techmalawi' ); return $currencies; }
add_filter( 'woocommerce_currencies', 'techmalawi_currency' );
add_filter( 'woocommerce_currency_symbol', function( $symbol, $currency ) { return $currency === 'MWK' ? 'MWK ' : $symbol; }, 10, 2 );

// Lilongwe-only COD safeguard.
add_filter( 'woocommerce_available_payment_gateways', function( $gateways ) { if ( is_admin() || ! isset( $gateways['cod'] ) ) return $gateways; $city = WC()->customer ? WC()->customer->get_shipping_city() : ''; if ( $city && stripos( $city, 'lilongwe' ) === false ) unset( $gateways['cod'] ); return $gateways; } );
add_action( 'woocommerce_after_checkout_billing_form', function() { echo '<p class="tm-shipping-note">Cash on Delivery is available in Lilongwe only. Delivery pricing is confirmed by region at checkout.</p>'; } );

// Localized bank-transfer instructions.
add_filter( 'woocommerce_bacs_accounts', function( $accounts ) { if ( empty( $accounts ) ) $accounts[] = array( 'account_name' => 'TechMalawi Solutions', 'account_number' => 'Your account number', 'bank_name' => 'National Bank of Malawi', 'sort_code' => '' ); return $accounts; } );

/** Offline Mobile Money gateways: customers submit their number, then staff verify payment. */
add_filter( 'woocommerce_payment_gateways', function( $gateways ) { $gateways[] = 'TechMalawi_Mobile_Money_Gateway'; return $gateways; } );
if ( class_exists( 'WC_Payment_Gateway' ) ) {
  class TechMalawi_Mobile_Money_Gateway extends WC_Payment_Gateway {
    public function __construct() {
      $this->id = 'techmalawi_mobile_money';
      $this->method_title = 'Mobile Money';
      $this->method_description = 'Airtel Money and TNM Mpamba payment verification.';
      $this->has_fields = true;
      $this->supports = array( 'products' );
      $this->init_form_fields(); $this->init_settings();
      $this->title = $this->get_option( 'title', 'Airtel Money / TNM Mpamba' );
      $this->description = $this->get_option( 'description', 'Pay securely with Airtel Money or TNM Mpamba. We will confirm your payment before dispatch.' );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
    public function init_form_fields() { $this->form_fields = array(
      'enabled' => array( 'title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable Mobile Money', 'default' => 'yes' ),
      'title' => array( 'title' => 'Title', 'type' => 'text', 'default' => 'Airtel Money / TNM Mpamba' ),
      'description' => array( 'title' => 'Customer message', 'type' => 'textarea', 'default' => 'Pay securely with Airtel Money or TNM Mpamba. We will confirm your payment before dispatch.' ),
    ); }
    public function payment_fields() {
      if ( $this->description ) echo wpautop( wp_kses_post( $this->description ) );
      echo '<p><label for="tm_mobile_network">Network</label><select name="tm_mobile_network" id="tm_mobile_network"><option>Airtel Money</option><option>TNM Mpamba</option></select></p>';
      echo '<p><label for="tm_mobile_number">Mobile Money number</label><input required name="tm_mobile_number" id="tm_mobile_number" type="tel" placeholder="+265 99..." /></p>';
    }
    public function validate_fields() { if ( empty( $_POST['tm_mobile_number'] ) ) { wc_add_notice( 'Please enter your Mobile Money number.', 'error' ); return false; } return true; }
    public function process_payment( $order_id ) {
      $order = wc_get_order( $order_id );
      $network = isset( $_POST['tm_mobile_network'] ) ? sanitize_text_field( wp_unslash( $_POST['tm_mobile_network'] ) ) : '';
      $number = isset( $_POST['tm_mobile_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tm_mobile_number'] ) ) : '';
      $order->update_meta_data( '_tm_mobile_network', $network ); $order->update_meta_data( '_tm_mobile_number', $number );
      $order->update_status( 'on-hold', 'Awaiting ' . $network . ' payment verification.' ); $order->reduce_order_stock(); $order->save(); WC()->cart->empty_cart();
      return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
    }
  }
}

/** Region-based shipping: set this method in any Malawi shipping zone. */
add_filter( 'woocommerce_shipping_methods', function( $methods ) { $methods['techmalawi_regional'] = 'TechMalawi_Regional_Shipping'; return $methods; } );
add_action( 'woocommerce_shipping_init', function() {
  if ( class_exists( 'TechMalawi_Regional_Shipping' ) ) return;
  class TechMalawi_Regional_Shipping extends WC_Shipping_Method {
    public function __construct( $instance_id = 0 ) { $this->id = 'techmalawi_regional'; $this->instance_id = absint( $instance_id ); $this->method_title = 'TechMalawi Regional Delivery'; $this->method_description = 'Automatically calculates delivery fee by destination city.'; $this->supports = array( 'shipping-zones', 'instance-settings' ); $this->init(); }
    public function init() { $this->init_form_fields(); $this->init_settings(); $this->title = $this->get_option( 'title', 'TechMalawi delivery' ); add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) ); }
    public function init_form_fields() { $this->instance_form_fields = array( 'title' => array( 'title' => 'Method title', 'type' => 'text', 'default' => 'TechMalawi delivery' ) ); }
    public function calculate_shipping( $package = array() ) { $city = strtolower( $package['destination']['city'] ?? '' ); $cost = strpos( $city, 'lilongwe' ) !== false ? 2000 : ( strpos( $city, 'blantyre' ) !== false || strpos( $city, 'mzuzu' ) !== false ? 5000 : 7500 ); $this->add_rate( array( 'id' => $this->get_rate_id(), 'label' => $this->title, 'cost' => $cost, 'package' => $package ) ); }
  }
} );

// Compact product REST fields for mobile autocomplete / integrations.
add_action( 'rest_api_init', function() { register_rest_field( 'product', 'techmalawi_stock_status', array( 'get_callback' => function( $object ) { $product = wc_get_product( $object['id'] ); return $product ? $product->get_stock_status() : ''; }, 'schema' => array( 'description' => 'Stock state', 'type' => 'string' ) ) ); } );
