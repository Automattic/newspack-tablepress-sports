<?php
/**
 * Plugin Name:       Newspack TablePress Sports
 * Description:       Manage sports scores using TablePress.
 * Version:           1.0.0-beta.1
 * Author:            Automattic
 * Author URI:        https://automattic.com
 * Text Domain:       newspack-tablepress-sports
 * Domain Path:       /languages
 */

/*
 @todo:
  - Authentication
  - Cancel button
  - Design?
  - Refactor code to be nicer.
*/

/**
 * This class manages everything.
 */
class Newspack_TablePress_Sports {

	const TABLE_ID_OPTION = 'newspack_tablepress_sports_table_id';

	/**
	 * Initialize.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'save_settings_page' ] );
		add_action( 'admin_notices', [ __CLASS__, 'not_set_up_notice' ] );
		add_shortcode( 'newspack_sports_scores', [ __CLASS__, 'render_shortcode' ] );
		add_action( 'wp', [ __CLASS__, 'process_form' ] );
		add_filter( 'tablepress_cell_content', [ __CLASS__, 'add_report_score' ], 10, 4 );
	}

	/**
	 * Register the settings page.
	 */
	public static function register_settings_page() {
		add_options_page(
			__( 'Newspack TablePress Sports', 'newspack-tablepress-sports' ),
			__( 'Newspack TablePress Sports', 'newspack-tablepress-sports' ),
			'manage_options',
			'newspack-tablepress-sports',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function save_settings_page() {
		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) !== 'newspack-tablepress-sports' || ! current_user_can( 'manage_options' ) || ! class_exists( 'TablePress' ) ) {
			return;
		}

		// Check nonce.
		$table = TablePress::$model_table;
		$args = $table->get_table_template();
		$args['name'] = __( 'Sports Scores', 'newspack-tablepress-sports' );
		$args['visibility'] = [
			'rows' => [ 1 ],
			'columns' => [ 1, 1, 1, 1, 1 ],
		];
		$args['data'] = [
			[ 'Date', 'Sport', 'Home Team', 'Away Team', 'Score' ],
		];

		if ( filter_input( INPUT_POST, 'init_tablepress_sports', FILTER_SANITIZE_NUMBER_INT ) ) {
			$table_id = $table->add( $args );
			if ( is_wp_error( $table_id ) ) {
				add_action( 'admin_notices', function() {
					?>
					<div class='notice notice-error'>
						<p>
							<?php echo esc_html( sprintf( __( 'Error initializing: %s', 'newspack-tablepress-sports' ), $table_id->get_error_message() ) ); ?>
						</p>
					</div>
					<?php
				} );
			} else {
				update_option( self::TABLE_ID_OPTION, absint( $table_id ) );
				add_action( 'admin_notices', function() {
					?>
					<div class='notice notice-success'>
						<p>
							<?php esc_html_e( 'Successfully initialized.', 'newspack-tablepress-sports' ); ?>
						</p>
					</div>
					<?php
				} );
			}
		} elseif ( filter_input( INPUT_POST, 'reset_tablepress_sports', FILTER_SANITIZE_NUMBER_INT ) ) {
			$args['id'] = absint( get_option( self::TABLE_ID_OPTION ) );
			$table_id = $table->save( $args );
			if ( is_wp_error( $table_id ) ) {
				add_action( 'admin_notices', function() {
					?>
					<div class='notice notice-error'>
						<p>
							<?php echo esc_html( sprintf( __( 'Error reseting table: %s', 'newspack-tablepress-sports' ), $table_id->get_error_message() ) ); ?>
						</p>
					</div>
					<?php
				} );
			} else {
				update_option( self::TABLE_ID_OPTION, absint( $table_id ) );
				add_action( 'admin_notices', function() {
					?>
					<div class='notice notice-success'>
						<p>
							<?php esc_html_e( 'Successfully reset table.', 'newspack-tablepress-sports' ); ?>
						</p>
					</div>
					<?php
				} );
			}
		}
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page() {
		$existing = get_option( self::TABLE_ID_OPTION );

		?>
		<h1><?php esc_html_e( 'Newspack TablePress Sports', 'newspack-tablepress-sports' ); ?></h1>

		<?php if ( is_plugin_active( 'tablepress/tablepress.php' ) ) : ?>
			<form method='post' action='options-general.php?page=newspack-tablepress-sports'>
				<table class='form-table'>
					<tr valign='top'>
						<th scope='row'>
							<?php esc_html_e( 'Initialize', 'newspack-nrh-checkout' ); ?>
						</th>
						<td>
							<?php if ( $existing ) : ?>
								<input type="hidden" name="reset_tablepress_sports" value="0" />
								<input type="checkbox" name="reset_tablepress_sports" value="1" />
								<?php esc_html_e( 'Reset and clear sports table', 'newspack-tablepress-sports' ); ?>
							<?php else : ?>
								<input type="hidden" name="init_tablepress_sports" value="0" />
								<input type="checkbox" name="init_tablepress_sports" value="1" />
								<?php esc_html_e( 'Initialize sports table', 'newspack-tablepress-sports' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		<?php else : ?>
			<h2><?php esc_html_e( 'TablePress is not installed and active. Please install and/or activate it to use Newspack TablePress Sports', 'newspack-tablepress-sports' ); ?></h2>
		<?php endif; ?>

		<?php
	}

	/**
	 * Output an admin notice if the plugin is not set up correctly.
	 */
	public static function not_set_up_notice() {
		if ( empty( get_option( self::TABLE_ID_OPTION, '' ) ) && filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) !== 'newspack-tablepress-sports' ) {
			?>
			<div class='notice notice-error'>
				<p>
					<?php esc_html_e( 'You have not set up Newspack TablePress Sports.', 'newspack-tablepress-sports' ); ?>
					<a href='<?php menu_page_url( "newspack-tablepress-sports" ); ?>'><?php esc_html_e( 'Set up.', 'newspack-tablepress-sports' ); ?></a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Update or add a score.
	 *
	 * @param array $args See $defaults.
	 * @return bool True on success. False on failure.
	 */
	public static function update_score( $args ) {
		if ( ! class_exists( 'TablePress' ) ) {
			return false;
		}

		$defaults = [
			'row'        => -1,
			'date'       => '',
			'sport'      => '',
			'home'       => '',
			'away'       => '',
			'home_score' => '',
			'away_score' => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$table_id = absint( get_option( self::TABLE_ID_OPTION ) );
		if ( ! $table_id || ! class_exists( 'TablePress' ) ) {
			return false;
		}

		$table = TablePress::$model_table;
		$table_data = $table->load( $table_id );
		if ( is_wp_error( $table_data ) ) {
			return;
		}

		// Columns: 'Date', 'Sport', 'Home Team', 'Away Team', 'Score'.
		$row_data = [
			sanitize_text_field( $args['date'] ),
			sanitize_text_field( $args['sport'] ),
			sanitize_text_field( $args['home'] ),
			sanitize_text_field( $args['away'] ),
			sanitize_text_field( sprintf( '%s - %s', $args['home_score'], $args['away_score'] ) ),
		];

		if ( -1 !== $args['row'] ) {
			$table_data['data'][ absint( $args['row'] ) ][4] = $row_data[4]; // Just update the score for existing rows.
		} else {
			$table_data['data'][] = $row_data;
			$table_data['visibility']['rows'][] = 1;
		}

		$result = $table->save( $table_data );
		return is_wp_error( $result ) ? false : true;
	}

	/**
	 * Render the form.
	 */
	public static function render_form() {
		$table_id = absint( get_option( self::TABLE_ID_OPTION ) );
		if ( ! $table_id || ! class_exists( 'TablePress' ) ) {
			return;
		}

		$table = TablePress::$model_table;
		$table_data = $table->load( $table_id );
		if ( is_wp_error( $table_data ) ) {
			return;
		}

		?>
		<form method='POST' action='<?php the_permalink(); ?>'>
			<input type='hidden' name='newspack_tablepress_sports_row' class='newspack-tablepress-sports-row' value='-1' />
			<div class='newspack-tablepress-sports-datetime'>
				<label>
					<?php esc_html_e( 'Date', 'newspack-tablepress-sports' ); ?>
					<input name='newspack_tablepress_sports_date' class='newspack-tablepress-sports-date' type='date' value='<?php echo date( 'Y-m-d' ); ?>' required />
				</label>
				<label>
					<?php esc_html_e( 'Start time', 'newspack-tablepress-sports' ); ?>
					<input name='newspack_tablepress_sports_time' class='newspack-tablepress-sports-time' type='time' value='<?php echo date( 'H:i' ); ?>' required />
				</label>
			</div>
			<div class='newspack-tablepress-sports-sport-container'>
				<label>
					<?php esc_html_e( 'Sport', 'newspack-tablepress-sports' ); ?>
					<input name='newspack_tablepress_sports_sport' class='newspack-tablepress-sports-sport' type='text' required />
				</label>
			</div>
			<div class='newspack-tablepress-sports-teams'>
				<label>
					<?php esc_html_e( 'Home team', 'newspack-tablepress-sports' ); ?>
					<input name='newspack_tablepress_sports_home' class='newspack-tablepress-sports-home' type='text' required />
				</label>
				<label>
					<?php esc_html_e( 'Away team', 'newspack-tablepress-sports' ); ?>
					<input name='newspack_tablepress_sports_away' class='newspack-tablepress-sports-away' type='text' required />
				</label>
			</div>
			<div class='newspack-tablepress-sports-scores'>
				<label>
					<?php esc_html_e( 'Home score', 'newspack-tablepress-sports' ); ?>
					<input name='newspack_tablepress_sports_home_score' class='newspack-tablepress-sports-score' type='number' />
				</label>
				<label>
					<?php esc_html_e( 'Away score', 'newspack-tablepress-sports' ); ?>
					<input name='newspack_tablepress_sports_away_score' class='newspack-tablepress-sports-score' type='number' />
				</label>
			</div>
			<button value='1' name='newspack_tablepress_sports_submit' class='newspack-tablepress-sports-submit' type="submit">Submit</button>
		</form>
		<?php
	}

	/**
	 * Process the form.
	 */
	public static function process_form() {
		if ( ! filter_input( INPUT_POST, 'newspack_tablepress_sports_submit', FILTER_SANITIZE_NUMBER_INT ) ) {
			return;
		}

		$row        = filter_input( INPUT_POST, 'newspack_tablepress_sports_row', FILTER_SANITIZE_NUMBER_INT );
		$date       = filter_input( INPUT_POST, 'newspack_tablepress_sports_date', FILTER_SANITIZE_STRING );
		$time       = filter_input( INPUT_POST, 'newspack_tablepress_sports_time', FILTER_SANITIZE_STRING );
		$sport      = filter_input( INPUT_POST, 'newspack_tablepress_sports_sport', FILTER_SANITIZE_STRING );
		$home_team  = filter_input( INPUT_POST, 'newspack_tablepress_sports_home', FILTER_SANITIZE_STRING );
		$away_team  = filter_input( INPUT_POST, 'newspack_tablepress_sports_away', FILTER_SANITIZE_STRING );
		$home_score = filter_input( INPUT_POST, 'newspack_tablepress_sports_home_score', FILTER_SANITIZE_STRING );
		$away_score = filter_input( INPUT_POST, 'newspack_tablepress_sports_away_score', FILTER_SANITIZE_STRING );

		$args = [
			'date'       => sanitize_text_field( $date . ' ' . $time ),
			'sport'      => sanitize_text_field( $sport ),
			'home'       => sanitize_text_field( $home_team ),
			'away'       => sanitize_text_field( $away_team ),
			'home_score' => '',
			'away_score' => '',
		];

		if ( '' !== $row && -1 !== (int) $row ) {
			$args['row'] = absint( $row );
		}

		if ( '' !== $home_score ) {
			$args['home_score'] = absint( $home_score );
		}

		if ( '' !== $away_score ) {
			$args['away_score'] = absint( $away_score );
		}

		$result = self::update_score( $args );
		wp_safe_redirect( get_permalink() . '?sports_score_added=' . ( $result ? 'success' : 'failure' ) );
		exit;
	}

	/**
	 * Add link for reporting a score when no score is reported for a game.
	 *
	 * @param string $cell_content The current cell content.
	 * @param int $table_id The ID of the table.
	 * @param int $row_idx The current row.
	 * @param int $col_idx The current column.
	 * @return string Modified $cell_content.
	 */
	public static function add_report_score( $cell_content, $table_id, $row_idx, $col_idx ) {
		if ( absint( get_option( self::TABLE_ID_OPTION ) ) !== absint( $table_id ) ) {
			return $cell_content;
		}

		if ( '-' === $cell_content && 5 === $col_idx ) {
			return sprintf( "<a href='#' class='newspack_tablepress_sports_add_score' data-row='%d' onclick='event.preventDefault(); newspack_tablepress_add_score( this )'>%s</a>", $row_idx - 2, esc_html__( 'Submit score', 'newspack-tablepress-sports' ) );
		}
		return $cell_content;
	}

	/**
	 * Render the newspack_tablepress_sports shortcode.
	 *
	 * @return string HTML output.
	 */
	public static function render_shortcode() {
		$table_id = get_option( self::TABLE_ID_OPTION );
		if ( ! $table_id || ! class_exists( 'TablePress' ) ) {
			return '';
		}

		ob_start();
		?>
		<style>
			.newspack-tablepress-sports-success {
				background: #b2ffb2;
				line-height: 3em;
				padding-left: 1em;
			}

			.newspack-tablepress-sports-form-container {
				background: #f7f7f7;
				padding: 1em;
			}

			.newspack-tablepress-sports-form-container h3 {
				margin-bottom: 1em;
			}

			.newspack-tablepress-sports-form-container form,
			.newspack-tablepress-sports-add-score {
				font-size: 14px;
			}

			.newspack-tablepress-sports-form-container label {
				margin-right: 1em;
			}

			.newspack-tablepress-sports-datetime,
			.newspack-tablepress-sports-teams,
			.newspack-tablepress-sports-scores,
			.newspack-tablepress-sports-sport-container {
				margin-bottom: 1em;
			}

			.newspack-tablepress-sports-form-container input:disabled {
				opacity: 0.5;
			}

			.newspack-tablepress-sports-form-container.mobile label {
				display: block;
				margin-bottom: 1em;
			}
		</style>

		<?php if ( 'success' === filter_input( INPUT_GET, 'sports_score_added', FILTER_SANITIZE_STRING ) ) : ?>
			<div class='newspack-tablepress-sports-success'>
				<?php esc_html_e( 'Success!', 'newspack-tablepress-sports' ); ?>
			</div>
		<?php endif; ?>

		<?php echo do_shortcode( '[table id=' . absint( $table_id ) . ' /]' ); ?>

		<button class='newspack-tablepress-sports-add-score'>
			<?php esc_html_e( 'Add a game', 'newspack-tablepress-sports' ); ?>
		</button>

		<div class='newspack-tablepress-sports-form-container'>
			<h3><?php esc_html_e( 'Add game info', 'newspack-tablepress-sports' ); ?></h3>
			<?php echo self::render_form(); ?>
		</div>

		<script>
			( function() {
				const sport_form           = document.querySelector( '.newspack-tablepress-sports-form-container' );
				const add_score            = document.querySelector( '.newspack-tablepress-sports-add-score' );
				const date_field           = document.querySelector( '.newspack-tablepress-sports-date' );
				const score_fields         = document.querySelector( '.newspack-tablepress-sports-scores' );
				sport_form.style.display   = 'none';

				add_score.addEventListener( 'click', function(){
					sport_form.style.display = 'block';
					add_score.style.display  = 'none';

					if ( sport_form.clientWidth < 650 ) {
						sport_form.classList.add( 'mobile' );
					}
				} );

				date_field.addEventListener( 'change', function( e ) {
					let now = Date.now();
					let event_date = Date.parse( e.target.value );
					if ( now > event_date ) {
						score_fields.style.display = 'block';
					} else {
						score_fields.style.display = 'none';
					}
				} );

				/**
				 * Since we care about the parent container, not necessarily the window width, handle mobile with JS.
				 */
				window.addEventListener( 'resize', function() {
					if ( sport_form.clientWidth < 650 ) {
						sport_form.classList.add( 'mobile' );
					} else {
						sport_form.classList.remove( 'mobile' );
					}
				} );
			} )( );

			function newspack_tablepress_add_score( el ) {
				const row         = jQuery( el ).data( 'row' );
				const table       = jQuery( el ).parents( 'table' ).dataTable().api();
				const row_info    = table.row( row ).data();
				const date_time = row_info[0].split( ' ' );
				const game_date   = date_time.length > 1 ? date_time[0] : date_time;
				const game_time   = date_time.length > 1 ? date_time[1] : '';
				const game_sport  = row_info[1];
				const home_team   = row_info[2];
				const away_team   = row_info[3];

				const sport_form   = jQuery( '.newspack-tablepress-sports-form-container' );
				const add_score_el = jQuery( '.newspack-tablepress-sports-add-score' );
				add_score_el.trigger( 'click' );

				const date_el = sport_form.find( '.newspack-tablepress-sports-date' );
				date_el.val( game_date );
				date_el.prop( 'disabled', true );

				const time_el = sport_form.find( '.newspack-tablepress-sports-time' );
				time_el.val( game_time );
				time_el.prop( 'disabled', true );

				const sport_el = sport_form.find( '.newspack-tablepress-sports-sport' );
				sport_el.val( game_sport );
				sport_el.prop( 'disabled', true );

				const home_el = sport_form.find( '.newspack-tablepress-sports-home' );
				home_el.val( home_team );
				home_el.prop( 'disabled', true );

				const away_el = sport_form.find( '.newspack-tablepress-sports-away' );
				away_el.val( away_team );
				away_el.prop( 'disabled', true );

				const row_el = sport_form.find( '.newspack-tablepress-sports-row' );
				row_el.val( row + 1 );
			}
		</script>
		<?php

		return ob_get_clean();
	}
}
Newspack_TablePress_Sports::init();
