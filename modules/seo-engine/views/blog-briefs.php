<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$page_url      = admin_url( 'admin.php?page=rzpa-seo-briefs' );
$filter_status = sanitize_key( $_GET['status'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 25;
$offset        = ( $paged - 1 ) * $per_page;

$total  = 0;
$briefs = RZPA_SEO_DB::get_briefs( $filter_status ?: null, $per_page, $offset, $total );

$status_labels = [
    'draft'     => [ 'Kladde',     'badge-paused' ],
    'review'    => [ 'Review',     '' ],
    'approved'  => [ 'Godkendt',   'badge-active' ],
    'generated' => [ 'Genereret',  'badge-blue' ],
    'published' => [ 'Publiceret', 'badge-active' ],
];
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">📝 Blog Briefs</h1>
      <p class="rzpa-page-sub"><?php echo esc_html( $total ); ?> briefs i alt</p>
    </div>
    <div>
      <a href="<?php echo esc_url( $page_url . '&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">➕ Nyt Brief</a>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Brief gemt.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <!-- Filter Tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:12px;">
    <?php foreach ( array_merge( [ '' => 'Alle' ], array_combine( array_keys( $status_labels ), array_column( $status_labels, 0 ) ) ) as $val => $label ) :
      $active = ( $filter_status === $val ) ? 'rzpa-btn-primary' : '';
    ?>
      <a href="<?php echo esc_url( add_query_arg( 'status', $val, $page_url ) ); ?>"
         class="rzpa-btn <?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
    <?php endforeach; ?>
  </div>

  <div class="rzpa-card">
    <?php if ( empty( $briefs ) ) : ?>
      <div class="rzpa-empty">
        <p>Ingen blog briefs fundet.</p>
        <a href="<?php echo esc_url( $page_url . '&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">➕ Opret nyt brief</a>
      </div>
    <?php else : ?>
      <table class="rzpa-table">
        <thead>
          <tr>
            <th>Primært Søgeord</th>
            <th>Intent</th>
            <th>Målgruppe</th>
            <th>Tone</th>
            <th>Type</th>
            <th>Status</th>
            <th>Indlæg</th>
            <th>Opdateret</th>
            <th>Handlinger</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $briefs as $brief ) :
            [ $s_lbl, $s_cls ] = $status_labels[ $brief['status'] ] ?? [ $brief['status'], '' ];
            $post_link = '';
            if ( ! empty( $brief['linked_post_id'] ) ) {
                $post_link = '<a href="' . esc_url( get_edit_post_link( $brief['linked_post_id'] ) ) . '" target="_blank" style="color:var(--neon);" title="Rediger indlæg">↗</a>';
            }
          ?>
            <tr>
              <td><strong><?php echo esc_html( $brief['primary_keyword'] ); ?></strong></td>
              <td style="font-size:12px;"><?php echo esc_html( $brief['intent'] ); ?></td>
              <td style="font-size:12px;"><?php echo esc_html( $brief['audience'] ); ?></td>
              <td style="font-size:12px;"><?php echo esc_html( $brief['tone_of_voice'] ); ?></td>
              <td style="font-size:12px;"><?php echo esc_html( $brief['article_type'] ); ?></td>
              <td><span class="badge <?php echo esc_attr( $s_cls ); ?>"><?php echo esc_html( $s_lbl ); ?></span></td>
              <td><?php echo wp_kses_post( $post_link ); ?></td>
              <td style="color:var(--text-muted);font-size:11px;"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $brief['updated_at'] ) ) ); ?></td>
              <td>
                <a href="<?php echo esc_url( $page_url . '&action=edit&id=' . absint( $brief['id'] ) ); ?>"
                   class="rzpa-btn" style="font-size:11px;">Rediger</a>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                  <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
                  <input type="hidden" name="action" value="rzpa_seo_generate_blog">
                  <input type="hidden" name="brief_id" value="<?php echo absint( $brief['id'] ); ?>">
                  <?php if ( RZPA_SEO_AI::is_configured() ) : ?>
                    <input type="hidden" name="use_ai" value="1">
                  <?php endif; ?>
                  <button type="submit" class="rzpa-btn" style="font-size:11px;">⚡ Generér</button>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                      style="display:inline;" onsubmit="return confirm('Slet dette brief?');">
                  <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
                  <input type="hidden" name="action" value="rzpa_seo_delete_brief">
                  <input type="hidden" name="id" value="<?php echo absint( $brief['id'] ); ?>">
                  <button type="submit" class="rzpa-btn" style="font-size:11px;color:var(--text-muted);">Slet</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ( $total > $per_page ) :
        $pages = ceil( $total / $per_page );
      ?>
        <div style="padding:12px 16px;display:flex;gap:6px;border-top:1px solid var(--border);">
          <?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $p, $page_url ) ); ?>"
               class="rzpa-btn<?php echo $p === $paged ? ' rzpa-btn-primary' : ''; ?>"
               style="font-size:12px;padding:4px 10px;"><?php echo $p; ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>
