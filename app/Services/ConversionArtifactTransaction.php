<?php
/**
 * Stages converted artifacts until all requested outputs succeed.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * All-or-nothing file publish for image conversion outputs.
 *
 * Prior known-good destination files remain untouched until commit().
 *
 * @since 4.3.0
 */
final class ConversionArtifactTransaction {

	/**
	 * Map of staging path => final destination path.
	 *
	 * @since 4.3.0
	 * @var array<string, string>
	 */
	private $staged = [];

	/**
	 * Stage a final destination by allocating a sibling staging path.
	 *
	 * Callers write bytes to the returned staging path. Commit renames into place.
	 *
	 * @since 4.3.0
	 * @param string $final_path Absolute destination path for the converted file.
	 * @return string Absolute staging path to write into.
	 */
	public function stage( string $final_path ): string {
		$dir      = dirname( $final_path );
		$basename = basename( $final_path );
		$staging  = $dir . '/.flux-stage-' . $basename . '.' . uniqid( '', true ) . '.tmp';

		$this->staged[ $staging ] = $final_path;

		return $staging;
	}

	/**
	 * Register an already-written staging file for a final destination.
	 *
	 * Used when converters produce a temporary path that should publish atomically.
	 *
	 * @since 4.3.0
	 * @param string $staging_path Path that currently holds the new bytes.
	 * @param string $final_path   Destination path after commit.
	 * @return void
	 */
	public function register( string $staging_path, string $final_path ): void {
		$this->staged[ $staging_path ] = $final_path;
	}

	/**
	 * Publish every staged file to its final path.
	 *
	 * Validates that every staging file exists before publishing any destination so a
	 * missing stage cannot partially replace known-good outputs.
	 *
	 * @since 4.3.0
	 * @return bool True when all renames succeed.
	 */
	public function commit(): bool {
		foreach ( array_keys( $this->staged ) as $staging ) {
			if ( ! file_exists( $staging ) ) {
				$this->rollback();
				return false;
			}
		}

		foreach ( $this->staged as $staging => $final ) {
			$dir = dirname( $final );
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				$this->rollback();
				return false;
			}

			// Replace destination only after staging succeeded for the full set.
			if ( file_exists( $final ) && ! @unlink( $final ) ) {
				$this->rollback();
				return false;
			}

			if ( ! @rename( $staging, $final ) ) {
				// Attempt copy+unlink if rename crosses devices.
				if ( ! @copy( $staging, $final ) ) {
					$this->rollback();
					return false;
				}
				@unlink( $staging );
			}

			unset( $this->staged[ $staging ] );
		}

		$this->staged = [];
		return true;
	}

	/**
	 * Delete staged files without touching prior known-good destinations.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function rollback(): void {
		foreach ( array_keys( $this->staged ) as $staging ) {
			if ( file_exists( $staging ) ) {
				@unlink( $staging );
			}
		}

		$this->staged = [];
	}

	/**
	 * Whether any staged files remain.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function has_staged(): bool {
		return ! empty( $this->staged );
	}
}
