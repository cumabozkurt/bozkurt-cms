/* BOZKURT CMS — yönetim arayüzü betikleri (bağımlılıksız, ~8 KB) */
(function () {
  'use strict';
  var CEVIRI = window.BZ_CEVIRI || {};
  function T(x) { return CEVIRI[x] || x; }
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var csrf = ($('meta[name=csrf]') || {}).content || '';
  var taban = document.body.dataset.taban || '/';

  /* Tema ve mobil menü */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-tema-degistir]');
    if (t) {
      var yeni = document.documentElement.dataset.tema === 'koyu' ? 'acik' : 'koyu';
      document.documentElement.dataset.tema = yeni;
      localStorage.setItem('bz_tema', yeni);
    }
    if (e.target.closest('[data-menu]')) document.body.classList.toggle('menu-acik');
    else if (document.body.classList.contains('menu-acik') && !e.target.closest('.yan')) document.body.classList.remove('menu-acik');
  });

  /* Onay gerektiren düğmeler */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-onay]');
    if (b && !confirm(b.dataset.onay)) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  /* Hepsini seç */
  $$('[data-hepsini-sec]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      $$('input[name="secili[]"]', cb.closest('form')).forEach(function (x) { x.checked = cb.checked; });
    });
  });

  /* Türkçe slug */
  var trMap = { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'İ': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u', 'Ç': 'c', 'Ğ': 'g', 'Ö': 'o', 'Ş': 's', 'Ü': 'u', 'â': 'a', 'î': 'i', 'û': 'u' };
  function slug(s) {
    return s.replace(/[çğıİöşüÇĞÖŞÜâîû]/g, function (c) { return trMap[c]; }).toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/['"`]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
  }
  var sk = $('[data-slug-kaynak]'), sh = $('[data-slug-hedef]');
  if (sk && sh) {
    var elle = sh.value !== '';
    sh.addEventListener('input', function () { elle = sh.value !== ''; });
    sk.addEventListener('input', function () {
      if (!elle) sh.placeholder = slug(sk.value) || 'otomatik';
      var sb = $('[data-serp-baslik]'), sbk = $('[data-serp-kaynak=baslik]');
      if (sb && sbk && !sbk.value) sb.textContent = sk.value;
    });
    sh.addEventListener('blur', function () { if (sh.value) sh.value = slug(sh.value); });
  }

  /* SEO önizleme ve karakter sayaçları */
  $$('[data-serp-kaynak]').forEach(function (inp) {
    var hedef = $('[data-serp-' + inp.dataset.serpKaynak + ']');
    var sayac = inp.closest('.alan').querySelector('[data-sayac]');
    function guncelle() {
      if (hedef && inp.value) hedef.textContent = inp.value;
      if (sayac) { var max = +sayac.dataset.sayac; sayac.textContent = inp.value.length + ' / ' + max; sayac.classList.toggle('asim', inp.value.length > max); }
    }
    inp.addEventListener('input', guncelle); guncelle();
  });

  /* Sekmeler (ayarlar) */
  $$('[data-sekmeler]').forEach(function (kutu) {
    var linkler = $$('.sekmeler a', kutu);
    function goster(id) {
      linkler.forEach(function (a) { a.classList.toggle('aktif', a.getAttribute('href') === '#' + id); });
      $$('section[id]', kutu).forEach(function (s) { s.hidden = s.id !== id; });
    }
    linkler.forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); var id = a.getAttribute('href').slice(1); goster(id); history.replaceState(null, '', '#' + id); }); });
    if (location.hash && $(location.hash, kutu)) goster(location.hash.slice(1));
  });

  /* Ctrl+S ile kaydet */
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      var f = $('form[data-kaydet-kisayol]');
      if (f) { e.preventDefault(); var b = f.querySelector('button[value=yayinla],button[value=kaydet],button:not([type=button]):last-of-type'); senkronize(); b ? b.click() : f.submit(); }
    }
  });

  /* Kaydedilmemiş değişiklik uyarısı */
  var degisti = false;
  $$('form[data-kaydet-kisayol]').forEach(function (f) {
    f.addEventListener('input', function () { degisti = true; });
    f.addEventListener('submit', function () { degisti = false; senkronize(); });
  });
  window.addEventListener('beforeunload', function (e) { if (degisti) { e.preventDefault(); e.returnValue = ''; } });

  /* İstemci tarafı HTML temizleyici: YZ çıktısı ve yapıştırılan içerik panelde çalıştırılmadan önce
     (sunucu kayıtta ayrıca izin listesiyle temizler). Prompt enjeksiyonu → XSS zincirini kırar. */
  function bzTemizle(html) {
    var doc = new DOMParser().parseFromString('<div>' + String(html) + '</div>', 'text/html'), kok = doc.body.firstChild;
    $$('script,style,object,embed,link,meta,form,input,button,textarea,select,base,svg,math', kok).forEach(function (n) { n.remove(); });
    $$('iframe', kok).forEach(function (n) { if (!/^https:\/\/(www\.youtube-nocookie\.com|www\.youtube\.com|player\.vimeo\.com)\//.test(n.getAttribute('src') || '')) n.remove(); });
    $$('*', kok).forEach(function (n) {
      Array.prototype.slice.call(n.attributes).forEach(function (a) {
        var ad = a.name.toLowerCase();
        if (ad.indexOf('on') === 0 || ad === 'style' || ad === 'srcdoc' || ((ad === 'href' || ad === 'src' || ad === 'xlink:href' || ad === 'action') && /^\s*(javascript|data|vbscript):/i.test(a.value))) n.removeAttribute(a.name);
      });
    });
    return kok.innerHTML;
  }
  window.bzTemizle = bzTemizle;

  /* Zengin metin editörü */
  function senkronize() {
    $$('[data-editor]').forEach(function (ed) {
      var ta = ed.querySelector('textarea[name]'), alan = ed.querySelector('.editor-alan');
      if (!ed.classList.contains('kaynak-modu')) ta.value = alan.innerHTML.trim() === '<br>' ? '' : alan.innerHTML;
    });
  }
  $$('[data-editor]').forEach(function (ed) {
    var alan = ed.querySelector('.editor-alan'), ta = ed.querySelector('textarea[name]');
    document.execCommand && document.execCommand('defaultParagraphSeparator', false, 'p');
    alan.addEventListener('input', function () { ta.value = alan.innerHTML; });
    alan.addEventListener('paste', function (e) {
      // Word/Google Docs'tan gelen çöp biçimleri temizle
      var html = (e.clipboardData || window.clipboardData).getData('text/html');
      if (!html) return;
      e.preventDefault();
      // DOMParser ile ayrıştırılan belge etkisizdir: yapıştırılan <img onerror> vb. çalışmaz
      var tmp = new DOMParser().parseFromString(html, 'text/html').body;
      $$('style,script,meta,link,o\\:p', tmp).forEach(function (n) { n.remove(); });
      $$('*', tmp).forEach(function (n) { n.removeAttribute('style'); n.removeAttribute('class'); n.removeAttribute('id'); });
      document.execCommand('insertHTML', false, bzTemizle(tmp.innerHTML));
    });
    ed.querySelector('.editor-arac').addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var k = b.dataset.k, v = b.dataset.v;
      if (k === 'kaynak') {
        if (ed.classList.toggle('kaynak-modu')) { ta.value = alan.innerHTML; ta.hidden = false; ta.classList.add('kaynak'); alan.hidden = true; }
        else { alan.innerHTML = bzTemizle(ta.value); ta.hidden = true; alan.hidden = false; }
        return;
      }
      alan.focus();
      if (k === 'link') { var u = prompt(T('Bağlantı adresi (https://…)')); if (u) document.execCommand('createLink', false, u); }
      else if (k === 'resim') { medyaAc(function (m) { document.execCommand('insertHTML', false, '<img src="' + m.url + '" alt="' + (m.alt || '').replace(/"/g, '') + '">'); ta.value = alan.innerHTML; }, 'resim'); }
      else if (k === 'video') {
        var y = prompt(T('YouTube bağlantısı')); var id = y && (y.match(/(?:v=|youtu\.be\/|shorts\/|embed\/)([\w-]{11})/) || [])[1];
        if (id) document.execCommand('insertHTML', false, '<p><iframe src="https://www.youtube-nocookie.com/embed/' + id + '" width="560" height="315" allowfullscreen title="Video"></iframe></p>');
      }
      else if (k === 'formatBlock') document.execCommand('formatBlock', false, '<' + v + '>');
      else document.execCommand(k, false, null);
      ta.value = alan.innerHTML;
    });
  });

  /* Medya kütüphanesi modalı */
  var modal = $('#medya-modal'), izgara = modal && $('[data-medya-izgara]', modal), secimCb = null, filtre = '';
  function medyaYukle(q) {
    var url = document.body.dataset.medyaUrl + (q ? '&q=' + encodeURIComponent(q) : '') + (filtre ? '&tur=' + filtre : '');
    fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      izgara.innerHTML = '';
      if (!d.ogeler.length) izgara.innerHTML = T('<p class="soluk">Henüz dosya yok. “Yükle” ile ekleyin.</p>');
      d.ogeler.forEach(function (m) {
        var b = document.createElement('button'); b.type = 'button'; b.title = m.orijinal;
        b.innerHTML = /^image\//.test(m.mime) ? '<img loading="lazy" alt="">' : '<span>' + m.orijinal.replace(/</g, '&lt;') + '</span>';
        if (/^image\//.test(m.mime)) b.querySelector('img').src = m.url;
        b.addEventListener('click', function () { if (secimCb) secimCb(m); modal.hidden = true; });
        izgara.appendChild(b);
      });
    });
  }
  function medyaAc(cb, tur) { if (!modal) return; secimCb = cb; filtre = tur || ''; modal.hidden = false; medyaYukle(''); setTimeout(function () { $('[data-medya-ara]', modal).focus(); }, 50); }
  if (modal) {
    modal.addEventListener('click', function (e) { if (e.target === modal || e.target.closest('[data-modal-kapat]')) modal.hidden = true; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.hidden = true; });
    var zaman; $('[data-medya-ara]', modal).addEventListener('input', function (e) { clearTimeout(zaman); zaman = setTimeout(function () { medyaYukle(e.target.value); }, 250); });
    $('[data-medya-yukle]', modal).addEventListener('change', function (e) { dosyaGonder(e.target.files, function () { medyaYukle(''); }); e.target.value = ''; });
  }
  function dosyaGonder(files, done) {
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('eylem', 'yukle');
    Array.prototype.forEach.call(files, function (f) { fd.append('dosya[]', f); });
    izgara && (izgara.innerHTML = T('<p class="soluk">Yükleniyor…</p>'));
    fetch(taban + 'yonetim/?s=medya&json=1', { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); }).then(function (d) {
        (d.sonuclar || []).forEach(function (s) { if (!s.ok) alert(s.hata); });
        done && done(d);
      }).catch(function () { alert(T('Yükleme başarısız.')); });
  }
  document.addEventListener('click', function (e) {
    var ac = e.target.closest('[data-medya-ac]'), tem = e.target.closest('[data-medya-temizle]');
    var kutu = (ac || tem) && (ac || tem).closest('[data-medya-sec]');
    if (!kutu) return;
    var inp = kutu.querySelector('input[type=hidden]'), on = kutu.querySelector('.medya-onizleme');
    if (tem) { inp.value = ''; on.innerHTML = T('<span class="bos-medya">Seçilmedi</span>'); degisti = true; return; }
    medyaAc(function (m) {
      inp.value = m.dosya; degisti = true;
      on.innerHTML = /^image\//.test(m.mime) ? '<img alt="">' : '<span>📎 ' + m.orijinal.replace(/</g, '&lt;') + '</span>';
      var im = on.querySelector('img'); if (im) im.src = m.url;
    }, kutu.dataset.tur);
  });

  /* Medya sayfası: sürükle-bırak, alt metin, bağlantı kopyala */
  var alan = $('[data-surukle-birak]');
  if (alan) {
    ['dragenter', 'dragover'].forEach(function (ev) { alan.addEventListener(ev, function (e) { e.preventDefault(); alan.classList.add('uzerinde'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { alan.addEventListener(ev, function (e) { e.preventDefault(); alan.classList.remove('uzerinde'); }); });
    alan.addEventListener('drop', function (e) { dosyaGonder(e.dataTransfer.files, function () { location.reload(); }); });
  }
  $$('[data-alt-kaydet]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var fd = new FormData(); fd.append('_csrf', csrf); fd.append('eylem', 'alt'); fd.append('id', inp.dataset.altKaydet); fd.append('alt', inp.value);
      fetch(taban + 'yonetim/?s=medya&json=1', { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function () { inp.style.borderColor = 'var(--yesil)'; });
    });
  });
  document.addEventListener('click', function (e) {
    var k = e.target.closest('[data-kopyala]'); if (!k) return;
    navigator.clipboard.writeText(k.dataset.kopyala).then(function () { var t = k.textContent; k.textContent = T('Kopyalandı ✓'); setTimeout(function () { k.textContent = t; }, 1500); });
  });

  /* Tekrarlanan gruplar */
  var sayac = Date.now();
  document.addEventListener('click', function (e) {
    var ekle = e.target.closest('[data-tekrar-ekle]');
    if (ekle) {
      var kutu = ekle.closest('[data-tekrar]'), tpl = kutu.querySelector(':scope > template');
      var html = tpl.innerHTML.replace(/__i__/g, 'y' + (sayac++));
      var div = document.createElement('div'); div.innerHTML = html;
      var satir = div.firstElementChild; kutu.querySelector('.tekrar-satirlar').appendChild(satir);
      editorBaslat(satir); var ilk = satir.querySelector('input:not([type=hidden]),textarea'); ilk && ilk.focus();
      degisti = true; return;
    }
    var s = e.target.closest('.tekrar-satir'); if (!s) return;
    if (e.target.closest('[data-tekrar-sil]')) { if (confirm(T('Bu satır silinsin mi?'))) { s.remove(); degisti = true; } }
    else if (e.target.closest('[data-tekrar-yukari]') && s.previousElementSibling) { s.parentNode.insertBefore(s, s.previousElementSibling); degisti = true; }
    else if (e.target.closest('[data-tekrar-asagi]') && s.nextElementSibling) { s.parentNode.insertBefore(s.nextElementSibling, s); degisti = true; }
  });
  function editorBaslat(kok) {
    $$('[data-editor]', kok).forEach(function (ed) {
      var alanE = ed.querySelector('.editor-alan'), ta = ed.querySelector('textarea[name]');
      alanE.addEventListener('input', function () { ta.value = alanE.innerHTML; });
    });
  }

  /* Bildirimleri kendiliğinden kapat */
  setTimeout(function () { $$('.bildirim.basari').forEach(function (b) { b.style.transition = 'opacity .4s'; b.style.opacity = '0'; setTimeout(function () { b.remove(); }, 400); }); }, 4000);

  /* ---------------------------------------------------------- YZ, bloklar, SEO analizi, otomatik taslak */

  /* Ayarlar sekmesi kaydedildikten sonra aynı sekmeye dön */
  $$('[data-sekmeler] .sekmeler a').forEach(function (a) {
    a.addEventListener('click', function () { var h = $('[data-sekme-alani]'); if (h) h.value = a.getAttribute('href').slice(1); });
  });
  if (location.hash && $('[data-sekme-alani]')) $('[data-sekme-alani]').value = location.hash.slice(1);

  /* Blok düzenleyici: türe göre blok ekle */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-blok-ekle]'); if (!b) return;
    var kutu = b.closest('[data-tekrar]'), tpl = kutu.querySelector('template[data-blok="' + b.dataset.blokEkle + '"]');
    var div = document.createElement('div'); div.innerHTML = tpl.innerHTML.replace(/__i__/g, 'b' + (sayac++));
    var satir = div.firstElementChild; kutu.querySelector('.tekrar-satirlar').appendChild(satir);
    editorBaslat(satir); degisti = true;
    var ilk = satir.querySelector('input:not([type=hidden]),textarea'); ilk && ilk.focus();
  });

  /* Kelime sayacı */
  function kelimeSay(ed) {
    var alanE = ed.querySelector('.editor-alan'), s = ed.querySelector('[data-kelime]'); if (!s || !alanE) return;
    var t = (alanE.innerText || '').trim(), n = t ? t.split(/\s+/).length : 0;
    s.textContent = n + T(' kelime · ') + Math.max(1, Math.ceil(n / 200)) + T(' dk');
  }
  $$('[data-editor]').forEach(function (ed) { kelimeSay(ed); ed.addEventListener('input', function () { kelimeSay(ed); }); });

  /* Yapay zekâ yardımcıları */
  var yzAcik = document.body.dataset.yz === '1', yzUrl = document.body.dataset.yzUrl;
  function yz(gorev, metin, ekstra) {
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('gorev', gorev); fd.append('metin', metin);
    Object.keys(ekstra || {}).forEach(function (k) { fd.append(k, ekstra[k]); });
    return fetch(yzUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) throw new Error(j.hata || T('YZ hatası')); return j.sonuc; });
  }
  function yzUyari() { alert(T('Yapay zekâ ayarlanmamış. Ayarlar › Yapay Zekâ bölümünden API anahtarı ekleyin.')); }
  var yzModal = $('#yz-modal'), yzHedef = null, yzSon = '';
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.editor-arac [data-k="yz"]'); if (!b) return;
    e.preventDefault(); e.stopImmediatePropagation();
    if (!yzAcik) return yzUyari();
    yzHedef = b.closest('[data-editor]'); yzModal.hidden = false;
    $('[data-yz-sonuc]', yzModal).hidden = true; $('[data-yz-eylemler]', yzModal).hidden = true;
  }, true);
  if (yzModal) {
    yzModal.addEventListener('click', function (e) {
      if (e.target === yzModal || e.target.closest('[data-modal-kapat]')) { yzModal.hidden = true; return; }
      var g = e.target.closest('[data-yz-gorev]'), alanE = yzHedef && yzHedef.querySelector('.editor-alan'), sonuc = $('[data-yz-sonuc]', yzModal);
      if (g && alanE) {
        var sel = window.getSelection && String(window.getSelection()), metin = alanE.innerHTML;
        sonuc.hidden = false; sonuc.textContent = T('Düşünüyorum…'); $('[data-yz-eylemler]', yzModal).hidden = true;
        yz(g.dataset.yzGorev, metin, { komut: $('[data-yz-komut]', yzModal).value, dil: ($('form[data-dil]') || {}).dataset ? $('form[data-dil]').dataset.dil : '' })
          .then(function (r) {
            yzSon = Array.isArray(r && r.basliklar) ? r.basliklar.join('\n') : String(r);
            sonuc.textContent = yzSon.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 4000);
            $('[data-yz-eylemler]', yzModal).hidden = false;
          }).catch(function (err) { sonuc.textContent = '⚠ ' + err.message; });
      }
      var guvenli = function (t) { return /<[a-z]/i.test(t) ? bzTemizle(t) : '<p>' + t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n+/g, '</p><p>') + '</p>'; };
      if (e.target.closest('[data-yz-uygula]') && alanE) { alanE.innerHTML = guvenli(yzSon); alanE.dispatchEvent(new Event('input', { bubbles: true })); yzModal.hidden = true; }
      if (e.target.closest('[data-yz-ekle]') && alanE) { alanE.insertAdjacentHTML('beforeend', guvenli(yzSon)); alanE.dispatchEvent(new Event('input', { bubbles: true })); yzModal.hidden = true; }
    });
  }
  /* SEO başlık + açıklama üret */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-yz-seo]'); if (!b) return;
    if (!yzAcik) return yzUyari();
    var f = b.closest('form'), metin = (($('#baslik', f) || {}).value || '') + '\n\n' + $$('.editor-alan', f).map(function (x) { return x.innerText; }).join('\n') + '\n' + $$('textarea[name^="alan["]', f).map(function (x) { return x.value; }).join('\n');
    b.disabled = true; var t = b.textContent; b.textContent = T('Üretiliyor…');
    yz('seo', metin, { dil: f.dataset.dil || '' }).then(function (r) {
      var sb = $('[data-serp-kaynak=baslik]', f), sa = $('[data-serp-kaynak=aciklama]', f);
      if (r.baslik) { sb.value = r.baslik; sb.dispatchEvent(new Event('input', { bubbles: true })); }
      if (r.aciklama) { sa.value = r.aciklama; sa.dispatchEvent(new Event('input', { bubbles: true })); }
    }).catch(function (err) { alert(err.message); }).finally(function () { b.disabled = false; b.textContent = t; });
  });
  /* Tüm alanları çevir (çeviri oluştururken) */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-yz-cevir]'); if (!b) return;
    if (!yzAcik) return yzUyari();
    if (!confirm(T('Metin alanları yapay zekâ ile çevrilecek. Sonucu kaydetmeden önce gözden geçirin. Devam edilsin mi?'))) return;
    var f = b.closest('form'), dil = b.dataset.yzCevir, isler = [];
    var hedefler = $$('input[data-cevrilebilir], textarea[data-cevrilebilir], .alan-metin input[type=text], .alan-uzunmetin textarea, .alan-markdown textarea', f).filter(function (x) { return x.value.trim().length > 1; });
    var editorler = $$('.editor-alan', f).filter(function (x) { return x.innerText.trim().length > 1; });
    b.disabled = true; var t = b.textContent; var toplam = hedefler.length + editorler.length, biten = 0;
    function ilerle() { biten++; b.textContent = T('Çevriliyor… ') + biten + '/' + toplam; }
    hedefler.forEach(function (x) { isler.push(yz('cevir', x.value, { dil: dil }).then(function (r) { x.value = String(r).trim(); x.dispatchEvent(new Event('input', { bubbles: true })); ilerle(); })); });
    editorler.forEach(function (x) { isler.push(yz('cevir', x.innerHTML, { dil: dil }).then(function (r) { x.innerHTML = bzTemizle(String(r)); x.dispatchEvent(new Event('input', { bubbles: true })); ilerle(); })); });
    var sh = $('[data-slug-hedef]', f); if (sh) sh.value = '';
    Promise.allSettled(isler).then(function (rs) {
      b.disabled = false; b.textContent = t; degisti = true;
      var hata = rs.filter(function (r) { return r.status === 'rejected'; });
      alert(hata.length ? hata.length + T(' alan çevrilemedi: ') + hata[0].reason.message : T('Çeviri tamamlandı. Gözden geçirip kaydedin.'));
    });
  });
  /* Görsel için alt metin */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-yz-alt]'); if (!b) return;
    if (!yzAcik) return yzUyari();
    var inp = b.parentNode.querySelector('[data-alt-kaydet]'); b.textContent = '…';
    yz('alt', b.dataset.yzAlt).then(function (r) { inp.value = String(r).replace(/^["']|["']$/g, ''); inp.dispatchEvent(new Event('change')); })
      .catch(function (err) { alert(err.message); }).finally(function () { b.textContent = '✨'; });
  });

  /* Canlı SEO analizi (Yoast benzeri, Türkçe) */
  var seoListe = $('[data-seo-liste]');
  if (seoListe) {
    var f = seoListe.closest('form');
    var trLower = function (s) { return (s || '').replace(/I/g, 'ı').replace(/İ/g, 'i').toLowerCase(); };
    var analiz = function () {
      var baslikEl = $('#baslik', f), seoB = $('[data-serp-kaynak=baslik]', f).value, seoA = $('[data-serp-kaynak=aciklama]', f).value;
      var baslik = seoB || (baslikEl ? baslikEl.value : ''), kw = trLower($('[data-seo-anahtar]', f).value.trim());
      var html = $$('.editor-alan', f).map(function (x) { return x.innerHTML; }).join(' ') + ' ' + $$('textarea[name^="alan["]', f).map(function (x) { return x.value; }).join(' ');
      var tmp = new DOMParser().parseFromString(html, 'text/html').body; var metin = tmp.innerText || '', kelime = metin.trim() ? metin.trim().split(/\s+/).length : 0;
      var slug = ($('[data-slug-hedef]', f) || {}).value || '';
      var k = [];
      k.push([baslik.length >= 30 && baslik.length <= 60, T('Başlık uzunluğu ') + baslik.length + T(' karakter (ideal 30–60)')]);
      k.push([seoA.length >= 120 && seoA.length <= 160, seoA ? T('Meta açıklama ') + seoA.length + T(' karakter (ideal 120–160)') : T('Meta açıklama yazılmamış')]);
      k.push([kelime >= 300, T('İçerik ') + kelime + T(' kelime (en az 300 önerilir)')]);
      k.push([tmp.querySelectorAll('h2,h3').length > 0, T('Alt başlık (H2/H3) kullanımı')]);
      var imgs = tmp.querySelectorAll('img'), altsiz = Array.prototype.filter.call(imgs, function (i) { return !i.getAttribute('alt'); }).length;
      k.push([altsiz === 0, imgs.length ? (altsiz ? altsiz + T(' görselde alt metin eksik') : T('Tüm görsellerde alt metin var')) : T('İçerikte görsel yok (önerilir)')]);
      k.push([tmp.querySelectorAll('a[href^="/"]').length > 0, T('İç bağlantı')]);
      if (kw) {
        var yogunluk = kelime ? (trLower(metin).split(kw).length - 1) / kelime * 100 : 0;
        k.push([trLower(baslik).indexOf(kw) > -1, T('Anahtar kelime başlıkta')]);
        k.push([trLower(seoA).indexOf(kw) > -1, T('Anahtar kelime meta açıklamada')]);
        k.push([trLower(metin.slice(0, 600)).indexOf(kw) > -1, T('Anahtar kelime ilk paragrafta')]);
        k.push([slug.indexOf(slugla(kw)) > -1 || (!slug && slugla(baslik).indexOf(slugla(kw)) > -1), T('Anahtar kelime adreste')]);
        k.push([yogunluk >= 0.5 && yogunluk <= 3, T('Anahtar kelime yoğunluğu %') + yogunluk.toFixed(1) + T(' (ideal %0,5–3)')]);
      }
      var iyi = k.filter(function (x) { return x[0]; }).length, puan = Math.round(iyi / k.length * 100);
      seoListe.innerHTML = k.map(function (x) { return '<li class="' + (x[0] ? 'iyi' : 'kotu') + '">' + (x[0] ? '●' : '○') + ' ' + x[1].replace(/</g, '&lt;') + '</li>'; }).join('');
      var p = $('[data-seo-puan]'); if (p) { p.textContent = puan; p.className = 'seo-puan ' + (puan >= 75 ? 'iyi' : puan >= 50 ? 'orta' : 'kotu'); }
    };
    var slugla = function (x) { return slug(x); };
    var zaman2; f.addEventListener('input', function () { clearTimeout(zaman2); zaman2 = setTimeout(analiz, 400); });
    analiz();
  }

  /* Otomatik yerel taslak (tarayıcı çökse de yazılanlar kaybolmaz) */
  var oto = $('form[data-otomatik-kayit]');
  if (oto && window.localStorage) {
    var anahtar = 'bz_taslak:' + oto.dataset.otomatikKayit, uyari = $('[data-otomatik-kayit-uyari]');
    var topla = function () {
      senkronize(); var d = {};
      $$('input[name], textarea[name], select[name]', oto).forEach(function (x) { if (x.name === '_csrf' || x.type === 'file') return; if ((x.type === 'checkbox' || x.type === 'radio') && !x.checked) return; d[x.name] = x.value; });
      return d;
    };
    try {
      var kayitli = JSON.parse(localStorage.getItem(anahtar) || 'null');
      if (kayitli && kayitli.t > Date.now() - 7 * 864e5 && uyari) {
        uyari.hidden = false; uyari.querySelector('span').textContent = new Date(kayitli.t).toLocaleString(document.body.dataset.panelDil === 'en' ? 'en-GB' : 'tr-TR');
        uyari.addEventListener('click', function (e) {
          if (e.target.closest('[data-otomatik-sil]')) { localStorage.removeItem(anahtar); uyari.hidden = true; }
          if (e.target.closest('[data-otomatik-geri]')) {
            Object.keys(kayitli.d).forEach(function (n) {
              var el = oto.querySelector('[name="' + n.replace(/"/g, '') + '"]'); if (!el) return;
              if (el.type === 'checkbox') el.checked = true; else el.value = kayitli.d[n];
              var ed = el.closest('[data-editor]'); if (ed) ed.querySelector('.editor-alan').innerHTML = bzTemizle(kayitli.d[n]);
            });
            uyari.hidden = true; degisti = true;
          }
        });
      }
    } catch (err) { }
    var zaman3; oto.addEventListener('input', function () { clearTimeout(zaman3); zaman3 = setTimeout(function () { try { localStorage.setItem(anahtar, JSON.stringify({ t: Date.now(), d: topla() })); } catch (err) { } }, 1500); });
    oto.addEventListener('submit', function () { localStorage.removeItem(anahtar); });
  }

  /* 404 → yönlendirme formunu doldur */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-yonlendir]'); if (!b) return;
    var f = $('#yonlendirme-formu'); f.kaynak.value = b.dataset.yonlendir; f.hedef.focus(); f.scrollIntoView({ behavior: 'smooth' });
  });
})();
