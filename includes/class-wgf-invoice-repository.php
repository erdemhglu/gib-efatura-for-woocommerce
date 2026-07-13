<?php
defined( 'ABSPATH' ) || exit;

/**
 * wgf_invoices tablosu üzerinde CRUD işlemleri.
 */
class WGF_Invoice_Repository {

	public const STATUS_DRAFT     = 'taslak';
	public const STATUS_SIGNED    = 'imzalandi';
	public const STATUS_ERROR     = 'hata';
	public const STATUS_DELETED   = 'silindi';

	/**
	 * Bir siparişte oluşturma/imzalama aşamasında olan (aktif) fatura var mı?
	 */
	public static function has_active_invoice( int $order_id ): bool {
		return (bool) self::find_active_by_order( $order_id );
	}

	/**
	 * Siparişin asıl satış faturasını döndürür (iade faturaları hariç).
	 */
	public static function find_active_by_order( int $order_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . WGF_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d AND belge_turu = 'satis' AND durum IN (%s, %s) ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id,
				self::STATUS_DRAFT,
				self::STATUS_SIGNED
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Belirtilen (asıl) faturaya karşı oluşturulmuş, silinmemiş iade faturalarını döndürür.
	 */
	public static function find_returns_by_source( int $source_invoice_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . WGF_TABLE;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE iade_kaynak_id = %d AND durum != %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_invoice_id,
				self::STATUS_DELETED
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * İmzalanmış bir fatura için GİB'e iptal başvurusu gönderildiğini kaydeder.
	 */
	public static function mark_cancellation_requested( int $id, string $explanation ): void {
		self::update( $id, [
			'iptal_talep_tarihi' => current_time( 'mysql' ),
			'iptal_aciklama'     => $explanation,
		] );
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . WGF_TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ?: null;
	}

	public static function find_by_uuid( string $uuid ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . WGF_TABLE;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uuid = %s", $uuid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ?: null;
	}

	public static function create( array $data ): int {
		global $wpdb;
		$table = $wpdb->prefix . WGF_TABLE;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array_merge(
				[
					'durum'      => self::STATUS_DRAFT,
					'belge_turu' => 'satis',
					'created_at' => $now,
					'updated_at' => $now,
					'created_by' => get_current_user_id(),
				],
				$data
			)
		);

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): void {
		global $wpdb;
		$table = $wpdb->prefix . WGF_TABLE;

		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->update( $table, $data, [ 'id' => $id ] );
	}

	/**
	 * Sayfalanmış fatura listesi (admin listeleme ekranı için).
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . WGF_TABLE;

		$defaults = [
			'durum'    => '',
			'mode'     => '',
			'search'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
		];
		$args = array_merge( $defaults, $args );

		$where  = [ '1=1' ];
		$params = [];

		if ( $args['durum'] ) {
			$where[]  = 'durum = %s';
			$params[] = $args['durum'];
		}
		if ( $args['mode'] ) {
			$where[]  = 'mode = %s';
			$params[] = $args['mode'];
		}
		if ( $args['search'] ) {
			$where[]  = '(alici_ad LIKE %s OR belge_no LIKE %s OR order_id LIKE %s OR alici_vkn_tckn LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$orderby = in_array( $args['orderby'], [ 'id', 'order_id', 'tutar', 'created_at' ], true ) ? $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( ! empty( $params ) ? $wpdb->prepare( $count_sql, array_slice( $params, 0, -2 ) ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'items' => $rows ?: [],
			'total' => $total,
		];
	}
}
