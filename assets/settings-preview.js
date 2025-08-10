(function(){function ready(f){if(document.readyState!=='loading'){f();}else{document.addEventListener('DOMContentLoaded',f);}}
ready(function(){
 const wrapper=document.getElementById('wooauthentix-guide'); // place preview after guide
 if(!wrapper) return;
 // Build preview box
 var box=document.createElement('div');
 box.id='wooauthentix-preview';
 box.style.cssText='border:1px solid #ccd;padding:12px;margin:12px 0;background:#fff;max-width:500px;';
 box.innerHTML='<strong>Label Preview</strong><div id="wooauthentix-preview-label" style="margin-top:8px;display:inline-block;border:1px solid #111;padding:6px;text-align:center;font:11px/1.3 sans-serif;min-width:180px;"><div class="brand">Brand</div><div class="qr" style="width:110px;height:110px;margin:0 auto 4px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#888;">QR</div><div class="code">CODE123456</div><div class="site" style="font-size:9px;color:#555;">example.com</div></div><p style="font-size:11px;color:#555;margin:6px 0 0;">Changes reflect automatically (client-side approximation).</p>';
 wrapper.parentNode.insertBefore(box, wrapper.nextSibling);
 function qs(sel){return document.querySelector(sel);} 
 function valInt(sel,def){var el=qs(sel);return el?parseInt(el.value,10)||def:def;}
 function checked(sel){var el=qs(sel);return el?el.checked:false;}
 function update(){
  var label=qs('#wooauthentix-preview-label'); if(!label) return;
  var size=valInt('input[name="wooauthentix_settings[label_qr_size]"]',110);
  var showBrand=checked('input[name="wooauthentix_settings[label_show_brand]"]');
  var showCode=checked('input[name="wooauthentix_settings[label_show_code]"]');
  var showSite=checked('input[name="wooauthentix_settings[label_show_site]"]');
  var showLogo=checked('input[name="wooauthentix_settings[label_show_logo]"]');
  var overlay=checked('input[name="wooauthentix_settings[label_logo_overlay]"]');
  var overlayScale=valInt('input[name="wooauthentix_settings[label_logo_overlay_scale]"]',28);
  var brandInput=qs('input[name="wooauthentix_settings[label_brand_text]"]');
  var brand=(brandInput && brandInput.value.trim()) || 'Brand';
  var qrHolder=label.querySelector('.qr');
  qrHolder.style.width=qrHolder.style.height=size+'px';
  // rebuild brand
  var b=label.querySelector('.brand'); if(b){ if(!showBrand){b.style.display='none';} else {b.style.display='block'; b.textContent=brand;} }
  var codeEl=label.querySelector('.code'); if(codeEl){codeEl.style.display=showCode?'block':'none';}
  var siteEl=label.querySelector('.site'); if(siteEl){siteEl.style.display=showSite?'block':'none';}
  // Generate simple QR client side (value = CODE123456) for preview
  qrHolder.innerHTML='';
  var value='SAMPLE';
  var done=function(canvas){ if(overlay && showLogo){ var ctx=canvas.getContext('2d'); var box=Math.round(canvas.width*(overlayScale/100)); var x=(canvas.width-box)/2; var y=(canvas.height-box)/2; ctx.fillStyle='#FFF'; ctx.fillRect(x,y,box,box); ctx.fillStyle='#000'; ctx.font=Math.round(box*0.4)+'px sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('L',x+box/2,y+box/2); }
   qrHolder.appendChild(canvas);
  };
  try{
    if(typeof QRCode!=='undefined' && typeof QRCode.toCanvas==='function'){
      QRCode.toCanvas(value,{width:size,margin:0},function(err,canvas){ if(err){qrHolder.textContent='ERR';return;} done(canvas); });
    } else if(typeof QRious!=='undefined') {
      var canvas=document.createElement('canvas'); new QRious({element:canvas,value:value,size:size,level:'M'}); done(canvas);
    } else { qrHolder.textContent='QR lib missing'; }
  }catch(e){ qrHolder.textContent='ERR'; }
 }
 document.addEventListener('input',function(e){ if(e.target && e.target.name && e.target.name.indexOf('wooauthentix_settings[')===0){ update(); }});
 document.addEventListener('change',function(e){ if(e.target && e.target.name && e.target.name.indexOf('wooauthentix_settings[')===0){ update(); }});
 update();
});})();
