(function(){function ready(f){if(document.readyState!=='loading'){f();}else{document.addEventListener('DOMContentLoaded',f);} }
ready(function(){
 const wrapper=document.getElementById('wooauthentix-guide');
 if(!wrapper) return;
 var box=document.createElement('div');
 box.id='wooauthentix-preview';
 box.style.cssText='border:1px solid #ccd;padding:12px;margin:12px 0;background:#fff;max-width:760px;';
 box.innerHTML='<strong>Label Preview</strong><div id="wooauthentix-preview-label" style="margin-top:8px;display:inline-block;border:1px solid #111;padding:6px;text-align:center;font:11px/1.3 sans-serif;min-width:180px;"><div class="brand">Brand</div><div class="qr" style="width:110px;height:110px;margin:0 auto 4px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#888;">QR</div><div class="code">CODE123456</div><div class="site" style="font-size:9px;color:#555;">example.com</div></div><p style="font-size:11px;color:#555;margin:6px 0 10px;">Label changes reflect automatically.</p><strong>Verification Page Preview</strong><div id="wooauthentix-preview-verification-wrapper" style="margin-top:8px;padding:14px;background:#f5f5f7;"><div id="wooauthentix-preview-verification" style="padding:20px 22px;border:1px solid #ccc;background:#fff;max-width:500px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.06);"><h3 style="margin:0 0 10px;font-size:18px;">Product Authenticity</h3><form><label style="display:block;margin-bottom:6px;font-weight:600;">Enter authenticity code</label><input type="text" disabled value="ABC123" style="padding:8px;font-size:14px;width:160px;" /> <button type="button" disabled style="padding:8px 14px;">Verify</button></form><div class="msg" style="margin:12px 0 0;font-size:13px;color:#2d7;">First-time verification: product authenticated.</div></div><p style="font-size:11px;color:#555;margin:10px 0 0;">Verification design updates live (heading, first-time message, width & background colour).</p></div>';
 wrapper.parentNode.insertBefore(box, wrapper.nextSibling);
 function qs(sel){return document.querySelector(sel);} 
 function valInt(sel,def){var el=qs(sel);return el?parseInt(el.value,10)||def:def;}
 function checked(sel){var el=qs(sel);return el?el.checked:false;}
 function updateLabels(){
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
  var b=label.querySelector('.brand'); if(b){ if(!showBrand){b.style.display='none';} else {b.style.display='block'; b.textContent=brand;} }
  var codeEl=label.querySelector('.code'); if(codeEl){codeEl.style.display=showCode?'block':'none';}
  var siteEl=label.querySelector('.site'); if(siteEl){siteEl.style.display=showSite?'block':'none';}
  qrHolder.innerHTML='';
  var value='SAMPLE';
  var done=function(canvas){ if(overlay && showLogo){ var ctx=canvas.getContext('2d'); var box=Math.round(canvas.width*(overlayScale/100)); var x=(canvas.width-box)/2; var y=(canvas.height-box)/2; ctx.fillStyle='#FFF'; ctx.fillRect(x,y,box,box); ctx.fillStyle='#000'; ctx.font=Math.round(box*0.4)+'px sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('L',x+box/2,y+box/2); } qrHolder.appendChild(canvas); };
  try{ if(typeof QRCode!=='undefined' && typeof QRCode.toCanvas==='function'){ QRCode.toCanvas(value,{width:size,margin:0},function(err,canvas){ if(err){qrHolder.textContent='ERR';return;} done(canvas); }); } else if(typeof QRious!=='undefined'){ var canvas=document.createElement('canvas'); new QRious({element:canvas,value:value,size:size,level:'M'}); done(canvas); } else { qrHolder.textContent='QR lib missing'; } }catch(e){ qrHolder.textContent='ERR'; }
 }
 function updateVerification(){
  var wrap=qs('#wooauthentix-preview-verification-wrapper'); var boxV=qs('#wooauthentix-preview-verification'); if(!wrap||!boxV) return;
  var headingField=qs('input[name="wooauthentix_settings[verification_heading]"]');
  var widthField=qs('input[name="wooauthentix_settings[verification_container_width]"]');
  var bgField=qs('input[name="wooauthentix_settings[verification_bg_color]"]');
  var msgFirst=qs('input[name="wooauthentix_settings[verification_msg_first_time]"]');
  var h=headingField && headingField.value.trim()? headingField.value.trim() : 'Product Authenticity';
  var w=parseInt(widthField && widthField.value? widthField.value:500,10); if(isNaN(w)||w<320) w=320; if(w>1200) w=1200;
  var bg=bgField && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(bgField.value.trim())? bgField.value.trim() : '#f5f5f7';
  var msg=msgFirst && msgFirst.value.trim()? msgFirst.value.trim(): 'First-time verification: product authenticated.';
  boxV.style.maxWidth=w+'px';
  wrap.style.background=bg;
  var hEl=boxV.querySelector('h3'); if(hEl) hEl.textContent=h;
  var msgEl=boxV.querySelector('.msg'); if(msgEl) msgEl.textContent=msg;
 }
 document.addEventListener('input',function(e){ if(e.target && e.target.name && e.target.name.indexOf('wooauthentix_settings[')===0){ updateLabels(); updateVerification(); }});
 document.addEventListener('change',function(e){ if(e.target && e.target.name && e.target.name.indexOf('wooauthentix_settings[')===0){ updateLabels(); updateVerification(); }});
 updateLabels(); updateVerification();
});})();
