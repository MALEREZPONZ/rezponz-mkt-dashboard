<?php if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

$page_url      = admin_url( 'admin.php?page=rzpa-seo-blog-templates' );
$filter_status = sanitize_key( $_GET['status'] ?? '' );

$templates = RZPA_SEO_DB::get_templates( 'blog', $filter_status ?: null );
?>
<div class="rzpa-wrap">

  <div class="rzpa-page-header">
    <div>
      <h1 class="rzpa-page-title">Blog Templates</h1>
      <p class="rzpa-page-sub">Skabeloner til AI-assisteret blogindhold</p>
    </div>
    <div>
      <a href="<?php echo esc_url( $page_url . '&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">➕ Nyt Blog Template</a>
    </div>
  </div>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Ændringer gemt.</p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['error'] ) ) : ?>
    <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
  <?php endif; ?>

  <!-- Filter Tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:12px;">
    <?php foreach ( [ '' => 'Alle', 'active' => 'Aktive', 'inactive' => 'Inaktive', 'draft' => 'Kladde' ] as $val => $label ) :
      $active = ( $filter_status === $val ) ? 'rzpa-btn-primary' : '';
    ?>
      <a href="<?php echo esc_url( add_query_arg( 'status', $val, $page_url ) ); ?>"
         class="rzpa-btn <?php echo esc_attr( $active ); ?>"><?php echo esc_html( $label ); ?></a>
    <?php endforeach; ?>
  </div>

  <div class="rzpa-card">
    <?php if ( empty( $templates ) ) : ?>
      <div class="rzpa-empty">
        <p>Ingen blog templates fundet.</p>
        <a href="<?php echo esc_url( $page_url . '&action=new' ); ?>" class="rzpa-btn rzpa-btn-primary">➕ Opret dit første blog template</a>
      </div>
    <?php else : ?>
      <table class="rzpa-table">
        <thead>
          <tr>
            <th>Navn</th>
            <th>Slug</th>
            <th>Status</th>
            <th>Version</th>
            <th>Opdateret</th>
            <th>Handlinger</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $templates as $tpl ) :
            $status_class = ( 'active' === $tpl['status'] ) ? 'badge-active' : 'badge-paused';
          ?>
            <tr>
              <td><strong><?php echo esc_html( $tpl['name'] ); ?></strong></td>
              <td><code style="font-size:12px;"><?php echo esc_html( $tpl['slug'] ); ?></code></td>
              <td><span class="badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $tpl['status'] ) ); ?></span></td>
              <td>v<?php echo esc_html( $tpl['version'] ); ?></td>
              <td style="color:var(--text-muted);font-size:12px;"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $tpl['updated_at'] ) ) ); ?></td>
              <td>
                <a href="<?php echo esc_url( $page_url . '&action=edit&id=' . absint( $tpl['id'] ) ); ?>"
                   class="rzpa-btn" style="font-size:12px;">Rediger</a>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                      style="display:inline;" onsubmit="return confirm('Slet dette template?');">
                  <?php wp_nonce_field( RZPA_SEO_Admin::NONCE_ACTION ); ?>
                  <input type="hidden" name="action" value="rzpa_seo_delete_template">
                  <input type="hidden" name="id" value="<?php echo absint( $tpl['id'] ); ?>">
                  <button type="submit" class="rzpa-btn" style="font-size:12px;color:var(--text-muted);">Slet</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
