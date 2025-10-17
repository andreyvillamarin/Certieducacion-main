document.addEventListener('DOMContentLoaded', function() {
    const designerDataElement = document.getElementById('designer-data');
    if (!designerDataElement) {
        console.error('Elemento de datos del diseñador no encontrado.');
        return;
    }

    const savedTemplate = JSON.parse(designerDataElement.dataset.template);
    const defaultTemplate = JSON.parse(designerDataElement.dataset.defaultTemplate);
    const basePath = designerDataElement.dataset.basePath;
    const signaturePath = basePath + designerDataElement.dataset.signaturePath;

    const A4_WIDTH = 842;
    const A4_HEIGHT = 595;

    const canvas = new fabric.Canvas('certificate-canvas', {
        width: A4_WIDTH,
        height: A4_HEIGHT,
        backgroundColor: '#f0f0f0',
        preserveObjectStacking: true
    });

    const canvasContainer = document.getElementById('canvas-container');

    function loadTemplate(templateData) {
        const data = JSON.parse(JSON.stringify(templateData));
        const backgroundData = data.backgroundImage;
        delete data.backgroundImage;

        canvas.clear();
        canvas.loadFromJSON(data, () => {
            const signaturePlaceholder = canvas.getObjects().find(obj => obj.data && obj.data.field === 'signature');
            if (signaturePlaceholder) {
                fabric.Image.fromURL(signaturePath, function(sigImg) {
                    const placeholderWidth = signaturePlaceholder.getScaledWidth();
                    const placeholderHeight = signaturePlaceholder.getScaledHeight();
                    
                    const scaleX = placeholderWidth / sigImg.width;
                    const scaleY = placeholderHeight / sigImg.height;
                    const scale = Math.min(scaleX, scaleY);

                    sigImg.scale(scale);

                    const newLeft = signaturePlaceholder.left + (placeholderWidth - sigImg.getScaledWidth()) / 2;
                    const newTop = signaturePlaceholder.top + (placeholderHeight - sigImg.getScaledHeight()) / 2;

                    sigImg.set({
                        left: newLeft,
                        top: newTop,
                        data: signaturePlaceholder.data,
                        crossOrigin: 'anonymous'
                    });
                    canvas.remove(signaturePlaceholder);
                    canvas.add(sigImg);
                    canvas.renderAll();
                }, { crossOrigin: 'anonymous' });
            }
            
            if (backgroundData && backgroundData.src) {
                const backgroundUrl = basePath + backgroundData.src;
                fabric.Image.fromURL(backgroundUrl, function(bgImg) {
                    canvas.setBackgroundImage(bgImg, canvas.renderAll.bind(canvas), {
                        scaleX: A4_WIDTH / bgImg.width,
                        scaleY: A4_HEIGHT / bgImg.height,
                        originX: 'left',
                        originY: 'top',
                        crossOrigin: 'anonymous'
                    });
                }, { crossOrigin: 'anonymous' });
            } else {
                canvas.setBackgroundImage(null, canvas.renderAll.bind(canvas));
            }
        });
    }

    function resizeCanvas() {
        const containerWidth = canvasContainer.clientWidth;
        const scale = containerWidth / A4_WIDTH;
        
        canvas.setDimensions({
            width: containerWidth,
            height: A4_HEIGHT * scale
        });
        canvas.setZoom(scale);
        canvas.renderAll();
        canvas.calcOffset();
    }
    
    loadTemplate(Object.keys(savedTemplate).length > 1 ? savedTemplate : defaultTemplate);
    setTimeout(resizeCanvas, 300);
    window.addEventListener('resize', resizeCanvas);
    
    const textControls = document.getElementById('text-controls');
    const objectControls = document.getElementById('object-controls');
    const fontFamily = document.getElementById('font-family');
    const fontSize = document.getElementById('font-size');
    const fontColor = document.getElementById('font-color');
    const fontBold = document.getElementById('font-bold');
    const fontItalic = document.getElementById('font-italic');
    const fontUnderline = document.getElementById('font-underline');
    const alignLeft = document.getElementById('align-left');
    const alignCenter = document.getElementById('align-center');
    const alignRight = document.getElementById('align-right');
    const alignJustify = document.getElementById('align-justify');
    const alignmentButtons = [alignLeft, alignCenter, alignRight, alignJustify];
    const objectAlignCenterH = document.getElementById('object-align-center-h');
    const objectAlignCenterV = document.getElementById('object-align-center-v');
    const fontUppercase = document.getElementById('font-uppercase');

    function updateControls(target) {
        if (target) {
            objectControls.style.display = 'block';
        } else {
            objectControls.style.display = 'none';
        }

        if (target && target.isType('textbox')) {
            textControls.style.display = 'block';
            fontFamily.value = target.get('fontFamily') || 'Arial';
            fontSize.value = target.get('fontSize');
            fontColor.value = target.get('fill');
            fontBold.classList.toggle('active', target.get('fontWeight') === 'bold');
            fontItalic.classList.toggle('active', target.get('fontStyle') === 'italic');
            fontUnderline.classList.toggle('active', target.get('underline') === true);
            fontUppercase.classList.toggle('active', target.data && target.data.isUppercase);
            
            const currentAlign = target.get('textAlign') || 'left';
            alignmentButtons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.id === `align-${currentAlign}`) {
                    btn.classList.add('active');
                }
            });
        } else {
            textControls.style.display = 'none';
        }
    }

    canvas.on({
        'selection:created': (e) => updateControls(e.selected[0]),
        'selection:updated': (e) => updateControls(e.selected[0]),
        'selection:cleared': () => updateControls(null)
    });

    fontFamily.addEventListener('change', (e) => { const obj = canvas.getActiveObject(); if (obj) { obj.set('fontFamily', e.target.value); canvas.renderAll(); } });
    fontSize.addEventListener('input', (e) => { const obj = canvas.getActiveObject(); if (obj) { obj.set('fontSize', parseInt(e.target.value, 10) || 12); canvas.renderAll(); } });
    fontColor.addEventListener('input', (e) => { const obj = canvas.getActiveObject(); if (obj) { obj.set('fill', e.target.value); canvas.renderAll(); } });
    fontBold.addEventListener('click', () => { const obj = canvas.getActiveObject(); if (obj) { obj.set('fontWeight', obj.get('fontWeight') === 'bold' ? 'normal' : 'bold'); fontBold.classList.toggle('active'); canvas.renderAll(); } });
    fontItalic.addEventListener('click', () => { const obj = canvas.getActiveObject(); if (obj) { obj.set('fontStyle', obj.get('fontStyle') === 'italic' ? 'normal' : 'italic'); fontItalic.classList.toggle('active'); canvas.renderAll(); } });
    fontUnderline.addEventListener('click', () => { const obj = canvas.getActiveObject(); if (obj) { obj.set('underline', !obj.get('underline')); fontUnderline.classList.toggle('active'); canvas.renderAll(); } });
    fontUppercase.addEventListener('click', () => {
        const obj = canvas.getActiveObject();
        if (obj && obj.isType('textbox')) {
            if (!obj.data) {
                obj.data = {};
            }
            obj.data.isUppercase = !obj.data.isUppercase;

            if (obj.data.isUppercase) {
                obj.set('text', obj.text.toUpperCase());
            } else {
                obj.set('text', obj.text.toLowerCase());
            }
            updateControls(obj);
            canvas.renderAll();
        }
    });

    alignmentButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const align = btn.id.replace('align-', '');
            const activeObject = canvas.getActiveObject();
            if (activeObject && activeObject.isType('textbox')) {
                activeObject.set('textAlign', align);
                alignmentButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                canvas.renderAll();
            }
        });
    });

    objectAlignCenterH.addEventListener('click', () => {
        const activeObject = canvas.getActiveObject();
        if (activeObject) { activeObject.viewportCenterH(); canvas.renderAll(); }
    });

    objectAlignCenterV.addEventListener('click', () => {
        const activeObject = canvas.getActiveObject();
        if (activeObject) { activeObject.viewportCenterV(); canvas.renderAll(); }
    });

    document.getElementById('background-uploader').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(f) {
            fabric.Image.fromURL(f.target.result, function(img) {
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                    scaleX: A4_WIDTH / img.width,
                    scaleY: A4_HEIGHT / img.height,
                    originX: 'left',
                    originY: 'top',
                    crossOrigin: 'anonymous'
                });
                resizeCanvas(); // Re-center/zoom the canvas after new bg
            });
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('signature-uploader').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(f) {
            const signatureObject = canvas.getObjects().find(obj => obj.data && obj.data.field === 'signature');
            if (!signatureObject) {
                alert('No se encontró el marcador de posición de la firma en la plantilla.');
                return;
            }

            fabric.Image.fromURL(f.target.result, function(newSigImg) {
                const placeholderWidth = signatureObject.getScaledWidth();
                const placeholderHeight = signatureObject.getScaledHeight();
                
                const scaleX = placeholderWidth / newSigImg.width;
                const scaleY = placeholderHeight / newSigImg.height;
                const scale = Math.min(scaleX, scaleY);

                newSigImg.scale(scale);

                const newLeft = signatureObject.left + (placeholderWidth - newSigImg.getScaledWidth()) / 2;
                const newTop = signatureObject.top + (placeholderHeight - newSigImg.getScaledHeight()) / 2;

                newSigImg.set({
                    left: newLeft,
                    top: newTop,
                    data: signatureObject.data,
                    crossOrigin: 'anonymous'
                });

                canvas.remove(signatureObject);
                canvas.add(newSigImg);
                canvas.renderAll();
            });
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('save-template').addEventListener('click', function() {
        const button = this;
        button.disabled = true;
        const formData = new FormData();
        
        const jsonOutput = { version: '5.3.1', objects: [] };
        canvas.getObjects().forEach(obj => {
            let wasModified = false;
            const originalStyles = obj.styles;

            // Workaround for fabric.js bug:
            // Normalize styles on text objects before serialization.
            // The bug occurs if .styles is undefined or an empty object.
            if (obj.isType && (obj.isType('textbox') || obj.isType('i-text'))) {
                if (!obj.styles || Object.keys(obj.styles).length === 0) {
                    obj.styles = {};
                    wasModified = true;
                }
            }
            
            jsonOutput.objects.push(obj.toObject(['data']));

            // Restore the object to its original state if it was modified.
            if (wasModified) {
                obj.styles = originalStyles;
            }
        });
        if (canvas.backgroundImage) {
            const bgSrc = canvas.backgroundImage.getSrc();
            if (bgSrc.startsWith('data:')) {
                jsonOutput.backgroundImage = { type: 'image', src: '' };
            } else {
                const templateObject = canvas.backgroundImage.toObject(['data']);
                const assetsPath = 'assets/img/';
                const indexOfAssets = bgSrc.indexOf(assetsPath);
                if (indexOfAssets > -1) {
                    templateObject.src = bgSrc.substring(indexOfAssets);
                }
                jsonOutput.backgroundImage = templateObject;
            }
        }
        
        formData.append('template_json', JSON.stringify(jsonOutput));
        
        const signatureFile = document.getElementById('signature-uploader').files[0];
        if (signatureFile) { formData.append('signature_image', signatureFile); }
        
        const backgroundFile = document.getElementById('background-uploader').files[0];
        if (backgroundFile) { formData.append('background_image', backgroundFile); }
        
        fetch('ajax_save_template.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Plantilla guardada con Éxito!');
                if (data.new_template_json) { 
                    designerDataElement.dataset.template = JSON.stringify(data.new_template_json);
                    loadTemplate(data.new_template_json);
                }
            } else {
                alert('Error al guardar: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión.');
        })
        .finally(() => { button.disabled = false; });
    });

    document.getElementById('reset-template').addEventListener('click', function() {
        if (confirm('Restaurar la plantilla original? Perderás los cambios no guardados.')) {
            loadTemplate(defaultTemplate);
            alert('Plantilla restaurada. Haz clic en "Guardar Cambios" para confirmar.');
        }
    });
});