<?php require_once "_incl/pre-body.php"; ?>

<style>
    .hero {
        text-align: center;
        margin: 0 0 28px;
        background: linear-gradient(135deg, #e8f0fe 0%, #fce8ff 100%);
        padding: 48px 24px;
        border-radius: 12px;
        color: var(--gw-text-primary, #202124);
        border: 1px solid #dadce0;
    }

    .hero h1 {
        font-size: 36px;
        font-weight: 400;
        margin-bottom: 8px;
        color: #202124;
        letter-spacing: -0.01em;
    }

    .hero p {
        color: #5f6368;
        font-size: 16px;
        margin-bottom: 0;
    }

    .hero hr {
        border-color: #dadce0;
        margin: 20px auto;
        max-width: 200px;
    }

    .hero h3 {
        font-size: 14px;
        font-weight: 500;
        color: #1a73e8;
        letter-spacing: 0.02em;
        margin-bottom: 4px;
    }

    .hero .btn {
        border-radius: 20px;
        padding: 8px 24px;
    }

    .section-title {
        font-size: 14px;
        font-weight: 500;
        color: #5f6368;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin: 0 0 16px;
    }

    .app-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .app-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 20px 16px 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        border: 1px solid #dadce0;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .app-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #bdc1c6;
    }

    .app-card img {
        height: 48px;
        width: 48px;
        object-fit: contain;
    }

    .app-title {
        font-weight: 500;
        font-size: 15px;
        color: #202124;
        margin-top: 4px;
    }

    .app-desc,
    .app-note {
        color: #5f6368;
        font-size: 13px;
    }

    .app-actions {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding-top: 8px;
    }

    .app-actions .btn {
        border-radius: 4px;
        font-size: 13px;
    }

    .is-disabled {
        opacity: 0.55;
    }

    .app-card .btn.btn-outline-secondary.disabled {
        color: #5f6368;
    }
</style>

<section class="hero">
    <h1>Bienvenidx a Axia4</h1>
    <p>La plataforma unificada de EuskadiTech y Sketaria.</p>
    <hr>
    <h3>Versión 2.1.0</h3>
    <p>Con esta versión, hacemos muchos cambios.</p>
    <br>
    <a class="btn btn-primary" href="/account/">Accede a tu cuenta</a>
</section>

<p class="section-title">Aplicaciones</p>

<div id="grid" class="app-grid">
    <div class="app-card">
        <img src="/static/logo-club.png" alt="Logo Club">
        <div class="app-title">La web del club</div>
        <div class="app-actions">
            <a href="/club/" class="btn btn-primary">Acceso público</a>
        </div>
    </div>
    <div class="app-card">
        <img src="/static/logo-telesec.png" alt="Logo TeleSec">
        <div class="app-title">TeleSec</div>
        <div class="app-desc">Gestión de aularios conectados.</div>
        <div class="app-actions">
            <a href="https://telesec.tech.eus/" target="_blank" class="btn btn-primary">Tengo cuenta</a>
        </div>
    </div>
    <div class="app-card">
        <img src="/static/logo-account.png" alt="Logo Account">
        <div class="app-title">Mi Cuenta</div>
        <div class="app-desc">Acceso a la plataforma y pagos.</div>
        <div class="app-actions">
            <?php if ($_SESSION["auth_ok"]) { ?>
                <a href="/account/" class="btn btn-primary">Ir a mi cuenta</a>
                <a href="/_login.php?logout=1&redir=/" class="btn btn-outline-secondary">Cerrar sesión</a>
            <?php } else { ?>
                <a href="/_login.php?redir=/" class="btn btn-primary">Iniciar sesión</a>
                <a href="/account/register.php" class="btn btn-outline-primary">Crear cuenta</a>
            <?php } ?>
        </div>
    </div>
    <div class="app-card">
        <img src="/static/logo-aulatek.png" alt="Logo AulaTek">
        <div class="app-title">AulaTek</div>
        <div class="app-desc">Tu aula, digital.</div>
        <div class="app-actions">
            <a href="/aulatek/" target="_blank" class="btn btn-primary">Acceso público</a>
        </div>
    </div>
    <div class="app-card">
        <img src="/static/logo-arroz.png" alt="Logo Arroz con leche">
        <div class="app-title">Arroz con leche</div>
        <div class="app-desc">Compartiendo nuestros conocimientos.</div>
        <div class="app-actions">
            <a href="https://arroz.tech.eus/" target="_blank" class="btn btn-primary">Acceso público</a>
        </div>
    </div>
    <div class="app-card">
        <img src="/static/logo-sysadmin.png" alt="Logo SysAdmin">
        <div class="app-title">SysAdmin</div>
        <div class="app-desc">Configuración de Axia4.</div>
        <div class="app-actions">
            <?php if (in_array('sysadmin:access', $_SESSION["auth_data"]["permissions"] ?? [])) { ?>
                <a href="/sysadmin/" class="btn btn-primary">Acceder</a>
            <?php } else { ?>
                <span class="btn btn-outline-secondary disabled">Sin permiso</span>
            <?php } ?>
        </div>
    </div>
</div>

<?php require_once "_incl/post-body.php"; ?>