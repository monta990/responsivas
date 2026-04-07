<?php

use Glpi\Event;
use Glpi\Plugin\Hooks;

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * Inicialización del plugin
 */
include_once __DIR__ . '/inc/paths.class.php';
    
function plugin_init_responsivas() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['responsivas'] = true;
    $PLUGIN_HOOKS['config_page']['responsivas'] = 'front/config.form.php';

    Plugin::registerClass(
        'PluginResponsivasUser',
        [
            'addtabon' => ['User']
        ]
    );
}

/**
 * Información del plugin
 */
function plugin_version_responsivas() {
   return [
      'name'          => 'Responsivas',
      'version'       => '1.3.2',
      'author'        => 'Edwin Elias Alvarez',
      'license'       => 'GPLv2+',
      'homepage'      => 'https://sontechs.com',
      'minphpversion' => '8.2',
      'requirements'  => [
         'glpi' => [
            'min' => '11.0',
            'max' => '11.99',
         ],
      ],
   ];
}

/**
 * Verifica prerrequisitos antes de activar el plugin.
 * GLPI llama esta función automáticamente.
 */
function plugin_responsivas_check() {

   // PHP minimum 8.2
   if (version_compare(PHP_VERSION, '8.2', '<')) {
      echo '<div class="alert alert-danger">'
         . sprintf(__('Responsivas requires PHP 8.2 or higher. Current version: %s', 'responsivas'), PHP_VERSION)
         . '</div>';
      return false;
   }

   // TCPDF debe estar disponible (lo incluye GLPI en vendor)
   if (!class_exists('TCPDF') && !file_exists(GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
      echo '<div class="alert alert-danger">'
         . __('Responsivas requires TCPDF, which must be available in the GLPI vendor directory.', 'responsivas')
         . '</div>';
      return false;
   }

   return true;
}

/**
 * Versión del schema de configuración.
 * Incrementar cuando se agregue, renombre o elimine cualquier campo.
 * La función plugin_responsivas_migrateConfig() se encarga de aplicar
 * los cambios necesarios basándose en esta versión.
 */
define('PLUGIN_RESPONSIVAS_SCHEMA_VERSION', '1');

/**
 * Definición completa del schema de configuración del plugin.
 *
 * Cada campo incluye:
 *   - 'default'  : valor por defecto
 *   - 'type'     : 'string' | 'int' | 'bool' | 'text'
 *   - 'since'    : versión del schema donde se introdujo el campo
 *   - 'group'    : agrupación lógica para migraciones selectivas
 *   - 'migrate'  : 'reset' = sobrescribir si schema bumped | 'keep' = nunca sobrescribir
 *
 * Para agregar un campo nuevo:
 *   1. Añadirlo aquí con 'since' => siguiente versión
 *   2. Incrementar PLUGIN_RESPONSIVAS_SCHEMA_VERSION
 *   3. Los campos con 'migrate'=>'reset' se restauran al default en la migración
 *   4. Los campos con 'migrate'=>'keep' solo se insertan si no existen (nunca se pisan)
 */
function plugin_responsivas_getSchemaFields(): array {
   $pc_intro_default   = "Se hace constar que el equipo de cómputo con número de activo **{activo}** es propiedad de **{empresa}** y ha sido asignada a **{nombre}** para el desempeño de sus funciones laborales con las siguientes características:";
   $pc_cuerpo_default  = "Por lo cual si suceden ciertos eventos como los indicados a continuación son responsabilidad de pagar y solucionar a la persona a quien está siendo asignada:\n1. Se le derrame algún líquido sobre el equipo y que deje de funcionar o en su caso si algún componente, el reemplazo de este corre por cuenta del usuario.\n2. Que por algún golpe se dañe la pantalla, bisagras o parte del equipo por uso fuera de lo cotidiano, es responsabilidad del usuario pagar o reemplazar el equipo por otro similar al asignado.\n3. Que le sea sustraído de su vehículo o casa.\n4. Los anexos al equipo cargador, mochila y mouse deben regresarse en condiciones de uso.\n\nCuando se goce de periodo vacacional, se deberá de dejar el equipo de cómputo en resguardo con su Líder inmediato. En caso de requerir hacer uso del equipo en dicho periodo, tendrá que ser autorizado por Dirección de micronegocio por escrito, notificando a Capital Humano, Director Administrativo y Líder de TI.\n\nQuedo como responsable de esta herramienta que se me otorga para ejecutar mis labores para las que fui contratado (no para uso personal ni prestarlo a terceras personas), por lo cual no se pueden agregar componentes y/o programas no autorizados, así mismo estarán sujetos a cualquier revisión que la administración decida llevar a cabo en el momento que sea necesario.";
   $pri_intro_default  = "Se hace constar que la impresora con número de activo **{activo}** es propiedad de **{empresa}** y ha sido asignada a **{nombre}** para el desempeño de sus funciones laborales con las siguientes características:";
   $pri_cuerpo_default = "El colaborador declara conocer y aceptar que el equipo asignado constituye un activo propiedad de la institución y se compromete a:\n1. Utilizar la impresora exclusivamente para fines laborales autorizados.\n2. Proteger el activo contra daño, pérdida, robo, uso indebido o acceso no autorizado.\n3. Cumplir con las políticas internas de seguridad de la información, uso aceptable de recursos tecnológicos y confidencialidad.\n4. Notificar de forma inmediata al área correspondiente cualquier incidente que comprometa la integridad, disponibilidad o funcionamiento del equipo.\n5. Conservar en buen estado los accesorios entregados (cableado, consumibles, componentes adicionales) y devolverlos junto con el equipo cuando sea requerido.\n\nEl incumplimiento de las responsabilidades aquí descritas podrá dar lugar a la aplicación de medidas administrativas conforme a los lineamientos internos de la organización y a la legislación aplicable.";
   $pho_apertura_default  = "En la localidad de {lugar}, a las {hora} horas del {fecha} comparecieron **{empresa}**, representado por el señor **{representante}**, mexicano, mayor de edad, con domicilio en {direccion}, C.P. {cp}, en {lugar}, a quien en lo sucesivo se le denominará como **\"el comodante\"**; y **{nombre}**, mayor de edad, a quien en lo sucesivo se le denominará como **\"el comodatario\"**; para formalizar el presente CONTRATO DE COMODATO, al tenor de las siguientes:";
   $pho_clausulas_default = "1. **\"El comodante\"** manifiesta ser dueño único y exclusivo, en legítima propiedad, del equipo de telefonía celular, Marca: **{marca}**, en la Condición de: **{estado}**, Modelo: **{modelo}**, Almacenamiento: **{almacenamiento}**, RAM: **{ram}**, Serie: **{serie_uuid}**, IMEI: **{imei}**, con número de línea celular **{linea}**, con un valor de **{precio} M.N.**\n2. **\"El comodante\"** manifiesta que el equipo anteriormente descrito deberá ser utilizado por **\"el comodatario\"** única y exclusivamente para la actividad comercial que desarrolla.\n3. Se establece la conformidad del **\"comodatario\"** con la transmisión gratuita del uso del bien, obligándose a conservarlo con la debida diligencia, siendo responsable de cualquier daño, deterioro o mal uso imputable a su culpa o negligencia, salvo el desgaste natural derivado de la vida útil mínima indicada en la cláusula décima tercera.\n4. Por ningún motivo podrá conceder el uso del bien o de la línea a terceros sin autorización expresa y por escrito del **\"comodante\"**, respondiendo el **\"comodatario\"** por la pérdida, daño o extravío del equipo aun cuando éstos deriven de caso fortuito, salvo lo previsto en la cláusula octava.\n5. **\"El comodatario\"** no podrá retener el bien bajo pretexto de adeudos, expensas o cualquier otro concepto.\n6. La entrega del bien se realiza en el domicilio del **\"comodante\"**, momento a partir del cual el **\"comodatario\"** asume la guarda y custodia del equipo, siendo responsable por los desperfectos no reportados oportunamente, salvo el desgaste natural derivado del uso normal del mismo.\n7. La duración del presente comodato será desde la fecha del contrato y hasta que **\"el comodante\"** solicite su devolución o concluya la vida útil del equipo, pudiendo **\"el comodante\"** darlo por terminado de manera anticipada en caso de incumplimiento a cualquiera de las obligaciones aquí establecidas, obligándose el **\"comodatario\"** a la devolución inmediata del bien.\n8. Si el bien quedara inservible por culpa del **\"comodatario\"**, éste deberá cubrir el valor proporcional restante conforme a la vida útil del equipo, en un plazo máximo de 30 días naturales. No aplicará en caso de robo con violencia debidamente denunciado ante la autoridad competente.\n9. En caso de extravío del equipo, **\"el comodatario\"** deberá cubrir el valor comercial total del bien o entregar otro de características equivalentes.\n10. Al término del contrato, **\"el comodatario\"** deberá devolver el bien en su forma individual, junto con todos sus accesorios.\n11. El equipo y la línea son para uso exclusivamente laboral, por lo que no deberán utilizarse para fines personales ni ajenos al trabajo; **\"el comodatario\"** deberá cuidar el equipo y la información que contiene, no compartirla sin autorización expresa y reportar de inmediato cualquier pérdida, robo o riesgo de seguridad.\n12. Se entrega el equipo con funda, vidrio templado, cable y cargador, los cuales deberán devolverse al final de la vida útil o, en su defecto, cubrir la cantidad de **\$400.00 M.N.** por concepto de reposición.\n13. {clausula_vida_util}";
   $pho_testigos_default  = "Comparecen como testigos {testigo1} y {testigo2}, vecinos de {lugar}, manifestando conocer a las partes, firmando en original y copia tras haber leído y comprendido el presente contrato, quedando un ejemplar para cada parte.";
   $pho_vida_util_factura_default = "Se establece como **vida útil** un periodo de 24 meses contados a partir del {fecha_compra}, con base en la factura {factura} emitida por {proveedor}.";
   $pho_vida_util_sin_default     = "Se establece como **vida útil** un periodo de 24 meses desde la fecha de asignación.";

   return [
      // ── General ──────────────────────────────────────── since v1, keep
      'timezone'             => ['default'=>date_default_timezone_get(), 'type'=>'string', 'since'=>'1', 'group'=>'general',   'migrate'=>'keep'],
      'show_employee_number' => ['default'=>1,                           'type'=>'bool',   'since'=>'1', 'group'=>'general',   'migrate'=>'keep'],
      'show_qr'              => ['default'=>1,                           'type'=>'bool',   'since'=>'1', 'group'=>'general',   'migrate'=>'keep'],
      'company_name'         => ['default'=>'Sontechs',                  'type'=>'string', 'since'=>'1', 'group'=>'general',   'migrate'=>'keep'],
      'currency'             => ['default'=>'$',                         'type'=>'string', 'since'=>'1', 'group'=>'general',   'migrate'=>'keep'],
      // ── Testigos / representante ──────────────────────── since v1, keep
      'testigo_1'            => ['default'=>2,                           'type'=>'int',    'since'=>'1', 'group'=>'witnesses', 'migrate'=>'keep'],
      'testigo_2'            => ['default'=>3,                           'type'=>'int',    'since'=>'1', 'group'=>'witnesses', 'migrate'=>'keep'],
      'representante'        => ['default'=>3,                           'type'=>'int',    'since'=>'1', 'group'=>'witnesses', 'migrate'=>'keep'],
      'cellphone_type_id'    => ['default'=>'',                          'type'=>'int',    'since'=>'1', 'group'=>'witnesses', 'migrate'=>'keep'],
      // ── Footer PC ────────────────────────────────────── since v1, keep
      'pc_footer_left_1'     => ['default'=>'Original: Empresa',         'type'=>'string', 'since'=>'1', 'group'=>'footer_pc', 'migrate'=>'keep'],
      'pc_footer_right_1'    => ['default'=>'Copia: Colaborador',        'type'=>'string', 'since'=>'1', 'group'=>'footer_pc', 'migrate'=>'keep'],
      'pc_footer_left_2'     => ['default'=>'SIS-RESP-001',              'type'=>'string', 'since'=>'1', 'group'=>'footer_pc', 'migrate'=>'keep'],
      'pc_footer_right_2'    => ['default'=>'Rev 1.4 08/01/2026',        'type'=>'string', 'since'=>'1', 'group'=>'footer_pc', 'migrate'=>'keep'],
      'pc_font_size'         => ['default'=>10,                          'type'=>'int',    'since'=>'1', 'group'=>'footer_pc', 'migrate'=>'keep'],
      // ── Footer Impresoras ─────────────────────────────── since v1, keep
      'pri_footer_left_1'    => ['default'=>'Original: Empresa',         'type'=>'string', 'since'=>'1', 'group'=>'footer_pri','migrate'=>'keep'],
      'pri_footer_right_1'   => ['default'=>'Copia: Colaborador',        'type'=>'string', 'since'=>'1', 'group'=>'footer_pri','migrate'=>'keep'],
      'pri_footer_left_2'    => ['default'=>'SIS-RESP-003',              'type'=>'string', 'since'=>'1', 'group'=>'footer_pri','migrate'=>'keep'],
      'pri_footer_right_2'   => ['default'=>'Rev 1.4 08/01/2026',        'type'=>'string', 'since'=>'1', 'group'=>'footer_pri','migrate'=>'keep'],
      'pri_font_size'        => ['default'=>10,                          'type'=>'int',    'since'=>'1', 'group'=>'footer_pri','migrate'=>'keep'],
      // ── Footer Teléfonos ──────────────────────────────── since v1, keep
      'pho_footer_left_1'    => ['default'=>'Original: Empresa',         'type'=>'string', 'since'=>'1', 'group'=>'footer_pho','migrate'=>'keep'],
      'pho_footer_right_1'   => ['default'=>'Copia: Colaborador',        'type'=>'string', 'since'=>'1', 'group'=>'footer_pho','migrate'=>'keep'],
      'pho_footer_left_2'    => ['default'=>'SIS-RESP-002',              'type'=>'string', 'since'=>'1', 'group'=>'footer_pho','migrate'=>'keep'],
      'pho_footer_right_2'   => ['default'=>'Rev 1.4 08/01/2026',        'type'=>'string', 'since'=>'1', 'group'=>'footer_pho','migrate'=>'keep'],
      'pho_font_size'        => ['default'=>9,                           'type'=>'int',    'since'=>'1', 'group'=>'footer_pho','migrate'=>'keep'],
      // ── Correo ────────────────────────────────────────── since v1, keep
      'email_subject'        => ['default'=>'Responsivas de activos asignados', 'type'=>'string', 'since'=>'1', 'group'=>'email', 'migrate'=>'keep'],
      'email_body'           => ['default'=>"Estimado colaborador,\n\nSe adjuntan sus responsivas y contratos de comodato de los activos asignados a su nombre.\n\nPor favor conserve este documento para cualquier aclaración futura.", 'type'=>'text', 'since'=>'1', 'group'=>'email', 'migrate'=>'keep'],
      'email_footer'         => ['default'=>'Este correo fue generado automáticamente por el sistema de responsivas. No responda a este mensaje.', 'type'=>'text', 'since'=>'1', 'group'=>'email', 'migrate'=>'keep'],
      // ── Plantillas de documento ───────────────────────── since v1, reset on schema bump
      'pc_titulo'            => ['default'=>'CARTA RESPONSIVA DE ACTIVO ASIGNADO', 'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pc_intro'             => ['default'=>$pc_intro_default,   'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pc_cuerpo'            => ['default'=>$pc_cuerpo_default,  'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pri_titulo'           => ['default'=>'CARTA RESPONSIVA DE ACTIVO ASIGNADO', 'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pri_intro'            => ['default'=>$pri_intro_default,  'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pri_cuerpo'           => ['default'=>$pri_cuerpo_default, 'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pho_titulo'           => ['default'=>'CONTRATO DE COMODATO', 'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pho_apertura'         => ['default'=>$pho_apertura_default,  'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pho_clausulas'        => ['default'=>$pho_clausulas_default, 'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pho_testigos'         => ['default'=>$pho_testigos_default,  'type'=>'text', 'since'=>'1', 'group'=>'template', 'migrate'=>'reset'],
      'pho_vida_util_factura' => ['default'=>$pho_vida_util_factura_default, 'type'=>'text', 'since'=>'1.2.6', 'group'=>'template', 'migrate'=>'keep'],
      'pho_vida_util_sin'     => ['default'=>$pho_vida_util_sin_default,     'type'=>'text', 'since'=>'1.2.6', 'group'=>'template', 'migrate'=>'keep'],
      // ── Schema version ────────────────────────────────── internal, always reset
      'config_schema_version'=> ['default'=>PLUGIN_RESPONSIVAS_SCHEMA_VERSION, 'type'=>'string', 'since'=>'1', 'group'=>'internal', 'migrate'=>'reset'],
   ];
}


/**
 * Valores por defecto del plugin.
 * Wrapper de compatibilidad — extrae los 'default' del schema.
 */
function plugin_responsivas_getDefaults(): array {
   return array_map(fn($f) => $f['default'], plugin_responsivas_getSchemaFields());
}

/**
 * Instalación
 */
function plugin_responsivas_install() {

   $files_dir = PluginResponsivasPaths::filesDir();
   $logo_path = PluginResponsivasPaths::logoPath();

   // Logo base incluido en el plugin
   $default_logo = __DIR__ . '/logo.png';

   // Crear directorio files del plugin
   if (!is_dir($files_dir)) {
      if (!mkdir($files_dir, 0755, true)) {

         if (class_exists('Session')) {
            Session::addMessageAfterRedirect(
               __('Could not create the Responsivas plugin files directory. Check permissions.', 'responsivas'),
               false,
               ERROR
            );
         }

         Event::log(
            0,
            'plugin_responsivas',
            3,
            'plugin',
            "No se pudo crear el directorio: $files_dir"
         );

         return false;
      }
   }

   // Copiar logo por defecto SOLO si no existe uno
   if (!is_file($logo_path) && is_readable($default_logo)) {
      copy($default_logo, $logo_path);
      chmod($logo_path, 0644);
   }

   // =============================
   // Inicialización segura de configuración
   // =============================
   $existing = Config::getConfigurationValues('plugin_responsivas') ?? [];
   plugin_responsivas_migrateConfig($existing);

   return true;
}


/**
 * Migración de configuración basada en schema versionado.
 *
 * Lógica:
 *  - Campos con migrate='reset': se restauran al default cuando config_schema_version
 *    es inferior a PLUGIN_RESPONSIVAS_SCHEMA_VERSION (o no existe).
 *  - Campos con migrate='keep': solo se insertan si no existen aún en BD.
 *
 * Para lanzar una migración en la próxima versión del plugin:
 *  1. Modifica el campo en getSchemaFields() (cambia default, type, etc.)
 *  2. Incrementa PLUGIN_RESPONSIVAS_SCHEMA_VERSION
 *  3. Esta función hará el resto automáticamente.
 */
function plugin_responsivas_migrateConfig(array $existing): void {
   $schema   = plugin_responsivas_getSchemaFields();
   $defaults = plugin_responsivas_getDefaults();
   $stored_schema_version = $existing['config_schema_version'] ?? '0';

   $to_set = [];

   if (version_compare($stored_schema_version, PLUGIN_RESPONSIVAS_SCHEMA_VERSION, '<')) {
      // Schema bumped — resetear todos los campos marcados como 'reset'
      foreach ($schema as $key => $meta) {
         if ($meta['migrate'] === 'reset') {
            $to_set[$key] = $defaults[$key];
         }
      }
   }

   // Insertar campos que no existen aún (migrate='keep' o nuevos desde 'since')
   foreach ($schema as $key => $meta) {
      if (!isset($existing[$key]) && !isset($to_set[$key])) {
         $to_set[$key] = $defaults[$key];
      }
   }

   if (!empty($to_set)) {
      Config::setConfigurationValues('plugin_responsivas', $to_set);
   }
}


/**
 * Actualización: aplica nuevos defaults sin sobrescribir valores existentes
 */
function plugin_responsivas_update($current, $new) {
   $existing = Config::getConfigurationValues('plugin_responsivas') ?? [];
   plugin_responsivas_migrateConfig($existing);
   return true;
}
