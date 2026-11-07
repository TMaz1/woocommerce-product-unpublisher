<?php
/**
 * Composition root: builds the object graph and registers hooks.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher;

use ProductUnpublisher\Admin\Assets;
use ProductUnpublisher\Admin\BulkEditFields;
use ProductUnpublisher\Admin\BulkEditHandler;
use ProductUnpublisher\Admin\BulkNotice;
use ProductUnpublisher\Admin\BulkResultStore;
use ProductUnpublisher\Admin\DateDisplay;
use ProductUnpublisher\Admin\DateParts;
use ProductUnpublisher\Admin\EditorRow;
use ProductUnpublisher\Admin\EditorSaveHandler;
use ProductUnpublisher\Admin\FailureAlerts;
use ProductUnpublisher\Admin\ListColumn;
use ProductUnpublisher\Admin\ProductNoticeStore;
use ProductUnpublisher\Admin\ProductNotices;
use ProductUnpublisher\Cron\WpCronGateway;
use ProductUnpublisher\Diagnostics\OptionFailureLog;
use ProductUnpublisher\Product\ProductUnpublisher;
use ProductUnpublisher\Product\WpProductGateway;
use ProductUnpublisher\Schedule\Reconciler;
use ProductUnpublisher\Schedule\RetryPolicy;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Schedule\StatusListener;
use ProductUnpublisher\Schedule\UnpublishRunner;
use ProductUnpublisher\Storage\PostMetaScheduleStore;
use ProductUnpublisher\Time\LocalTimeCodec;
use ProductUnpublisher\Time\ScheduleDateParser;
use ProductUnpublisher\Time\SiteClock;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	private SiteClock $clock;
	private PostMetaScheduleStore $store;
	private WpCronGateway $cron;
	private OptionFailureLog $failures;
	private ScheduleManager $schedules;
	private WpProductGateway $products;
	private ProductUnpublisher $unpublisher;
	private ScheduleDateParser $parser;
	private RetryPolicy $retry;

	private function __construct() {
		$this->clock       = new SiteClock();
		$this->store       = new PostMetaScheduleStore( new LocalTimeCodec( $this->clock ) );
		$this->cron        = new WpCronGateway();
		$this->failures    = new OptionFailureLog();
		$this->schedules   = new ScheduleManager( $this->store, $this->cron, $this->clock, $this->failures );
		$this->products    = new WpProductGateway();
		$this->unpublisher = new ProductUnpublisher( $this->products, $this->schedules, $this->failures );
		$this->parser      = new ScheduleDateParser( $this->clock );
		$this->retry       = new RetryPolicy();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot only when WooCommerce is present.
	 */
	public static function boot_if_ready(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( self::class, 'missing_woocommerce_notice' ) );
			return;
		}

		self::instance()->boot();
	}

	public static function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'WooCommerce Product Unpublisher requires WooCommerce to be installed and active.', 'woocommerce-product-unpublisher' )
		);
	}

	/**
	 * Activation: re-register cron events for schedules saved before deactivation.
	 */
	public static function activate(): void {
		self::instance()->reconcile();
	}

	/**
	 * Deactivation: remove cron events. Schedule metadata is kept so activation can restore them.
	 */
	public static function deactivate(): void {
		wp_unschedule_hook( Config::CRON_HOOK );
	}

	public function schedules(): ScheduleManager {
		return $this->schedules;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// Behaviour that must run in every context (cron, admin, WP-CLI).
		( new UnpublishRunner( $this->schedules, $this->store, $this->products, $this->unpublisher, $this->failures, $this->retry, $this->clock ) )->register();
		( new StatusListener( $this->schedules, $this->failures ) )->register();

		// Request handlers (guarded by nonces and capability checks).
		$notices      = new ProductNoticeStore();
		$display      = new DateDisplay( $this->clock );
		$bulk_results = new BulkResultStore();

		( new EditorSaveHandler( $this->schedules, $this->products, $this->parser, $notices, $display ) )->register();
		( new BulkEditHandler( $this->schedules, $this->products, $this->unpublisher, $this->parser, $bulk_results ) )->register();

		add_action( 'admin_init', array( $this, 'maybe_reconcile' ) );

		if ( ! is_admin() ) {
			return;
		}

		$parts = new DateParts( $this->clock );

		( new Assets( $this->clock ) )->register();
		( new EditorRow( $this->schedules, $parts, $display, $this->clock ) )->register();
		( new BulkEditFields( $parts, $this->clock ) )->register();
		( new ListColumn( $this->schedules, $this->products, $this->retry, $display, $this->clock ) )->register();
		( new ProductNotices( $notices ) )->register();
		( new BulkNotice( $bulk_results ) )->register();
		( new FailureAlerts( $this->failures ) )->register();
	}

	/**
	 * Reconcile once after the plugin version changes.
	 */
	public function maybe_reconcile(): void {
		if ( WPU_VERSION === get_option( Config::DB_VERSION_OPTION ) ) {
			return;
		}

		$this->reconcile();
	}

	/**
	 * Re-register missing events and drop stale metadata.
	 *
	 * @return array{registered:int,removed:int,failed:int}
	 */
	public function reconcile(): array {
		$summary = ( new Reconciler( $this->schedules, $this->store, $this->clock ) )->reconcile();

		update_option( Config::DB_VERSION_OPTION, WPU_VERSION, false );

		return $summary;
	}
}
