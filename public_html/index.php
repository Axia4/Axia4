<?php require_once "_incl/pre-body.php"; ?>

<section class="hero">
    <h1>Bienvenidx a Axia4</h1>
    <p>La plataforma unificada de EuskadiTech y Sketaria.</p>
    <hr>
    <h3>Versión 2.0.0</h3>
    <p>Con esta versión, cambiamos la interfaz a una mas sencilla.</p>
    <a class="btn btn-primary" href="/account/">Accede a tu cuenta</a>
</section>

<div class="notice-card">
    <strong>Aviso: En mantenimiento</strong>
    <span>En los siguientes días vamos a cambiar la interfaz.</span>
</div>

<div id="grid" class="app-grid" style="display: none;">
    <div class="app-card">
        <img src="/static/logo-club.png" alt="Logo Club">
        <div class="app-title">La web del club</div>
        <div class="app-actions">
            <a href="/club/" class="btn btn-primary">Acceso público</a>
        </div>
    </div>
    <div class="app-card">
        <img src="/static/logo-entreaulas.png" alt="Logo EntreAulas">
        <div class="app-title">EntreAulas</div>
        <div class="app-desc">Gestión de aularios conectados.</div>
        <div class="app-actions">
            <?php if ($_SESSION["auth_ok"] && in_array('entreaulas:access', $_SESSION["auth_data"]["permissions"] ?? [])) { ?>
                <a href="/entreaulas/" class="btn btn-primary">Acceder</a>
            <?php } else { ?>
                <span class="btn btn-outline-secondary disabled">Sin permiso</span>
            <?php } ?>
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
    <div class="app-card is-disabled">
        <img src="/static/logo-oscar.png" alt="Logo OSCAR">
        <div class="app-title">OSCAR</div>
        <div class="app-desc">Red de IA Absoluta.</div>
        <div class="app-note">Próximamente</div>
    </div>
    <div class="app-card is-disabled">
        <img src="/static/logo-media.png" alt="Logo ET Media">
        <div class="app-title">ET Media</div>
        <div class="app-desc">Streaming de pelis y series.</div>
        <div class="app-note">Próximamente</div>
    </div>
    <div class="app-card is-disabled">
        <img src="/static/logo-hyper.png" alt="Logo Hyper">
        <div class="app-title">Hyper</div>
        <div class="app-desc">Plataforma de gestión empresarial.</div>
        <div class="app-note">Próximamente</div>
    </div>
    <div class="app-card is-disabled">
        <img src="/static/logo-mail.png" alt="Logo Comunicaciones">
        <div class="app-title">Comunicaciones</div>
        <div class="app-desc">Correos electrónicos y mensajería.</div>
        <div class="app-note">Próximamente</div>
    </div>
    <div class="app-card is-disabled">
        <img src="/static/logo-malla.png" alt="Logo Malla">
        <div class="app-title">Malla Meshtastic</div>
        <div class="app-desc">Red de comunicación por radio.</div>
        <div class="app-note">Próximamente</div>
    </div>
    <div class="app-card is-disabled">
        <img src="/static/logo-aularios.png" alt="Logo Aularios">
        <div class="app-title">Aularios<sup>2</sup></div>
        <div class="app-desc">Visita virtual a los aularios.</div>
        <div class="app-note">Solo lectura · Migrando a Axia4</div>
    </div>
    <div class="app-card is-disabled">
        <img src="/static/logo-nube.png" alt="Logo Axia4 Cloud">
        <div class="app-title">Nube Axia4.NET</div>
        <div class="app-desc">Almacenamiento central de datos.</div>
        <div class="app-note">Cerrado por migración</div>
    </div>
    <div class="app-card is-disabled">
        <img src="/static/logo-nk4.png" alt="Logo Nube Kasa">
        <div class="app-title">Nube Kasa</div>
        <div class="app-desc">Nube personal con domótica.</div>
        <div class="app-note">Cerrado por mantenimiento</div>
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
</div>

<style>
    body {
        background: #f5f5f5;
    }

    .hero {
        text-align: center;
        margin: 32px 0 16px;
        background: url(/static/portugalete.jpg) #ffffffc2;
        padding: 25px 7px;
        padding-top: 50px;
        height: 350px;
        border-radius: 50px;
        background-size: cover;
        background-position: center;
        background-blend-mode: lighten;
        color: black;
        /* -webkit-text-stroke: 0.5px #acacac; */
    }

    .hero h1 {
        font-size: 42px;
        margin-bottom: 8px;
        color: #000;
    }

    .hero p {
        color: #000;
    }

    .notice-card {
        background: #e8f0fe;
        padding: 12px 16px;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        color: #1a3c78;
        margin-bottom: 20px;
        outline: 1px solid #c2d1f0;
    }

    .app-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }

    .app-card {
        background: #fff;
        border-radius: 16px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }

    .app-card img {
        height: 64px;
        width: 64px;
    }

    .app-title {
        font-weight: 600;
        color: #202124;
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
    }

    .is-disabled {
        opacity: 0.6;
    }
    .app-card .btn.btn-outline-secondary.disabled {
        color: black;
    }
</style>

<?php require_once "_incl/post-body.php"; ?>