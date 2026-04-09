<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="rzpa-app" data-rzpa-page="rapport">

  <div class="rzpa-logo-bar">
    <img src="<?php echo esc_url( RZPA_URL . 'assets/Rezponz-logo.png' ); ?>" alt="Rezponz" />
    <span class="rzpa-logo-badge">PDF Rapport</span>
  </div>

  <div class="rzpa-header">
    <div>
      <h1>PDF Rapport Generator</h1>
      <p class="page-sub">Generer en komplet branded marketing rapport til ledelsen</p>
    </div>
    <div class="rzpa-header-right">
      <div id="rzpa-date-filter" class="rzpa-date-filter">
        <button data-days="7">7 dage</button>
        <button data-days="30" class="active">30 dage</button>
        <button data-days="90">90 dage</button>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

    <div class="rzpa-card">
      <h2>Generer rapport</h2>

      <div id="rzpa-rapport-notice" class="rzpa-notice" style="display:none"></div>

      <ul class="rzpa-checklist">
        <li>Forside med Rezponz branding og periode</li>
        <li>Executive Summary med nøgletal</li>
        <li>SEO – Top søgeord og positioner</li>
        <li>AI-synlighed – Google AI Overviews & Featured Snippets</li>
        <li>Meta Ads – Kampagneoversigt og ROAS</li>
        <li>Snapchat Ads – Swipe-ups og engagement</li>
        <li>TikTok Ads – Video performance og spend</li>
        <li>AI-genererede anbefalinger (kræver OpenAI API nøgle)</li>
        <li>Brandede Rezponz bagside</li>
      </ul>

      <div style="margin-top:20px">
        <button id="rzpa-gen-rapport" class="btn-primary" style="font-size:14px;padding:10px 20px">
          📥 Generer &amp; Åbn Rapport
        </button>
      </div>

      <p style="font-size:12px;color:#444;margin-top:12px">
        Rapporten åbnes i et nyt vindue. Brug <strong style="color:#777">Ctrl+P</strong> (Windows) eller
        <strong style="color:#777">Cmd+P</strong> (Mac) for at gemme som PDF.
      </p>
    </div>

    <div class="rzpa-card">
      <h2>API-nøgler status</h2>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
        <?php
        $opts   = get_option( 'rzpa_settings', [] );
        $checks = [
            'Google Search Console' => 'google_client_id',
            'SerpAPI (AI tracking)' => 'serp_api_key',
            'Meta Ads'              => 'meta_access_token',
            'Snapchat Ads'          => 'snap_access_token',
            'TikTok Ads'            => 'tiktok_access_token',
            'OpenAI (anbefalinger)' => 'openai_api_key',
        ];
        foreach ( $checks as $label => $key ) :
            $configured = ! empty( $opts[ $key ] );
        ?>
        <div style="display:flex;align-items:center;gap:10px;font-size:13px">
          <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $configured ? '#CCFF00' : '#555'; ?>;flex-shrink:0"></span>
          <span style="color:<?php echo $configured ? '#bbb' : '#555'; ?>"><?php echo esc_html( $label ); ?></span>
          <?php if ( ! $configured ) : ?>
          <span style="font-size:11px;color:#444">– ikke konfigureret</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <p style="font-size:12px;color:#444;margin-top:16px">
        Uden API-nøgler vises realistiske demo-data.
        Gå til <a href="<?php echo admin_url('admin.php?page=rzpa-settings'); ?>" style="color:var(--neon)">Indstillinger</a> for at tilføje nøgler.
      </p>
    </div>

  </div>

</div>
