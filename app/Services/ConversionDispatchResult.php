<?php
/**
 * Explicit conversion dispatch outcome.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Result of ConversionOrchestrator::dispatch() for sync and async work.
 *
 * @since 4.3.0
 */
final class ConversionDispatchResult {

	/**
	 * Terminal local success.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const OUTCOME_COMPLETED = 'completed';

	/**
	 * Work accepted by cloud / external processor (await webhook).
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const OUTCOME_SUBMITTED = 'submitted';

	/**
	 * Local async work queued (e.g. video cron) — not terminal success.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const OUTCOME_DEFERRED = 'deferred';

	/**
	 * Terminal failure.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const OUTCOME_FAILED = 'failed';

	/**
	 * Intentionally skipped (disabled, unsupported, already in flight).
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const OUTCOME_SKIPPED = 'skipped';

	/**
	 * Outcome string.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $outcome;

	/**
	 * Human-readable message.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $message;

	/**
	 * Structured context.
	 *
	 * @since 4.3.0
	 * @var array<string, mixed>
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param string               $outcome Outcome constant.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	private function __construct( string $outcome, string $message = '', array $context = [] ) {
		$this->outcome = $outcome;
		$this->message = $message;
		$this->context = $context;
	}

	/**
	 * Completed successfully.
	 *
	 * @since 4.3.0
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return self
	 */
	public static function completed( string $message = '', array $context = [] ): self {
		return new self( self::OUTCOME_COMPLETED, $message, $context );
	}

	/**
	 * Submitted to external processor.
	 *
	 * @since 4.3.0
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return self
	 */
	public static function submitted( string $message = '', array $context = [] ): self {
		return new self( self::OUTCOME_SUBMITTED, $message, $context );
	}

	/**
	 * Deferred to local async worker.
	 *
	 * @since 4.3.0
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return self
	 */
	public static function deferred( string $message = '', array $context = [] ): self {
		return new self( self::OUTCOME_DEFERRED, $message, $context );
	}

	/**
	 * Failed.
	 *
	 * @since 4.3.0
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return self
	 */
	public static function failed( string $message = '', array $context = [] ): self {
		return new self( self::OUTCOME_FAILED, $message, $context );
	}

	/**
	 * Skipped.
	 *
	 * @since 4.3.0
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return self
	 */
	public static function skipped( string $message = '', array $context = [] ): self {
		return new self( self::OUTCOME_SKIPPED, $message, $context );
	}

	/**
	 * Outcome string.
	 *
	 * @since 4.3.0
	 * @return string
	 */
	public function get_outcome(): string {
		return $this->outcome;
	}

	/**
	 * Message.
	 *
	 * @since 4.3.0
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Context bag.
	 *
	 * @since 4.3.0
	 * @return array<string, mixed>
	 */
	public function get_context(): array {
		return $this->context;
	}

	/**
	 * Whether conversion finished successfully on this request.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function is_completed(): bool {
		return self::OUTCOME_COMPLETED === $this->outcome;
	}

	/**
	 * Whether work is still outstanding (submitted or deferred).
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function is_in_flight(): bool {
		return in_array(
			$this->outcome,
			[ self::OUTCOME_SUBMITTED, self::OUTCOME_DEFERRED ],
			true
		);
	}

	/**
	 * Whether conversion failed.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function is_failed(): bool {
		return self::OUTCOME_FAILED === $this->outcome;
	}

	/**
	 * Whether conversion was intentionally skipped.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function is_skipped(): bool {
		return self::OUTCOME_SKIPPED === $this->outcome;
	}
}
