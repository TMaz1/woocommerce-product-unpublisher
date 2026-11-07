<?php

namespace ProductUnpublisher\Tests\Integration;

use ProductUnpublisher\Admin\BulkEditHandler;
use ProductUnpublisher\Admin\BulkResultStore;
use ProductUnpublisher\Admin\DateDisplay;
use ProductUnpublisher\Admin\EditorSaveHandler;
use ProductUnpublisher\Admin\ProductNoticeStore;
use ProductUnpublisher\Cron\CronGateway;
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

/**
 * Builds the real object graph for a test, optionally with a replaced cron gateway.
 */
final class Services {

	public SiteClock $clock;
	public PostMetaScheduleStore $store;
	public CronGateway $cron;
	public OptionFailureLog $failures;
	public ScheduleManager $schedules;
	public WpProductGateway $products;
	public ProductUnpublisher $unpublisher;
	public ScheduleDateParser $parser;
	public ProductNoticeStore $notices;
	public BulkResultStore $bulk_store;

	public function __construct( ?CronGateway $cron = null ) {
		$this->clock       = new SiteClock();
		$this->store       = new PostMetaScheduleStore( new LocalTimeCodec( $this->clock ) );
		$this->cron        = $cron ?? new WpCronGateway();
		$this->failures    = new OptionFailureLog();
		$this->schedules   = new ScheduleManager( $this->store, $this->cron, $this->clock, $this->failures );
		$this->products    = new WpProductGateway();
		$this->unpublisher = new ProductUnpublisher( $this->products, $this->schedules, $this->failures );
		$this->parser      = new ScheduleDateParser( $this->clock );
		$this->notices     = new ProductNoticeStore();
		$this->bulk_store  = new BulkResultStore();
	}

	public function runner(): UnpublishRunner {
		return new UnpublishRunner( $this->schedules, $this->store, $this->products, $this->unpublisher, $this->failures, new RetryPolicy(), $this->clock );
	}

	public function status_listener(): StatusListener {
		return new StatusListener( $this->schedules, $this->failures );
	}

	public function reconciler(): Reconciler {
		return new Reconciler( $this->schedules, $this->store, $this->clock );
	}

	public function bulk_handler(): BulkEditHandler {
		return new BulkEditHandler( $this->schedules, $this->products, $this->unpublisher, $this->parser, $this->bulk_store );
	}

	public function editor_handler(): EditorSaveHandler {
		return new EditorSaveHandler( $this->schedules, $this->products, $this->parser, $this->notices, new DateDisplay( $this->clock ) );
	}
}
