<?php
/**
 * Zoro
 *
 * @wordpress-plugin
 * Plugin Name:         Zoro Lite
 * Description:         Access to design sets collections managed by the Asura plugin
 * Version:             1.0.0
 * Author:              thelostasura
 * Author URI:          https://thelostasura.com/
 * Requires at least:   5.5
 * Tested up to:		5.5.1
 * Requires PHP:        7.3
 * 
 * @package             Zoro Lite
 * @author              thelostasura
 * @link                https://thelostasura.com/
 * @since               1.0.0
 * @copyright           2020 thelostasura
 * 
 * Romans 12:12 (ASV)  
 * rejoicing in hope; patient in tribulation; continuing stedfastly in prayer;
 * 
 * Roma 12:12 (TB)  
 * Bersukacitalah dalam pengharapan, sabarlah dalam kesesakan, dan bertekunlah dalam doa! 
 * 
 * https://alkitab.app/v/f27a6d7e714e
 */

defined( 'ABSPATH' ) || exit;

define( 'ZL_VERSION', '1.0.0' );
define( 'ZL_PLUGIN_FILE', __FILE__ );
define( 'ZL_PLUGIN_DIR', __DIR__ );
define( 'ZL_PLUGIN_URL', plugins_url( '', __FILE__ ) . '/' );

require_once ZL_PLUGIN_DIR . '/vendor/autoload.php';

use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Activation & Deactivation Hook
|--------------------------------------------------------------------------
*/

function activate_zl() {
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $zl_providers = "CREATE TABLE {$wpdb->prefix}zl_providers (
        id BIGINT NOT NULL AUTO_INCREMENT,
        uid VARCHAR(255) NOT NULL,
        provider VARCHAR(255) NOT NULL,
        namespace VARCHAR(255) NOT NULL,
        version VARCHAR(255) NOT NULL,
        api_key VARCHAR(255) NOT NULL,
        api_secret VARCHAR(255) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";
    dbDelta( $zl_providers );

    $zl_licenses = "CREATE TABLE {$wpdb->prefix}zl_licenses (
        id BIGINT NOT NULL AUTO_INCREMENT,
        uid VARCHAR(255) NOT NULL,
        provider VARCHAR(255) NOT NULL,
        license VARCHAR(255) NOT NULL,
        hash VARCHAR(255) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";
    dbDelta( $zl_licenses );

}
register_activation_hook( __FILE__, 'activate_zl' );


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

class ZLNotice 
{
	protected $types = [
        'error',
        'success',
        'warning',
        'info',
	];

	public function __construct() {}

	public function init() {
		foreach ( $this->types as $type ) {
			$messages = get_transient( 'zl_notice_' . $type );

			if ( $messages && is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					echo sprintf(
						'<div class="notice notice-%s is-dismissible"><p><b>Zoro Lite</b>: %s</p></div>',
						$type,
						$message
					);
				}

				delete_transient( 'zl_notice_' . $type );
			}
		}
	}

	public static function add( $level, $message, $code = 0, $duration = 60 ) {
		$messages = get_transient( 'zl_notice_' . $level );

		if ( $messages && is_array( $messages ) ) {
			if (!in_array($message, $messages)) {
				$messages[] = $message;
			}
		} else {
			$messages = [ $message ];
		}

		set_transient( 'zl_notice_' . $level, $messages, $duration );
	}

	public static function error( $message ) {
		self::add( 'error', $message );
	}

	public static function success( $message ) {
		self::add( 'success', $message );
	}

	public static function warning( $message ) {
		self::add( 'warning', $message );
	}

	public static function info( $message ) {
		self::add( 'info', $message );
	}
}
add_action('admin_notices', function() {
    $notices = new ZLNotice();
    $notices->init();
});


function zl_redirect_js($location)
{
    echo "<script>
    window.location.href='{$location}';
    </script>";
}

/*
|--------------------------------------------------------------------------
| Cache
|--------------------------------------------------------------------------
*/

// Create a new Container object, needed by the cache manager.
$container = new Container;

// The CacheManager creates the cache "repository" based on config values
// which are loaded from the config class in the container.
// More about the config class can be found in the config component; for now we will use an array
$container['config'] = [
    'cache.default' => 'file',
    'cache.stores.file' => [
        'driver' => 'file',
        'path' => __DIR__ . '/storage/framework/cache/data'
    ]
];


// To use the file cache driver we need an instance of Illuminate's Filesystem, also stored in the container
$container['files'] = new Filesystem;

// Create the CacheManager
$cacheManager = new CacheManager($container);

// Get the default cache driver (file in this case)
$cache = $cacheManager->store();

// Or, if you have multiple drivers:
// $cache = $cacheManager->store('file');


/*
|--------------------------------------------------------------------------
| Providers & License
|--------------------------------------------------------------------------
*/

global $ct_source_sites;

$zlSite = [
    'tla_abcde_abcde_abcdefghijklmnopq_melati' => [
        'label' => 'Melati [de.test]', 
        'url' => 'https://thelostasura.com', 
        'accesskey' =>  '', 
        'system' => true
    ],
];


$ct_source_sites = array_merge($zlSite, $ct_source_sites);

/*
|--------------------------------------------------------------------------
| Asura API
|--------------------------------------------------------------------------
*/


function zl_get_items_from_source() {
    
    $name = isset( $_REQUEST['name'] ) ? sanitize_text_field( $_REQUEST['name'] ) : false;

    if ( Str::startsWith( $name, 'tla_' ) ) {
        return ct_get_items_from_source();
    }
    $name = Str::replaceFirst( 'tla_', '', $name );

    $zl_pair = Str::of( $name )->explode( '_', 3 );

    list($provider, $license, $slug) = $zl_pair;

    dd($provider, $license, $slug);
    
}


/*
|--------------------------------------------------------------------------
| Ajax
|--------------------------------------------------------------------------
*/

function zl_new_style_api_call() {
    $call_type = isset( $_REQUEST['call_type'] ) ? sanitize_text_field( $_REQUEST['call_type'] ) : false;
	
	ct_new_style_api_call_security_check( $call_type );

	switch( $call_type ) {
		case 'setup_default_data':
			ct_setup_default_data();
		break;
		case 'get_component_from_source':
			ct_get_component_from_source();
		break;
		case 'get_page_from_source':
			ct_get_page_from_source();
		break;
		case 'get_items_from_source':
			zl_get_items_from_source();
		break;
		case 'get_stuff_from_source':
			ct_get_stuff_from_source();
		break;
	}

	die();
}

remove_action( 'wp_ajax_ct_new_style_api_call', 'ct_new_style_api_call' );
add_action( 'wp_ajax_ct_new_style_api_call', 'zl_new_style_api_call' );


/*
|--------------------------------------------------------------------------
| AdminMenu
|--------------------------------------------------------------------------
*/

function zl_providers_page()
{
    add_submenu_page(
        'ct_dashboard_page',
        'Zoro Lite Providers',
        'Zoro Lite Providers',
        'manage_options',
        'zl',
        'zl_providers_page_callback'
    );

    add_submenu_page(
        null, 
        'Add Providers', 
        'Add Providers', 
        'manage_options', 
        'add_zl_providers', 
        'add_zl_providers_callback'
    );

    add_submenu_page(
        null,
        'License',
        'License',
        'manage_options',
        'zl_licenses',
        'zl_licenses_page_callback'
    );
}
add_action('admin_menu', 'zl_providers_page');

function zl_licenses_page_callback()
{
    ?>
    
    <h2>Zoro Lite</h2>

    <h3>License — </h3>
    
    <?php
}

function zl_providers_page_callback()
{
    global $wpdb;

    $providers = $wpdb->get_results("
        SELECT *
        FROM {$wpdb->prefix}zl_providers
    ");

    ?>


    <h2>Zoro Lite</h2>

    <h3>Providers</h3>

    <table class="wp-list-table widefat fixed striped table-view-list tags">
        <thead>
            <tr>
                <th scope="col" id="provider" class="manage-column column-provider">
                    <span>Provider</span>
                </th>
                <th scope="col" id="namespace" class="manage-column column-namespace">
                    <span>Namespace</span>
                </th>
                <th scope="col" id="version" class="manage-column column-version">
                    <span>Version</span>
                </th>
                <th scope="col" id="apikey" class="manage-column column-apikey">
                    <span>API Key</span>
                </th>
                <th scope="col" id="apisecret" class="manage-column column-apisecret">
                    <span>API Secret</span>
                </th>
            </tr>
        </thead>
        <tbody id="the-list">
        <?php foreach ($providers as $key => $provider): ?>
            <tr class="level-0">
            
                <td class="name column-name has-row-actions column-primary" data-colname="Name">
                    <strong>
                        <a class="row-title" title="<?php echo $provider->uid; ?>"> <?php echo $provider->provider; ?>  </a>
                    </strong>
                    <br>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="">Edit</a> | 
                        </span>
                        <span class="delete">
                            <a href="<?php
                                echo add_query_arg([
                                    'page' => 'zl_providers',
                                    'license' => $provider->id,
                                    'action' => 'revoke',
                                    'wp_none' => wp_create_nonce( 'zl_revoke_provider' )
                                ], get_admin_url().'admin.php');
                            ?>" class="delete-tag">Delete</a> | 
                        </span>
                        <span class="view">
                            <a href="http://user.test/tag/bcd/">License Keys</a>
                        </span>
                    </div>
                </td>
                <td class="slug column-namespace"><?php echo $provider->namespace; ?></td>
                <td class="slug column-version"><?php echo $provider->version; ?></td>
                <td class="slug column-apikey"><?php echo $provider->api_key; ?></td>
                <td class="slug column-apisecret"><?php echo $provider->api_secret; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        
    </table>

    <a href="<?php echo add_query_arg('page', 'add_zl_providers', get_admin_url().'admin.php');?>">+ Add Providers</a>
    
    <?php
}




function add_zl_providers_callback() {

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_REQUEST['add_zl_providers'] ) ) {
        
        if( ! base64_decode( $_REQUEST['zl_provider_string'] ) ) {
            ZLNotice::error( 'Provider String should be base64 encoded string.' );
            zl_redirect_js( add_query_arg( 'page', 'add_zl_providers', get_admin_url().'admin.php' ) );
            exit;
        }

        if ( ! is_scalar( $_REQUEST['zl_provider_string'] ) && ! method_exists( $_REQUEST['zl_provider_string'], '__toString' ) ) {
            ZLNotice::error( 'Zoro String should be json string. [1]' );
            zl_redirect_js( add_query_arg( 'page', 'add_zl_providers', get_admin_url().'admin.php' ) );
            exit;
        }

        $provider = json_decode( base64_decode( $_REQUEST['zl_provider_string'] ) );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            ZLNotice::error( 'Zoro String should be json string. [2]' );
            zl_redirect_js( add_query_arg( 'page', 'add_zl_providers', get_admin_url().'admin.php' ) );
            exit;
        }

        if ( ! is_object( $provider )
            || ! isset( $provider->api_key )
            || ! isset( $provider->api_secret )
            || ! isset( $provider->provider )
            || ! isset( $provider->namespace )
            || ! isset( $provider->version )
        ) {
            ZLNotice::error( 'Zoro String doesn\'t contain connection config' );
            zl_redirect_js( add_query_arg( 'page', 'add_zl_providers', get_admin_url().'admin.php' ) );
            exit;
        }

        global $wpdb;

        $insert = $wpdb->insert("{$wpdb->prefix}zl_providers", [
            'uid' => Str::random(5),
            'api_key' => $provider->api_key,
            'api_secret' => $provider->api_secret,
            'provider' => $provider->provider,
            'namespace' => $provider->namespace,
            'version' => $provider->version,
        ]);


        if (!$insert) {
            ZLNotice::error( 'Failed to add the provider to database' );
            zl_redirect_js( add_query_arg( 'page', 'add_zl_providers', get_admin_url().'admin.php' ) );
            exit;
        }


        ZLNotice::success( 'Success added the provider to database' );

        zl_redirect_js( add_query_arg( 'page', 'zl', get_admin_url().'admin.php' ) );
        exit;

        // return $wpdb->insert_id;

    }


	?>
	<h2>Add Provider</h2>
	<form method="post" action="?page=add_zl_providers">
		<div>
			<?php wp_nonce_field(-1, 'add_zl_providers');?>
			<label for="zl_provider_string">Zoro String</label>
			<input type="text" value="" name="zl_provider_string" id="zl_provider_string" />
		</div>
		<?php submit_button('Add Provider'); ?>
	</form>
	<?php
}