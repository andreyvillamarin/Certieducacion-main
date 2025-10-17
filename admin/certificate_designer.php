<?php
$page_title = 'Editar Plantilla de Certificado';
include 'includes/header.php';

$template_json = file_exists('certificate_template.json') ? file_get_contents('certificate_template.json') : '{}';
$default_template_json = file_get_contents('certificate_template.json');
?>

<h1 class="mt-4">Editar Plantilla de Certificado</h1>
<p>Haz clic en un elemento para moverlo o editar su estilo.</p>

<div id="designer-data" 
     data-template='<?php echo htmlspecialchars($template_json, ENT_QUOTES, 'UTF-8'); ?>'
     data-default-template='<?php echo htmlspecialchars($default_template_json, ENT_QUOTES, 'UTF-8'); ?>'
     data-base-path="../"
     data-signature-path="assets/img/signatures/director.png?t=<?php echo time(); ?>">
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <h5>Controles Generales</h5>
                <hr>
                <div class="mb-3">
                    <label for="background-uploader" class="form-label">Cambiar Fondo</label>
                    <input type="file" id="background-uploader" class="form-control" accept="image/*">
                    <div class="form-text">medida del fondo: 4679 x 3308 px</div>
                </div>
                <div class="mb-3"><label for="signature-uploader" class="form-label">Cambiar Firma</label><input type="file" id="signature-uploader" class="form-control" accept="image/png"></div>
                <hr>
                <button id="save-template" class="btn btn-primary w-100">Guardar Cambios</button>
                <button id="reset-template" class="btn btn-outline-danger w-100 mt-2">Restaurar Original</button>
                
                <div id="object-controls" class="mt-4" style="display: none;">
                    <hr>
                    <h5>Alineación de Objeto</h5>
                    <div class="d-grid gap-2">
                        <button id="object-align-center-h" class="btn btn-outline-primary btn-sm">Centrar Horizontalmente</button>
                        <button id="object-align-center-v" class="btn btn-outline-primary btn-sm">Centrar Verticalmente</button>
                    </div>
                </div>

                <div id="text-controls" class="mt-4" style="display: none;">
                    <hr>
                    <h5>Estilos de Texto</h5>
                    <div class="mb-2"><label for="font-family" class="form-label">Fuente</label><select id="font-family" class="form-select form-select-sm"><option>Arial</option><option>Helvetica</option><option>Times New Roman</option><option>Courier</option><option>Verdana</option></select></div>
                    <div class="mb-2"><label for="font-size" class="form-label">Tamaño (pt)</label><input type="number" id="font-size" class="form-control form-control-sm" min="1"></div>
                    <div class="mb-2"><label for="font-color" class="form-label">Color</label><input type="color" id="font-color" class="form-control form-control-color w-100"></div>
                    <div class="d-flex justify-content-start gap-2 flex-wrap">
                        <button id="font-bold" class="btn btn-outline-secondary btn-sm"><i class="fas fa-bold"></i></button>
                        <button id="font-italic" class="btn btn-outline-secondary btn-sm"><i class="fas fa-italic"></i></button>
                        <button id="font-underline" class="btn btn-outline-secondary btn-sm"><i class="fas fa-underline"></i></button>
                        <button id="font-uppercase" class="btn btn-outline-secondary btn-sm" title="Convertir a Mayúsculas">Aa</button>
                        <div class="vr"></div>
                        <button id="align-left" class="btn btn-outline-secondary btn-sm"><i class="fas fa-align-left"></i></button>
                        <button id="align-center" class="btn btn-outline-secondary btn-sm"><i class="fas fa-align-center"></i></button>
                        <button id="align-right" class="btn btn-outline-secondary btn-sm"><i class="fas fa-align-right"></i></button>
                        <button id="align-justify" class="btn btn-outline-secondary btn-sm"><i class="fas fa-align-justify"></i></button>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div id="canvas-container" style="border: 1px solid #ccc; position: relative; width: 100%; height: 0; padding-bottom: 70.66%;"><canvas id="certificate-canvas"></canvas></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="js/certificate_designer.js" defer></script>