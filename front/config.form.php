<?php
if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}
require_once dirname(__DIR__) . '/inc/paths.class.php';
require_once dirname(__DIR__) . '/inc/config.class.php';
global $CFG_GLPI;
$self = $CFG_GLPI['root_doc'] . '/plugins/responsivas/front/config.form.php';
?>
<script>
// Tracks the last textarea/input that had focus
window._respLastField = null;
document.addEventListener('focusin', function (e) {
   if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT')) {
      window._respLastField = e.target;
   }
});
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
</script>
<?php