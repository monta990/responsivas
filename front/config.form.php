<?php
if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}
require_once dirname(__DIR__) . '/inc/paths.class.php';
require_once dirname(__DIR__) . '/inc/config.class.php';
global $CFG_GLPI;
$self = $CFG_GLPI['root_doc'] . '/plugins/responsivas/front/config.form.php';
?>
<style>
/* ── Dark-mode text fixes ─────────────────────────────────────────────
   form-text / text-muted usan colores que en algunos temas GLPI
   quedan con contraste muy bajo. Heredamos del color del cuerpo. */
.form-text {
   color: var(--bs-secondary-color, var(--bs-body-color));
   opacity: 0.80;
}
.resp-fmt-btn { padding: 1px 7px; font-size: 0.82em; line-height: 1.4; }
</style>
<script>
// ── Último campo con foco ─────────────────────────────────────────────
window._respLastField = null;
document.addEventListener('focusin', function (e) {
   if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT')) {
      window._respLastField = e.target;
   }
});

// ── Insertar variable en cursor ───────────────────────────────────────
window.responsivasInsertVar = function (tag) {
   var a = window._respLastField;
   if (a && a.isConnected) {
      var s = a.selectionStart !== undefined ? a.selectionStart : a.value.length;
      var e = a.selectionEnd   !== undefined ? a.selectionEnd   : a.value.length;
      a.value = a.value.substring(0, s) + tag + a.value.substring(e);
      a.selectionStart = a.selectionEnd = s + tag.length;
      a.focus();
      a.dispatchEvent(new Event('input', { bubbles: true }));
   } else {
      navigator.clipboard.writeText(tag).catch(function () {});
   }
};

// ── Botones de formato B / I / U (toggle) ────────────────────────────
document.addEventListener('click', function (e) {
   var btn = e.target.closest('.resp-fmt-btn');
   if (!btn) return;
   var wrap = btn.dataset.wrap;
   var wLen = wrap.length;
   var ae = document.activeElement;
   var ta = (ae && (ae.tagName === 'TEXTAREA' || ae.tagName === 'INPUT'))
      ? ae
      : window._respLastField;
   if (!ta) return;
   ta.focus();

   var start = ta.selectionStart;
   var end   = ta.selectionEnd;
   var sel    = ta.value.substring(start, end);
   var before = ta.value.substring(start - wLen, start);
   var after  = ta.value.substring(end, end + wLen);

   // Toggle: quitar si ya existe dentro o fuera de la selección
   var wrappedInside  = sel.startsWith(wrap) && sel.endsWith(wrap) && sel.length >= wLen * 2 + 1;
   var wrappedOutside = before === wrap && after === wrap;

   var newStart, newEnd;
   if (wrappedInside) {
      var inner = sel.slice(wLen, sel.length - wLen);
      ta.setRangeText(inner, start, end, 'preserve');
      newStart = start;
      newEnd   = start + inner.length;
   } else if (wrappedOutside) {
      ta.setRangeText(sel, start - wLen, end + wLen, 'preserve');
      newStart = start - wLen;
      newEnd   = newStart + sel.length;
   } else {
      var placeholder = sel || 'texto';
      ta.setRangeText(wrap + placeholder + wrap, start, end, 'preserve');
      newStart = start + wLen;
      newEnd   = newStart + placeholder.length;
   }
   ta.setSelectionRange(newStart, newEnd);
   ta.dispatchEvent(new Event('input', { bubbles: true }));
});
</script>
<?php