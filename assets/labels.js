(function(){
    function ready(fn){ if(document.readyState!=='loading'){fn();} else {document.addEventListener('DOMContentLoaded',fn);} }
    ready(function(){
        var grid = document.getElementById('wooauthentix_label_grid');
        if(!grid) return;
        var labels = Array.prototype.slice.call(grid.querySelectorAll('.wooauthentix-label'));
    var qrSize = parseInt(grid.getAttribute('data-qr-size')||'110',10);
    var logoURL = grid.getAttribute('data-logo')||'';
    var overlay = grid.getAttribute('data-logo-overlay') === '1';
    var overlayScale = parseInt(grid.getAttribute('data-logo-scale')||'28',10);
    var productId = grid.getAttribute('data-product')||'0';
    var hostNS = grid.getAttribute('data-host')||location.host;
    var serverFallback = grid.getAttribute('data-server-fallback') === '1';
    var ajaxUrl = grid.getAttribute('data-ajax-url');
    var nonce = grid.getAttribute('data-nonce');
    var showBrand = grid.getAttribute('data-show-brand') === '1';
    var showCode = grid.getAttribute('data-show-code') === '1';
    var showSite = grid.getAttribute('data-show-site') === '1';
    function cacheKey(code){ return 'wooauth_qr_'+hostNS+'_'+productId+'_'+code+'_'+qrSize+'_'+(overlay?1:0); }
        function putCache(code,data){ try{ localStorage.setItem(cacheKey(code),data); }catch(e){} }
        function getCache(code){ try{ return localStorage.getItem(cacheKey(code)); }catch(e){ return null; } }
    function drawLogo(canvas,cb){ if(!overlay || !logoURL){ return cb && cb(); } try{ var ctx=canvas.getContext('2d'); var img=new Image(); img.crossOrigin='anonymous'; img.onload=function(){ var s=Math.min(canvas.width,canvas.height); var box=Math.round(s*(overlayScale/100)); var x=(canvas.width-box)/2; var y=(canvas.height-box)/2; ctx.fillStyle='#FFF'; ctx.fillRect(x,y,box,box); ctx.drawImage(img,x,y,box,box); cb && cb(); }; img.onerror=function(){ cb && cb(); }; img.src=logoURL; }catch(e){ cb && cb(); } }
        labels.forEach(function(label){
            var url = label.getAttribute('data-url');
            var code = label.getAttribute('data-code');
            // Remove hidden sections if toggles false (in case markup cached in browser history)
            if(!showBrand){ var b=label.querySelector('.brand'); if(b) b.remove(); }
            if(!showCode){ var c=label.querySelector('.code'); if(c) c.remove(); }
            if(!showSite){ var s=label.querySelector('.site'); if(s) s.remove(); }
            var qrDiv = label.querySelector('.qr');
            if(!qrDiv) return;
            qrDiv.textContent='';
            var cached = getCache(code);
            if(cached){ var img=new Image(); img.className='qr-img'; img.onload=function(){ qrDiv.innerHTML=''; qrDiv.appendChild(img); }; img.src=cached; return; }
            function finalize(canvas){ try{ var data=canvas.toDataURL('image/png'); putCache(code,data); var img=new Image(); img.className='qr-img'; img.onload=function(){ qrDiv.innerHTML=''; qrDiv.appendChild(img); }; img.src=data; }catch(e){ canvas.style.imageRendering='pixelated'; qrDiv.appendChild(canvas); } }
            if(typeof QRCode !== 'undefined' && typeof QRCode.toCanvas === 'function'){
                try {
                    QRCode.toCanvas(url,{width:qrSize,margin:0},function(err,canvas){
                        if(err){ qrDiv.textContent='ERR'; return; }
                        try{ var ctx=canvas.getContext('2d'); ctx.imageSmoothingEnabled=false; }catch(e){}
                        drawLogo(canvas,function(){ finalize(canvas); });
                    });
                } catch(e){ qrDiv.textContent='ERR'; }
                return;
            }
            if(typeof QRious !== 'undefined') {
                try {
                    var canvas = document.createElement('canvas');
                    new QRious({element:canvas,value:url,size:qrSize,level:'M'});
                    try{ var ctx=canvas.getContext('2d'); ctx.imageSmoothingEnabled=false; }catch(e){}
                    drawLogo(canvas,function(){ finalize(canvas); });
                } catch(e){ qrDiv.textContent='ERR'; }
                return;
            }
            if(serverFallback && ajaxUrl){
                qrDiv.textContent='...';
                var xhr=new XMLHttpRequest();
                xhr.onreadystatechange=function(){ if(xhr.readyState===4){ if(xhr.status===200){ try{ var resp=JSON.parse(xhr.responseText); if(resp.success && resp.data && resp.data.dataURI){ var img=new Image(); img.className='qr-img'; img.onload=function(){ qrDiv.innerHTML=''; qrDiv.appendChild(img); putCache(code,resp.data.dataURI); }; img.src=resp.data.dataURI; return; } }catch(e){} qrDiv.textContent='ERR'; } } };
                xhr.open('GET', ajaxUrl+'?action=wooauthentix_qr&data='+encodeURIComponent(url)+'&s='+qrSize+'&_wpnonce='+encodeURIComponent(nonce), true);
                xhr.send();
            } else {
                qrDiv.textContent='NO QR';
            }
        });
        var printBtn=document.getElementById('wooauthentix_print_btn');
        if(printBtn) printBtn.addEventListener('click',function(){ window.print(); });
        var pdfBtn=document.getElementById('wooauthentix_pdf_btn');
        var statusEl=document.getElementById('wooauthentix_pdf_status');
        var paperSel=document.getElementById('wooauthentix_paper');
        var purgeBtn=document.getElementById('wooauthentix_purge_cache_btn');
        var customTextsTA=document.getElementById('wooauthentix_custom_texts');
        var applyTextBtn=document.getElementById('wooauthentix_apply_text_btn');

        function purgeCache(){
            try{
                var prefix='wooauth_qr_'+hostNS+'_'+productId+'_';
                for(var i=localStorage.length-1;i>=0;i--){ var k=localStorage.key(i); if(k.indexOf(prefix)===0){ localStorage.removeItem(k); } }
            }catch(e){}
            if(statusEl){ statusEl.textContent=(window.wooauthentixLabels && window.wooauthentixLabels.i18n.purged)||'Purged'; setTimeout(function(){ statusEl.textContent=''; },2500); }
        }
        if(purgeBtn) purgeBtn.addEventListener('click',purgeCache);

        function applyCustomTexts(){
            if(!customTextsTA) return;
            var lines=customTextsTA.value.split(/\r?\n/); if(lines.length===1 && lines[0]===''){ lines=[]; }
            var i=0;
            labels.forEach(function(label){
                var existing=label.querySelector('.extra-line');
                if(existing) existing.remove();
                if(lines[i]){
                    var div=document.createElement('div');
                    div.className='extra-line';
                    div.style.cssText='margin-top:2px;font-size:9px;color:#333;';
                    div.textContent=lines[i];
                    label.appendChild(div);
                }
                i++;
            });
        }
        if(applyTextBtn) applyTextBtn.addEventListener('click',applyCustomTexts);

        if(pdfBtn){
            pdfBtn.addEventListener('click',function(){
                if(!window.jspdf || !html2canvas){ return; }
                statusEl.textContent = (window.wooauthentixLabels && window.wooauthentixLabels.i18n.rendering) || 'Rendering...';
                // Multi-page: rasterize each label individually and compose into PDF grid according to paper size.
                setTimeout(function(){
                    var choice = paperSel ? paperSel.value : 'a4-p';
                    var parts = choice.split('-'); var base = parts[0]; var orient = parts[1]||'p';
                    var ps = (window.wooauthentixLabels && window.wooauthentixLabels.paperSizes && window.wooauthentixLabels.paperSizes[base]) || {w:210,h:297};
                    var pageW = orient==='p'?ps.w:ps.h; var pageH = orient==='p'?ps.h:ps.w;
                    var unit='mm';
                    var pdf=new window.jspdf.jsPDF({orientation:orient,unit:unit,format:[pageW,pageH]});
                    // Determine label element dimensions in px
                    var firstLabel=labels[0]; if(!firstLabel){ statusEl.textContent='0'; return; }
                    var labelRect=firstLabel.getBoundingClientRect();
                    // Assume 96dpi -> 1in = 25.4mm ; convert px to mm: mm = px * 25.4 / 96
                    var px2mm = 25.4/96;
                    var lblWmm = labelRect.width * px2mm;
                    var lblHmm = labelRect.height * px2mm;
                    var gapMm = 4; // small gap
                    var cols = Math.max(1, Math.floor((pageW - gapMm) / (lblWmm + gapMm)));
                    var rows = Math.max(1, Math.floor((pageH - gapMm) / (lblHmm + gapMm)));
                    var perPage = cols * rows;
                    var renderIndex = 0;
                    function renderLabelCanvas(el){
                        return html2canvas(el,{scale:2,useCORS:true});
                    }
                    function addPage(labelsSlice, cb){
                        var promises = labelsSlice.map(function(el){ return renderLabelCanvas(el); });
                        Promise.all(promises).then(function(canvases){
                            canvases.forEach(function(c,idx){
                                var pageIndex = Math.floor(renderIndex / perPage);
                                var posInPage = renderIndex % perPage;
                                if(posInPage===0 && renderIndex>0){ pdf.addPage([pageW,pageH], orient); }
                                var col = posInPage % cols; var row = Math.floor(posInPage / cols);
                                var x = gapMm + col*(lblWmm + gapMm);
                                var y = gapMm + row*(lblHmm + gapMm);
                                var imgData=c.toDataURL('image/png');
                                pdf.addImage(imgData,'PNG',x,y,lblWmm,lblHmm);
                                renderIndex++;
                            });
                            cb();
                        }).catch(function(){ statusEl.textContent = (window.wooauthentixLabels && window.wooauthentixLabels.i18n.failed) || 'Failed'; });
                    }
                    var i=0;
                    function next(){
                        if(i>=labels.length){
                            var pid = (window.wooauthentixLabels && window.wooauthentixLabels.product_id) || 0;
                            pdf.save('qr-labels-'+pid+'.pdf');
                            statusEl.textContent = (window.wooauthentixLabels && window.wooauthentixLabels.i18n.done) || 'Done';
                            setTimeout(function(){ statusEl.textContent=''; },4000);
                            return;
                        }
                        var slice = labels.slice(i, i+perPage);
                        addPage(slice, function(){ i+=perPage; next(); });
                    }
                    next();
                },80);
            });
        }
    });
})();
