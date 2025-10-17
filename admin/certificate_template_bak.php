<?php
// admin/certificate_template.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Asistencia</title>
    <style>
        body { 
            font-family: 'times', serif; /* TCPDF usará su fuente por defecto si 'times' no está disponible/embebida */
            margin: 0; 
            padding: 0; 
            color: #000; 
            /* background-color: transparent; Para asegurar que no oculte el fondo del PDF */
        }
        /* El .certificate-container ahora ocupará el espacio que TCPDF le dé DESPUÉS de los márgenes del PDF */
        .certificate-container { 
            /* No establezcas width/height fijos aquí si quieres que se adapte a los márgenes del PDF */
            /* El padding interno del contenido se puede manejar aquí o con los márgenes del PDF */
            /* padding: 10mm 15mm; */ /* Ejemplo si quieres padding adicional DENTRO del área de márgenes del PDF */
            position: relative; /* Para el posicionamiento absoluto del footer-table */
            box-sizing: border-box;
            width: 100%; /* Ocupará el ancho disponible dentro de los márgenes del PDF */
            /* background-color: rgba(255, 255, 0, 0.3); */ /* Solo para depurar */
        }
        .header { text-align: center; margin-bottom: 5mm; } 
        /*.header-line { border-bottom: 0px solid #000; }*/
        .resolution { text-align: center; font-family: 'helvetica', sans-serif; font-size: 9pt; font-weight: bold; margin-top: 5mm; margin-bottom: 15mm; }
        .main-content { text-align: center; }
        .main-content .intro-text { font-size: 14pt; font-style: italic; margin-bottom: 8mm; }
        .student-name-container {padding: 3mm 0; margin: 5mm auto; width: 80%; }
        .main-content .student-name { font-size: 22pt; font-weight: bold; text-transform: uppercase; }
        .main-content .student-id { font-size: 10pt; font-weight: normal; text-transform: none; margin-top: 1.5mm; }
        .main-content .attended-text { font-size: 12pt; margin-top: 8mm; font-style: italic;}
        .main-content .course-name { font-size: 15pt; font-weight: bold; text-transform: uppercase; margin-top: 5mm; }
        .main-content .duration-text { font-size: 10pt; margin-top: 5mm; font-style: italic;}
        .date-section { text-align: center; margin-top: 15mm; font-size: 11pt; font-style: italic;}
        
        .footer-table { 
            width: 100%; /* Se ajustará al ancho del .certificate-container */
            position: absolute; 
            bottom: 10mm; /* Ajusta según el margen inferior del PDF y tu diseño */
            left: 0; 
            border-collapse: collapse; 
        }
        .footer-table td { vertical-align: bottom; text-align: center; padding: 0 2mm; }
        .signature-block { width: 70mm; margin: 0 auto; position: relative; height: 30mm; /* Aumentar altura si es necesario */ }
        .signature-image { position: absolute; bottom: 8mm; /* Ajustar para que no se solape con la línea */ left: 50%; transform: translateX(-50%); max-height: 20mm; max-width: 60mm; }
        .signature-line { position: absolute; bottom: 0; left: 0; right: 0; border-top: 1px solid #000; padding-top: 1.5mm; font-family: 'helvetica', sans-serif; font-size: 8pt; font-weight: bold; }
        .qr-code { width: 20mm; height: 20mm; }
        .qr-text { font-family: 'helvetica', sans-serif; font-size: 7pt; color: #555; display: block; margin-top: 1mm; }
    </style>
</head>
<body>
    <!-- No .page-wrapper, no img#background-image-html -->
    <div class="certificate-container">
        <div class="header">
            <!-- Logos y slogan eliminados -->
            <div class="header-line"></div>
        </div>

        <div class="resolution">
            APROBADO POR LA SECRETARÍA DE EDUCACIÓN MUNICIPAL<br>
            RESOLUCIÓN DE RENOVACIÓN 0153 DE 2012
        </div>

        <div class="main-content">
            <div class="intro-text">Hace constar que:</div>
            <div class="student-name-container">
                 <div class="student-name">{{student_name}}</div>
                 <div class="student-id">C.C. No. {{student_identification}}</div>
            </div>
            <div class="attended-text">Asistió a:</div>
            <div class="course-name">{{course_name}}</div>
            <div class="duration-text">Con una intensidad de <strong>{{duration}}</strong> horas</div>
        </div>

        <div class="date-section">Dado en Neiva a los {{issue_date}}</div>

        <table class="footer-table">
            <tr>
                <td style="width: 30%; text-align: left;"> 
                    <img src="{{qr_code_path}}" alt="QR Code" class="qr-code">
                    <span class="qr-text">Código: {{validation_code}}</span>
                </td>
                <td style="width: 40%;">
                    <div class="signature-block">
                        <img src="{{signature_path}}" alt="Firma" class="signature-image">
                        <div class="signature-line">
                            {{director_name}}<br>
                            JEFE DE DIVISIÓN SERVICIOS EDUCATIVOS
                        </div>
                    </div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <!-- Logo vigilado eliminado -->
                </td>
            </tr>
        </table>
    </div> <!-- Fin .certificate-container -->
</body>
</html>