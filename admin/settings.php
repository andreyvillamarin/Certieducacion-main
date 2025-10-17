<?php
// admin/settings.php
$page_title = 'Configuración';
include 'includes/header.php';

// DOBLE guardián de seguridad: solo superadmin puede ver esta página.
if ($admin_role !== 'superadmin') {
    echo '<div class="alert alert-danger">Acceso denegado. Esta sección es solo para superadministradores.</div>';
    include 'includes/footer.php';
    exit;
}

// Las constantes ya están cargadas desde config.php, podemos usarlas directamente.

$notification = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}
?>

<h1 class="mt-4">Configuración del Sistema</h1>
<p>Administra las claves de API y otras configuraciones globales de la aplicación.</p>

<!-- Notificaciones -->
<?php if ($notification): ?>
<div class="alert alert-<?php echo htmlspecialchars($notification['type']); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($notification['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Claves y Endpoints</h5>
    </div>
    <div class="card-body">
        <form action="settings_handler.php" method="POST">
            <h6 class="text-primary">Configuración de Soporte</h6>
            <div class="mb-3">
                <label for="whatsapp_number" class="form-label">Número de WhatsApp para Soporte</label>
                <input type="text" class="form-control" id="whatsapp_number" name="WHATSAPP_SUPPORT_NUMBER" value="<?php echo htmlspecialchars(WHATSAPP_SUPPORT_NUMBER); ?>">
                <small class="form-text text-muted">Formato: 573001234567</small>
            </div>
            <hr>
            
            <h6 class="text-primary">API de Correo (Brevo SMTP)</h6>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="brevo_host" class="form-label">Host SMTP</label><input type="text" class="form-control" id="brevo_host" name="BREVO_SMTP_HOST" value="<?php echo htmlspecialchars(BREVO_SMTP_HOST); ?>"></div>
                <div class="col-md-6 mb-3"><label for="brevo_port" class="form-label">Puerto SMTP</label><input type="number" class="form-control" id="brevo_port" name="BREVO_SMTP_PORT" value="<?php echo htmlspecialchars(BREVO_SMTP_PORT); ?>"></div>
                <div class="col-md-6 mb-3"><label for="brevo_user" class="form-label">Usuario SMTP (tu email de login en Brevo)</label><input type="text" class="form-control" id="brevo_user" name="BREVO_SMTP_USER" value="<?php echo htmlspecialchars(BREVO_SMTP_USER); ?>"></div>
                <div class="col-md-6 mb-3"><label for="brevo_key" class="form-label">Clave SMTP (de Brevo)</label><input type="password" class="form-control" id="brevo_key" name="BREVO_SMTP_KEY" placeholder="Introduce una nueva clave para actualizarla"></div>
            </div>
             <div class="row">
                <div class="col-md-6 mb-3"><label for="smtp_from_email" class="form-label">Email Remitente (verificado en Brevo)</label><input type="email" class="form-control" id="smtp_from_email" name="SMTP_FROM_EMAIL" value="<?php echo htmlspecialchars(SMTP_FROM_EMAIL); ?>"></div>
                 <div class="col-md-6 mb-3"><label for="smtp_from_name" class="form-label">Nombre Remitente</label><input type="text" class="form-control" id="smtp_from_name" name="SMTP_FROM_NAME" value="<?php echo htmlspecialchars(SMTP_FROM_NAME); ?>"></div>
            </div>
            <hr>

            <h6 class="text-primary">API de SMS (Altiria)</h6>
             <div class="row">
                <div class="col-md-6 mb-3"><label for="altiria_login" class="form-label">Login de Altiria</label><input type="text" class="form-control" id="altiria_login" name="ALTIRIA_LOGIN" value="<?php echo htmlspecialchars(ALTIRIA_LOGIN); ?>"></div>
                <div class="col-md-6 mb-3"><label for="altiria_password" class="form-label">Contraseña de Altiria</label><input type="password" class="form-control" id="altiria_password" name="ALTIRIA_PASSWORD" placeholder="Introduce una nueva clave para actualizarla"></div>
                 <div class="col-md-12 mb-3"><label for="altiria_sender" class="form-label">Sender ID de Altiria (opcional)</label><input type="text" class="form-control" id="altiria_sender" name="ALTIRIA_SENDER_ID" value="<?php echo htmlspecialchars(ALTIRIA_SENDER_ID); ?>"></div>
            </div>

            <button type="submit" class="btn btn-primary">Guardar Configuración</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
