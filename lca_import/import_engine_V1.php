<?php
declare(strict_types=1);
// Guard contra doble carga
if (defined('CREEA_IMPORT_ENGINE')) { return; }
define('CREEA_IMPORT_ENGINE', 1);

/* ========================= Helpers base ========================= */

if (!function_exists('creea_norm_uuid')) {
  function creea_norm_uuid(?string $v): ?string {
    if ($v === null) return null;
    $v = trim((string)$v);
    if ($v === '' || strcasecmp($v,'null')===0) return null;
    return strtolower($v);
  }
}
if (!function_exists('creea_provider_exists')) {
  function creea_provider_exists(PDO $pdo, string $uuid): bool {
    $s = $pdo->prepare("SELECT 1 FROM processes WHERE uuid=:u LIMIT 1");
    $s->execute([':u'=>$uuid]);
    return (bool)$s->fetchColumn();
  }
}
if (!function_exists('creea_find_provider_by_flow')) {
  function creea_find_provider_by_flow(PDO $pdo, string $flowUuid, ?string $locationUuid): ?string {
    $sql = "SELECT po.process_uuid
            FROM process_outputs po
            JOIN processes p ON p.uuid=po.process_uuid
            WHERE po.flow_uuid=:f AND po.is_reference=1 AND (:loc IS NULL OR p.location_uuid=:loc)
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':f'=>$flowUuid, ':loc'=>$locationUuid]);
    $x = $st->fetchColumn();
    if ($x) return (string)$x;

    $st = $pdo->prepare("SELECT po.process_uuid
                         FROM process_outputs po
                         WHERE po.flow_uuid=:f AND po.is_reference=1
                         LIMIT 1");
    $st->execute([':f'=>$flowUuid]);
    $x = $st->fetchColumn();
    return $x ? (string)$x : null;
  }
}
if (!function_exists('creea_resolve_provider')) {
  function creea_resolve_provider(PDO $pdo, ?string $provUuid, string $flowUuid, ?string $procLocationUuid): ?string {
    $provUuid = creea_norm_uuid($provUuid);
    if ($provUuid && creea_provider_exists($pdo, $provUuid)) return $provUuid;
    $cand = creea_find_provider_by_flow($pdo, $flowUuid, $procLocationUuid);
    return $cand ?: null;
  }
}


if (!function_exists('creea_exec_with_unit_fallback')) {
  /**
   * Ejecuta un statement. Si falla por SQLSTATE 45000 (trigger), asume mismatch FP↔unit_group:
   * - Si la FP no tiene unit_group, intenta "curarla" usando la unidad que viene en params.
   * - Cambia la unidad por la unidad de referencia del unit_group de la FP y reintenta 1 vez.
   * - Si no hay unidad de referencia, registra y omite.
   *
   * @param int $unitIdx Índice 0-based de unit_uuid en $params
   * @param int $fpIdx   Índice 0-based de flow_property_uuid en $params
   * @return bool true si se ejecutó; false si se omitió; relanza si es otro error.
   */
  function creea_exec_with_unit_fallback(
    PDO $pdo, PDOStatement $stmt, array $params,
    string $table, string $kind, int $unitIdx, int $fpIdx,
    string $dataset, string $pid, string $intId, string $flowId
  ): bool {
    try {
      $stmt->execute($params);
      return true;
    } catch (PDOException $e) {
      // Detección SÓLIDA de SQLSTATE 45000 (trigger) sin depender del texto
      $sqlstate = (string)($e->errorInfo[0] ?? '');
      $code     = (string)$e->getCode();
      $is45000  = ($sqlstate === '45000') || ($code === '45000');

      if ($is45000) {

        // Preparar datos
        $fpFromParams = $params[$fpIdx]  ?? null;
        $unitOld      = $params[$unitIdx]?? null;

        // FP efectiva: exchange.fp o flow.reference_fp
        $effFp = creea_effectiveFpUuid($pdo, $fpFromParams ? (string)$fpFromParams : null, (string)$flowId);

        // 1) unit_group de la FP efectiva (o curarla si viene sin group y tenemos unidad)
        $ugFp = $effFp ? creea_unitGroupOfFp($pdo, (string)$effFp) : null;
        if (!$ugFp) {
          $ugFp = creea_getOrFixFpUnitGroup($pdo, (string)($effFp ?? ''), $unitOld ? (string)$unitOld : null, null);
        }

        // 2) obtener unidad de referencia del unit_group
        $refUnit = $ugFp ? creea_getReferenceUnitInGroup($pdo, (string)$ugFp) : null;

        // 2b) averiguar unit_group de la unidad original (para log)
        $unitGroupOld = null;
        if ($unitOld) {
          try {
            $sUG = $pdo->prepare("SELECT unit_group_uuid FROM units WHERE uuid=:u");
            $sUG->execute([':u'=>$unitOld]);
            $unitGroupOld = $sUG->fetchColumn() ?: null;
          } catch (Throwable $e) {}
        }

        if ($refUnit) {
          // Log del reemplazo + reintento
          creea_logMismatch([
            'dataset'            => (string)$dataset,
            'process_uuid'       => (string)$pid,
            'exchange_id'        => (string)$intId,
            'kind'               => (string)$kind,
            'flow_uuid'          => (string)$flowId,
            'flow_property_uuid' => (string)($effFp ?? ''),
            'unit_uuid'          => (string)($unitOld ?? ''),
            'fp_unit_group'      => (string)($ugFp ?? ''),
            'unit_unit_group'    => (string)($unitGroupOld ?? ''),
            'note'               => 'Unidad reemplazada por la unidad de referencia del unit_group (auto-fix)'
          ]);

          // Sustituir unidad y FP (si venía null) y reintentar en un try/catch independiente
          $params[$unitIdx] = $refUnit;
          if (!$fpFromParams && $effFp) { $params[$fpIdx] = $effFp; }

          try {
            $stmt->execute($params);
            return true;
          } catch (PDOException $e2) {
            $sqlstate2 = (string)($e2->errorInfo[0] ?? '');
            $code2     = (string)$e2->getCode();
            $is45000_2 = ($sqlstate2 === '45000') || ($code2 === '45000');
            if ($is45000_2) {
              // Si aún así falla, lo omitimos en lugar de abortar la corrida
              creea_logMismatch([
                'dataset'            => (string)$dataset,
                'process_uuid'       => (string)$pid,
                'exchange_id'        => (string)$intId,
                'kind'               => (string)$kind,
                'flow_uuid'          => (string)$flowId,
                'flow_property_uuid' => (string)($effFp ?? ''),
                'unit_uuid'          => (string)$refUnit,
                'fp_unit_group'      => (string)($ugFp ?? ''),
                'unit_unit_group'    => (string)($unitGroupOld ?? ''),
                'note'               => 'Omitido tras fallback: persistió el 45000 en reintento'
              ]);
              return false;
            }
            throw $e2;
          }
        }

        // 3) No hay unidad de referencia: registrar y omitir
        creea_logMismatch([
          'dataset'            => (string)$dataset,
          'process_uuid'       => (string)$pid,
          'exchange_id'        => (string)$intId,
          'kind'               => (string)$kind,
          'flow_uuid'          => (string)$flowId,
          'flow_property_uuid' => (string)($effFp ?? ''),
          'unit_uuid'          => (string)($unitOld ?? ''),
          'fp_unit_group'      => (string)($ugFp ?? ''),
          'unit_unit_group'    => (string)($unitGroupOld ?? ''),
          'note'               => 'Omitido: no hay unidad de referencia para el unit_group de la FP'
        ]);
        return false;
      }

      // No fue un 45000 (otro error): relanzar
      throw $e;
    }
  }
}



if (!function_exists('creea_effectiveFpUuid')) {
  function creea_effectiveFpUuid(PDO $pdo, ?string $fpFromEx, string $flowUuid): ?string {
    if ($fpFromEx && $fpFromEx !== '') { return $fpFromEx; }
    try {
      $s = $pdo->prepare("SELECT reference_flow_property_uuid FROM flows WHERE uuid=:f");
      $s->execute([':f'=>$flowUuid]);
      $ref = $s->fetchColumn();
      return $ref ?: null;
    } catch (Throwable $e) { return null; }
  }
}



if (!function_exists('uuidv4')) {
  function uuidv4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
  }
}

if (!function_exists('filesIn')) {
  function filesIn(string $dir): array {
    return is_dir($dir) ? (glob($dir.'/*.json') ?: []) : [];
  }
}

if (!function_exists('jread')) {
  function jread(string $file): array {
    $d = json_decode(file_get_contents($file), true);
    if (!is_array($d)) { throw new RuntimeException("JSON inválido: $file"); }
    return $d;
  }
}

if (!function_exists('jval')) {
  function jval($o, string $path, $def=null){
    $p=explode('.', $path); $c=$o;
    foreach($p as $k){ if(is_array($c) && array_key_exists($k,$c)){$c=$c[$k];} else return $def; }
    return $c;
  }
}

if (!function_exists('b01')) {
  function b01($v): int { return $v ? 1 : 0; }
}

if (!function_exists('isoToMySql')) {
  function isoToMySql(?string $s): ?string {
    if (!$s) return null;
    $s = preg_replace('/\.\d+Z$/', 'Z', $s);
    $s = str_replace('T',' ', rtrim($s,'Z'));
    if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/',$s)) {
      if (strlen($s)===16) $s .= ':00';
      return $s;
    }
    return null;
  }
}

if (!function_exists('mapPrice')) {
  function mapPrice(array $ex): array {
    $type = $ex['priceType'] ?? jval($ex, 'costs.type');
    $val  = $ex['price'] ?? jval($ex, 'costs.amount');
    $cur  = jval($ex, 'currency.@id') ?? jval($ex, 'costs.currency.@id');
    return [$type ?: null, isset($val) ? (float)$val : null, $cur ?: null];
  }
}

if (!function_exists('mapUncertainty')) {
  function mapUncertainty(?array $u): array {
    if (!$u) return [null,null,null,null,null];
    $t = $u['distributionType'] ?? $u['type'] ?? null;
    $t = is_string($t) ? strtolower($t) : null;
    $meanMode = $sdGsd = $min = $max = null;
    if ($t === 'normal') {
      $meanMode = $u['mean'] ?? null;
      $sdGsd    = $u['sd'] ?? null;
      $min      = $u['minimum'] ?? $u['lower'] ?? null;
      $max      = $u['maximum'] ?? $u['upper'] ?? null;
    } elseif ($t === 'lognormal') {
      $meanMode = $u['gMean'] ?? $u['mean'] ?? null;
      $sdGsd    = $u['gSd'] ?? $u['sd'] ?? null;
      $min      = $u['minimum'] ?? $u['lower'] ?? null;
      $max      = $u['maximum'] ?? $u['upper'] ?? null;
    } elseif ($t === 'triangle' || $t === 'triangular') {
      $meanMode = $u['mode'] ?? null;
      $sdGsd    = null;
      $min      = $u['minimum'] ?? $u['lower'] ?? null;
      $max      = $u['maximum'] ?? $u['upper'] ?? null;
    } else {
      $min = $u['minimum'] ?? $u['lower'] ?? null;
      $max = $u['maximum'] ?? $u['upper'] ?? null;
    }
    return [$t, $meanMode, $sdGsd, $min, $max];
  }
}

if (!function_exists('normalizeYear')) {
  function normalizeYear($raw): ?int {
    if ($raw === null) return null;
    if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
      $y = (int)$raw;
      return ($y >= 1800 && $y <= 2100) ? $y : null;
    }
    if (is_string($raw)) {
      if (preg_match('/\b(18\d{2}|19\d{2}|20\d{2}|2100)\b/', $raw, $m)) {
        $y = (int)$m[1];
        return ($y >= 1800 && $y <= 2100) ? $y : null;
      }
    }
    return null;
  }
}

/* ===== Helpers de FlowProperty ↔ UnitGroup ↔ Unit (todos FUERA de funciones) ===== */

if (!function_exists('creea_unitGroupOfFp')) {
  function creea_unitGroupOfFp(PDO $pdo, string $fpUuid): ?string {
    $st = $pdo->prepare("SELECT unit_group_uuid FROM flow_properties WHERE uuid=?");
    $st->execute([$fpUuid]);
    return $st->fetchColumn() ?: null;
  }
}

if (!function_exists('creea_unitGroupOfUnit')) {
  function creea_unitGroupOfUnit(PDO $pdo, string $unitUuid): ?string {
    $st = $pdo->prepare("SELECT unit_group_uuid FROM units WHERE uuid=?");
    $st->execute([$unitUuid]);
    return $st->fetchColumn() ?: null;
  }
}

if (!function_exists('creea_logMismatch')) {
  function creea_logMismatch(array $row): void {
    $log = '/home/u303404040/domains/ciclodevida.mx/secure_lca_inserts/mismatch_exchanges.csv';
    $isNew = !is_file($log);
    $fh = @fopen($log, 'a');
    if ($fh) {
      if ($isNew) {
        fputcsv($fh, ['ts','dataset','process_uuid','exchange_id','kind','flow_uuid','flow_property_uuid','unit_uuid','fp_unit_group','unit_unit_group','note']);
      }
      fputcsv($fh, [
        date('Y-m-d H:i:s'),
        $row['dataset'] ?? '',
        $row['process_uuid'] ?? '',
        $row['exchange_id'] ?? '',
        $row['kind'] ?? '',
        $row['flow_uuid'] ?? '',
        $row['flow_property_uuid'] ?? '',
        $row['unit_uuid'] ?? '',
        $row['fp_unit_group'] ?? '',
        $row['unit_unit_group'] ?? '',
        $row['note'] ?? '',
      ]);
      fclose($fh);
    } else {
      error_log("[CREEA] mismatch_exchanges: ".json_encode($row, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
  }
}

if (!function_exists('creea_getFlowRefFpUuid')) {
  function creea_getFlowRefFpUuid(PDO $pdo, string $flowUuid): ?string {
    $st = $pdo->prepare("SELECT reference_flow_property_uuid FROM flows WHERE uuid = :u");
    $st->execute([':u' => $flowUuid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row['reference_flow_property_uuid'] ?? null;
  }
}

if (!function_exists('creea_getOrFixFpUnitGroup')) {
  /**
   * Si la flow_property no tiene unit_group_uuid, intenta asignarlo
   * usando la unidad del exchange (por uuid o por nombre).
   * Devuelve el unit_group_uuid final (o null si no pudo).
   */
  function creea_getOrFixFpUnitGroup(PDO $pdo, string $fpUuid, ?string $unitUuidFromEx, ?string $unitNameFromEx): ?string {
    // ¿Ya tiene unit_group?
    $st = $pdo->prepare("SELECT unit_group_uuid FROM flow_properties WHERE uuid=:fp");
    $st->execute([':fp'=>$fpUuid]);
    $ug = $st->fetchColumn() ?: null;
    if ($ug) return $ug;

    // Tomar unit_group desde la unidad del exchange (uuid)
    if ($unitUuidFromEx) {
      $st = $pdo->prepare("SELECT unit_group_uuid FROM units WHERE uuid=:u");
      $st->execute([':u'=>$unitUuidFromEx]);
      $ug = $st->fetchColumn() ?: null;
      if ($ug) {
        $name = null;
        try {
          $s2 = $pdo->prepare("SELECT name FROM unit_groups WHERE uuid=:g");
          $s2->execute([':g'=>$ug]);
          $name = $s2->fetchColumn() ?: null;
        } catch (Throwable $e) {}
        $sql = "UPDATE flow_properties SET unit_group_uuid=:g" . ($name ? ", unit_group_name=:n" : "") . " WHERE uuid=:fp AND unit_group_uuid IS NULL";
        $p   = [':g'=>$ug, ':fp'=>$fpUuid]; if ($name) $p[':n']=$name;
        $pdo->prepare($sql)->execute($p);
        return $ug;
      }
    }

    // Intentar por nombre de unidad
    if ($unitNameFromEx) {
      $st = $pdo->prepare("SELECT unit_group_uuid FROM units WHERE UPPER(TRIM(name)) = UPPER(TRIM(:n)) LIMIT 1");
      $st->execute([':n'=>$unitNameFromEx]);
      $ug = $st->fetchColumn() ?: null;
      if ($ug) {
        $name = null;
        try {
          $s2 = $pdo->prepare("SELECT name FROM unit_groups WHERE uuid=:g");
          $s2->execute([':g'=>$ug]);
          $name = $s2->fetchColumn() ?: null;
        } catch (Throwable $e) {}
        $sql = "UPDATE flow_properties SET unit_group_uuid=:g" . ($name ? ", unit_group_name=:n" : "") . " WHERE uuid=:fp AND unit_group_uuid IS NULL";
        $p   = [':g'=>$ug, ':fp'=>$fpUuid]; if ($name) $p[':n']=$name;
        $pdo->prepare($sql)->execute($p);
        return $ug;
      }
    }

    return null;
  }
}

if (!function_exists('creea_findUnitByNameInGroup')) {
  function creea_findUnitByNameInGroup(PDO $pdo, ?string $name, string $ugUuid): ?string {
    if (!$name) return null;
    $st = $pdo->prepare("SELECT uuid FROM units WHERE unit_group_uuid=:g AND UPPER(TRIM(name))=UPPER(TRIM(:n)) LIMIT 1");
    $st->execute([':g'=>$ugUuid, ':n'=>$name]);
    return $st->fetchColumn() ?: null;
  }
}

if (!function_exists('creea_getReferenceUnitInGroup')) {
  function creea_getReferenceUnitInGroup(PDO $pdo, string $ugUuid): ?string {
    // 1) reference_unit_uuid en unit_groups
    try {
      $s = $pdo->prepare("SELECT reference_unit_uuid FROM unit_groups WHERE uuid=:g");
      $s->execute([':g'=>$ugUuid]);
      $ref = $s->fetchColumn();
      if ($ref) return $ref;
    } catch (Throwable $e) {}
    // 2) bandera en units
    try {
      $s = $pdo->prepare("SELECT uuid FROM units WHERE unit_group_uuid=:g AND (is_reference=1 OR is_reference='1') LIMIT 1");
      $s->execute([':g'=>$ugUuid]);
      $ref = $s->fetchColumn();
      if ($ref) return $ref;
    } catch (Throwable $e) {}
    // 3) cualquier unidad del grupo
    $s = $pdo->prepare("SELECT uuid FROM units WHERE unit_group_uuid=:g ORDER BY name ASC LIMIT 1");
    $s->execute([':g'=>$ugUuid]);
    return $s->fetchColumn() ?: null;
  }
}

if (!function_exists('creea_resolveFpAndUnit')) {
  /**
   * Devuelve [flow_property_uuid_resuelta, unit_uuid_resuelta]
   * - Usa la FP de referencia del flow si existe; si no, la del exchange.
   * - Garantiza unit_group en la FP (y lo “cura” si está NULL usando la unidad del exchange).
   * - Elige una unidad válida del group (por uuid → por nombre → unidad de referencia).
   */
  function creea_resolveFpAndUnit(PDO $pdo, string $flowUuid, ?string $fpFromEx, ?string $unitUuidFromEx, ?string $unitNameFromEx): array {
    $fpUuid = creea_getFlowRefFpUuid($pdo, $flowUuid) ?: $fpFromEx;
    if (!$fpUuid) {
      throw new RuntimeException("No se encontró reference_flow_property_uuid para flow=$flowUuid");
    }

    $ug = creea_getOrFixFpUnitGroup($pdo, $fpUuid, $unitUuidFromEx, $unitNameFromEx);
    if (!$ug) {
      throw new RuntimeException("Flow property $fpUuid sin unit_group (no pude derivarlo de la unidad del exchange)");
    }

    $unitUuid = $unitUuidFromEx;

    // Si vino unidad pero no pertenece al grupo, descártala
    if ($unitUuid) {
      $st = $pdo->prepare("SELECT 1 FROM units WHERE uuid=:u AND unit_group_uuid=:g");
      $st->execute([':u'=>$unitUuid, ':g'=>$ug]);
      if (!$st->fetchColumn()) { $unitUuid = null; }
    }

    // Intentar por nombre
    if (!$unitUuid && $unitNameFromEx) {
      $unitUuid = creea_findUnitByNameInGroup($pdo, $unitNameFromEx, $ug);
    }

    // Fallback a unidad de referencia del grupo
    if (!$unitUuid) {
      $unitUuid = creea_getReferenceUnitInGroup($pdo, $ug);
    }

    if (!$unitUuid) {
      throw new RuntimeException("No se pudo resolver unidad válida para unit_group=$ug (flow=$flowUuid, fp=$fpUuid)");
    }

    return [$fpUuid, $unitUuid];
  }
}

/**
 * Conveniencia: devuelve true si hay mismatch de unit_group (y lo registra), para hacer continue;
 */
// Conveniencia: devuelve true si hay mismatch y ya lo registró
if (!function_exists('creea_should_skip_exchange')) {
  function creea_should_skip_exchange(
    PDO $pdo, ?string $fpId, ?string $unitId,
    string|int|null $dataset, string|int|null $pid, string|int|null $intId,
    string|int|null $flowId, string|int|null $category,
    ?string $unitNameFromEx = null
  ): bool {
    // Normaliza a string (evita TypeError con strict_types=1)
    $dataset  = (string)($dataset  ?? '');
    $pid      = (string)($pid      ?? '');
    $intId    = (string)($intId    ?? '');
    $flowId   = (string)($flowId   ?? '');
    $category = (string)($category ?? '');

    if (!$fpId || !$unitId) return false;

    $ugFp   = creea_unitGroupOfFp($pdo, $fpId);
    $ugUnit = creea_unitGroupOfUnit($pdo, $unitId);

    // Si la FP no tiene unit_group, intenta repararla con la unidad del exchange (no cambiamos unidad)
    if (!$ugFp && function_exists('creea_getOrFixFpUnitGroup')) {
      $fixed = creea_getOrFixFpUnitGroup($pdo, $fpId, $unitId, $unitNameFromEx);
      $ugFp = $fixed ?: null;
    }

    if ($ugFp && $ugUnit && $ugFp !== $ugUnit) {
      $kind = ($category === 'INPUT') ? 'IN' : (($category === 'OUTPUT') ? 'OUT' : 'UNK');
      creea_logMismatch([
        'dataset'            => $dataset,
        'process_uuid'       => $pid,
        'exchange_id'        => $intId,
        'kind'               => $kind,
        'flow_uuid'          => $flowId,
        'flow_property_uuid' => $fpId,
        'unit_uuid'          => $unitId,
        'fp_unit_group'      => $ugFp,
        'unit_unit_group'    => $ugUnit,
        'note'               => 'Unidad no pertenece al unit_group de la FP. Exchange omitido.'
      ]);
      return true; // ← indica que hay que saltar (continue)
    }
    return false;
  }
}



if (!function_exists('creea_effectiveFpUuid')) {
  function creea_effectiveFpUuid(PDO $pdo, ?string $fpFromEx, string $flowUuid): ?string {
    if ($fpFromEx && $fpFromEx !== '') { return $fpFromEx; }
    try {
      $s = $pdo->prepare("SELECT reference_flow_property_uuid FROM flows WHERE uuid=:f");
      $s->execute([':f'=>$flowUuid]);
      $ref = $s->fetchColumn();
      return $ref ?: null;
    } catch (Throwable $e) { return null; }
  }
}

/* ========================= Motor ========================= */

function run_import(PDO $pdo, string $DATASET_DIR): void {
  $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
  $stmts = [];

  // Deduplicación de units
  $unitNameMap   = []; // [unit_group_uuid][lower(name)] => canonical_unit_uuid
  $unitUuidAlias = []; // [duplicate_unit_uuid] => canonical_unit_uuid

  // Recolector de FPs faltantes en factors
  $missingFlowProps = []; // uuid => true

  /* ---------- PREPARES ---------- */

  // actors
  $stmts['actors'] = $pdo->prepare("
    INSERT INTO actors (
      uuid, name, description, category, version, last_change,
      address, city, zip_code, country, email, telefax, telephone, website
    ) VALUES (
      :uuid, :name, :description, :category, :version, :last_change,
      :address, :city, :zip_code, :country, :email, :telefax, :telephone, :website
    )
    ON DUPLICATE KEY UPDATE
      name=VALUES(name),
      description=VALUES(description),
      category=VALUES(category),
      version=VALUES(version),
      last_change=VALUES(last_change),
      address=VALUES(address),
      city=VALUES(city),
      zip_code=VALUES(zip_code),
      country=VALUES(country),
      email=VALUES(email),
      telefax=VALUES(telefax),
      telephone=VALUES(telephone),
      website=VALUES(website)
  ");

  // unit_groups
  $stmts['unit_groups'] = $pdo->prepare("
    INSERT INTO unit_groups (
      uuid, name, category, description, reference_unit, version, last_change, default_flow_property_uuid
    ) VALUES (
      :uuid, :name, :category, :description, :reference_unit, :version, :last_change, :default_fp_uuid
    )
    ON DUPLICATE KEY UPDATE
      name=VALUES(name),
      category=VALUES(category),
      description=VALUES(description),
      reference_unit=VALUES(reference_unit),
      version=VALUES(version),
      last_change=VALUES(last_change),
      default_flow_property_uuid=VALUES(default_flow_property_uuid)
  ");

  // units (UPDATE-first con synonyms)
  $qFindUnit = $pdo->prepare("SELECT uuid FROM units WHERE uuid=? LIMIT 1");
  $qUpdUnit  = $pdo->prepare("
    UPDATE units
    SET name=?, description=?, synonyms=?, unit_group_uuid=?, conversion_factor=?, is_ref_unit=?
    WHERE uuid=?
  ");
  $qInsUnit  = $pdo->prepare("
    INSERT INTO units (uuid, name, description, synonyms, unit_group_uuid, conversion_factor, is_ref_unit)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");

  // flow_properties
  $stmts['flow_properties'] = $pdo->prepare("
    INSERT INTO flow_properties (
      uuid, name, description, category, unit_group_name, `type`, version, last_change, unit_group_uuid
    ) VALUES (
      :uuid, :name, :description, :category, :unit_group_name, :type, :version, :last_change, :unit_group_uuid
    )
    ON DUPLICATE KEY UPDATE
      name=VALUES(name),
      description=VALUES(description),
      category=VALUES(category),
      unit_group_name=VALUES(unit_group_name),
      `type`=VALUES(`type`),
      version=VALUES(version),
      last_change=VALUES(last_change),
      unit_group_uuid=VALUES(unit_group_uuid)
  ");
  $qUGName = $pdo->prepare("SELECT name FROM unit_groups WHERE uuid=? LIMIT 1");

  // locations
  $stmts['locations'] = $pdo->prepare("
    INSERT INTO locations (
      uuid, code, name, category, description, latitude, longitude, last_change, version
    ) VALUES (
      :uuid, :code, :name, :category, :description, :lat, :lon, :last_change, :version
    )
    ON DUPLICATE KEY UPDATE
      code=VALUES(code),
      name=VALUES(name),
      category=VALUES(category),
      description=VALUES(description),
      latitude=VALUES(latitude),
      longitude=VALUES(longitude),
      last_change=VALUES(last_change),
      version=VALUES(version)
  ");

  // sources (autodetect year/year_published)
  $hasYear = (bool)$pdo->query("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='sources' AND COLUMN_NAME='year'
  ")->fetchColumn();
  $hasYearPublished = (bool)$pdo->query("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='sources' AND COLUMN_NAME='year_published'
  ")->fetchColumn();

  if ($hasYear) {
    $stmts['sources'] = $pdo->prepare("
      INSERT INTO sources (uuid, name, text_reference, `year`)
      VALUES (:uuid, :name, :text_reference, :year)
      ON DUPLICATE KEY UPDATE
        name=VALUES(name), text_reference=VALUES(text_reference), `year`=VALUES(`year`)
    ");
  } elseif ($hasYearPublished) {
    $stmts['sources'] = $pdo->prepare("
      INSERT INTO sources (uuid, name, text_reference, year_published)
      VALUES (:uuid, :name, :text_reference, :year_published)
      ON DUPLICATE KEY UPDATE
        name=VALUES(name), text_reference=VALUES(text_reference), year_published=VALUES(year_published)
    ");
  } else {
    $stmts['sources'] = $pdo->prepare("
      INSERT INTO sources (uuid, name, text_reference)
      VALUES (:uuid, :name, :text_reference)
      ON DUPLICATE KEY UPDATE
        name=VALUES(name), text_reference=VALUES(text_reference)
    ");
  }

  // flows (esquema real del usuario)
  $stmts['flows'] = $pdo->prepare("
    INSERT INTO flows (
      uuid, name, description, category, version, last_change,
      flow_type, cas, formula, location_uuid,
      reference_flow_property_name, reference_flow_property_uuid
    ) VALUES (
      :uuid, :name, :description, :category, :version, :last_change,
      :flow_type, :cas, :formula, :location_uuid,
      :ref_fp_name, :ref_fp_uuid
    )
    ON DUPLICATE KEY UPDATE
      name=VALUES(name),
      description=VALUES(description),
      category=VALUES(category),
      version=VALUES(version),
      last_change=VALUES(last_change),
      flow_type=VALUES(flow_type),
      cas=VALUES(cas),
      formula=VALUES(formula),
      location_uuid=VALUES(location_uuid),
      reference_flow_property_name=VALUES(reference_flow_property_name),
      reference_flow_property_uuid=VALUES(reference_flow_property_uuid)
  ");

  // Flow Property Factors prepares + support queries
  $qHasFlowProp = $pdo->prepare("SELECT 1 FROM flow_properties WHERE uuid=? LIMIT 1");
  $qFindFpf = $pdo->prepare("
    SELECT uuid FROM flow_property_factors
    WHERE flow_uuid = ? AND flow_property_uuid = ? LIMIT 1
  ");
  $qUpdFpf  = $pdo->prepare("
    UPDATE flow_property_factors
    SET conversion_factor = ?, is_ref_flow_property = ?
    WHERE uuid = ?
  ");
  $qInsFpf  = $pdo->prepare("
    INSERT INTO flow_property_factors
      (uuid, flow_uuid, flow_property_uuid, conversion_factor, is_ref_flow_property)
    VALUES (?, ?, ?, ?, ?)
  ");
  $qGetFpName  = $pdo->prepare("SELECT name FROM flow_properties WHERE uuid=? LIMIT 1");
  $qUpdFlowRef = $pdo->prepare("
    UPDATE flows
    SET reference_flow_property_uuid = ?, reference_flow_property_name = ?
    WHERE uuid = ?
  ");

  // processes
  $stmts['processes'] = $pdo->prepare("
    INSERT INTO processes (
      uuid, name, process_type, category, description, version, last_change,
      tags_text, valid_from, valid_until, time_desc, location_uuid, geo_desc,
      tech_desc, dq_process_schema, dq_data_quality, dq_flow_schema, dq_social_schema, created_at
    ) VALUES (
      :uuid, :name, :ptype, :category, :description, :version, :last_change,
      :tags, :valid_from, :valid_until, :time_desc, :loc_uuid, :geo_desc,
      :tech_desc, :dq_proc, :dq_data, :dq_flow, :dq_social, :created_at
    )
    ON DUPLICATE KEY UPDATE
      name=VALUES(name),
      process_type=VALUES(process_type),
      category=VALUES(category),
      description=VALUES(description),
      version=VALUES(version),
      last_change=VALUES(last_change),
      tags_text=VALUES(tags_text),
      valid_from=VALUES(valid_from),
      valid_until=VALUES(valid_until),
      time_desc=VALUES(time_desc),
      location_uuid=VALUES(location_uuid),
      geo_desc=VALUES(geo_desc),
      tech_desc=VALUES(tech_desc),
      dq_process_schema=VALUES(dq_process_schema),
      dq_data_quality=VALUES(dq_data_quality),
      dq_flow_schema=VALUES(dq_flow_schema),
      dq_social_schema=VALUES(dq_social_schema)
  ");

  // process_documentation
  $stmts['process_documentation'] = $pdo->prepare("
    INSERT INTO process_documentation (
      process_uuid, lci_method, modeling_constants,
      ds_data_completeness, ds_data_selection, ds_data_treatment,
      ds_sampling_procedure, ds_collection_period, ds_use_advice,
      completeness_text, sources_text, publication_source_uuid,
      project, intended_application, creation_date, copyright_flag,
      access_use_restrictions
    ) VALUES (
      :proc_uuid, :lci_method, :modeling_constants,
      :ds_data_completeness, :ds_data_selection, :ds_data_treatment,
      :ds_sampling_procedure, :ds_collection_period, :ds_use_advice,
      :completeness_text, :sources_text, :publication_source_uuid,
      :project, :intended_application, :creation_date, :copyright_flag,
      :access_use_restrictions
    )
    ON DUPLICATE KEY UPDATE
      lci_method=VALUES(lci_method),
      modeling_constants=VALUES(modeling_constants),
      ds_data_completeness=VALUES(ds_data_completeness),
      ds_data_selection=VALUES(ds_data_selection),
      ds_data_treatment=VALUES(ds_data_treatment),
      ds_sampling_procedure=VALUES(ds_sampling_procedure),
      ds_collection_period=VALUES(ds_collection_period),
      ds_use_advice=VALUES(ds_use_advice),
      completeness_text=VALUES(completeness_text),
      sources_text=VALUES(sources_text),
      publication_source_uuid=VALUES(publication_source_uuid),
      project=VALUES(project),
      intended_application=VALUES(intended_application),
      creation_date=VALUES(creation_date),
      copyright_flag=VALUES(copyright_flag),
      access_use_restrictions=VALUES(access_use_restrictions)
  ");
  $qHasSource = $pdo->prepare("SELECT 1 FROM sources WHERE uuid=? LIMIT 1");

  // apoyo para exchanges
  $qFlowMeta = $pdo->prepare("SELECT category, location_uuid FROM flows WHERE uuid=? LIMIT 1");
  $qProcName = $pdo->prepare("SELECT name FROM processes WHERE uuid=? LIMIT 1");

  // inputs/outputs (UPDATE-first)
  $qFindPi = $pdo->prepare("SELECT uuid FROM process_inputs  WHERE process_uuid=? AND internal_id <=> ? LIMIT 1");
  $qUpdPi  = $pdo->prepare("
    UPDATE process_inputs SET
      flow_uuid=?, category=?, amount=?, unit_uuid=?, flow_property_uuid=?,
      price_type=?, price_value=?, currency_uuid=?,
      uncertainty_type=COALESCE(?,'NONE'), stat_mean_mode=?, stat_sd_gsd=?, stat_min=?, stat_max=?,
      is_avoided=?, provider_process_uuid=NULLIF(?, ''), provider_name=?, dq_entry_text=?,
      location_uuid=?, description=?, is_reference=?
    WHERE uuid=?
  ");
  $qInsPi  = $pdo->prepare("
    INSERT INTO process_inputs (
      uuid, process_uuid, internal_id, flow_uuid, category, amount, unit_uuid,
      flow_property_uuid, price_type, price_value, currency_uuid, uncertainty_type,
      stat_mean_mode, stat_sd_gsd, stat_min, stat_max, is_avoided,
      provider_process_uuid, provider_name, dq_entry_text, location_uuid, description, is_reference
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, 'NONE'), ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?
    )
  ");

  $qFindPo = $pdo->prepare("SELECT uuid FROM process_outputs WHERE process_uuid=? AND internal_id <=> ? LIMIT 1");
  $qUpdPo  = $pdo->prepare("
    UPDATE process_outputs SET
      flow_uuid=?, category=?, amount=?, unit_uuid=?, flow_property_uuid=?,
      price_type=?, price_value=?, currency_uuid=?,
      uncertainty_type=COALESCE(?,'NONE'), stat_mean_mode=?, stat_sd_gsd=?, stat_min=?, stat_max=?,
      is_avoided=?, provider_process_uuid=NULLIF(?, ''), provider_name=?, dq_entry_text=?,
      location_uuid=?, description=?, is_reference=?
    WHERE uuid=?
  ");
  $qInsPo  = $pdo->prepare("
    INSERT INTO process_outputs (
      uuid, process_uuid, internal_id, flow_uuid, category, amount, unit_uuid,
      flow_property_uuid, price_type, price_value, currency_uuid, uncertainty_type,
      stat_mean_mode, stat_sd_gsd, stat_min, stat_max, is_avoided,
      provider_process_uuid, provider_name, dq_entry_text, location_uuid, description, is_reference
    ) VALUES (
      ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, 'NONE'), ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?
    )
  ");

  /* ---------- IMPORT ---------- */

  $ugDefaults = []; // unit_group_uuid => default_flow_property_uuid

  // 1) unit_groups + units
  $pdo->beginTransaction();
  try {
    foreach (filesIn($DATASET_DIR.'/unit_groups') as $f) {
      $d    = jread($f);
      $ug   = $d['@id'] ?? null;
      $name = $d['name'] ?? null;
      $cat  = $d['category'] ?? null;
      $desc = $d['description'] ?? null;
      $ver  = $d['version'] ?? null;
      $last = isoToMySql($d['lastChange'] ?? null);

      // reference_unit a partir de la unidad marcada ref
      $refUnitName = null;
      foreach (($d['units'] ?? []) as $u0) {
        if (b01($u0['isRefUnit'] ?? false) === 1) {
          $refUnitName = $u0['name'] ?? null;
          break;
        }
      }

      $defaultFP = jval($d, 'defaultFlowProperty.@id');
      if ($defaultFP) { $ugDefaults[$ug] = $defaultFP; }

      // insert con default_fp NULL
      $stmts['unit_groups']->execute([
        ':uuid'=>$ug, ':name'=>$name, ':category'=>$cat, ':description'=>$desc,
        ':reference_unit'=>$refUnitName, ':version'=>$ver, ':last_change'=>$last,
        ':default_fp_uuid'=>null
      ]);

      // units embebidas (UPDATE-first + dedupe por nombre dentro del grupo)
      foreach (($d['units'] ?? []) as $u) {
        $uid   = $u['@id'] ?? null;
        $uname = $u['name'] ?? null;
        $udesc = $u['description'] ?? null;

        // dedupe por nombre
        $normName = $uname !== null ? mb_strtolower(trim($uname)) : null;
        if ($normName !== null && $normName !== '') {
          if (!isset($unitNameMap[$ug])) { $unitNameMap[$ug] = []; }
          if (isset($unitNameMap[$ug][$normName]) && $unitNameMap[$ug][$normName] !== $uid) {
            $unitUuidAlias[$uid] = $unitNameMap[$ug][$normName];
            continue; // no insert/update esta unidad duplicada por nombre
          }
          $unitNameMap[$ug][$normName] = $uid; // registrar canónica
        }

        // synonyms normalizado a string
        $syn = null;
        if (isset($u['synonyms'])) {
          if (is_array($u['synonyms'])) { $syn = implode(', ', array_filter(array_map('strval', $u['synonyms']))); }
          elseif (is_scalar($u['synonyms'])) { $syn = (string)$u['synonyms']; }
        }

        $conv  = isset($u['conversionFactor']) ? (float)$u['conversionFactor'] : null;
        $isRef = b01($u['isRefUnit'] ?? false);

        $qFindUnit->execute([$uid]);
        if ($qFindUnit->fetchColumn()) {
          $qUpdUnit->execute([$uname, $udesc, $syn, $ug, $conv, $isRef, $uid]);
        } else {
          $qInsUnit->execute([$uid, $uname, $udesc, $syn, $ug, $conv, $isRef]);
        }
      }
    }
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

  // 2) flow_properties
  $pdo->beginTransaction();
  try {
    foreach (filesIn($DATASET_DIR.'/flow_properties') as $f) {
      $d      = jread($f);
      $uuid   = $d['@id'] ?? null;
      $name   = $d['name'] ?? null;
      $desc   = $d['description'] ?? null;
      $cat    = $d['category'] ?? null;
      $type   = $d['flowPropertyType'] ?? null;
      $version= $d['version'] ?? null;
      $lastCh = isoToMySql($d['lastChange'] ?? null);
      $ugId   = jval($d, 'unitGroup.@id');

      $ugName = jval($d, 'unitGroup.name');
      if (!$ugName && $ugId) { $qUGName->execute([$ugId]); $ugName = $qUGName->fetchColumn() ?: null; }

      $stmts['flow_properties']->execute([
        ':uuid'=>$uuid, ':name'=>$name, ':description'=>$desc, ':category'=>$cat,
        ':unit_group_name'=>$ugName, ':type'=>$type, ':version'=>$version,
        ':last_change'=>$lastCh, ':unit_group_uuid'=>$ugId
      ]);
    }
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

  // 2b) Post: set default_flow_property_uuid ahora que existen las FP
  $pdo->beginTransaction();
  try {
    $qCheck = $pdo->prepare("SELECT 1 FROM flow_properties WHERE uuid=? AND unit_group_uuid=? LIMIT 1");
    $qUpd   = $pdo->prepare("UPDATE unit_groups SET default_flow_property_uuid=? WHERE uuid=?");
    foreach ($ugDefaults as $ug => $dfp) {
      if (!$dfp) continue;
      $qCheck->execute([$dfp, $ug]);
      if ($qCheck->fetchColumn()) { $qUpd->execute([$dfp, $ug]); }
    }
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

  // 3) locations
  $pdo->beginTransaction();
  try {
    foreach (filesIn($DATASET_DIR.'/locations') as $f) {
      $d = jread($f);
      $uuid = $d['@id'] ?? null;
      $code = $d['code'] ?? null;
      $name = $d['name'] ?? null;
      $cat  = $d['category'] ?? null;
      $desc = $d['description'] ?? null;
      $lat  = isset($d['latitude'])  ? (float)$d['latitude']  : null;
      $lon  = isset($d['longitude']) ? (float)$d['longitude'] : null;
      $last = isoToMySql($d['lastChange'] ?? null);
      $ver  = $d['version'] ?? null;

      $stmts['locations']->execute([
        ':uuid'=>$uuid, ':code'=>$code, ':name'=>$name, ':category'=>$cat,
        ':description'=>$desc, ':lat'=>$lat, ':lon'=>$lon,
        ':last_change'=>$last, ':version'=>$ver
      ]);
    }
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

  // 4) actors
  $pdo->beginTransaction();
  try {
    foreach (filesIn($DATASET_DIR.'/actors') as $f) {
      $d = jread($f);
      $stmts['actors']->execute([
        ':uuid'        => $d['@id'] ?? null,
        ':name'        => $d['name'] ?? null,
        ':description' => $d['description'] ?? null,
        ':category'    => $d['category'] ?? null,
        ':version'     => $d['version'] ?? null,
        ':last_change' => isoToMySql($d['lastChange'] ?? null),
        ':address'     => $d['address'] ?? null,
        ':city'        => $d['city'] ?? null,
        ':zip_code'    => $d['zipCode'] ?? null,
        ':country'     => $d['country'] ?? null,
        ':email'       => $d['email'] ?? null,
        ':telefax'     => $d['telefax'] ?? null,
        ':telephone'   => $d['telephone'] ?? null,
        ':website'     => $d['website'] ?? null
      ]);
    }
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

  // 5) sources
  $pdo->beginTransaction();
  try {
    foreach (filesIn($DATASET_DIR.'/sources') as $f) {
      $d       = jread($f);
      $uuid    = $d['@id'] ?? null;
      $name    = $d['name'] ?? null;
      $textRef = $d['textReference'] ?? null;
      $rawYear = $d['year'] ?? ($d['yearPublished'] ?? ($d['publicationYear'] ?? ($d['date'] ?? null)));
      $yearVal = normalizeYear($rawYear);

      if ($hasYear) {
        $stmts['sources']->execute([
          ':uuid'=>$uuid, ':name'=>$name, ':text_reference'=>$textRef, ':year'=>$yearVal
        ]);
      } elseif ($hasYearPublished) {
        $stmts['sources']->execute([
          ':uuid'=>$uuid, ':name'=>$name, ':text_reference'=>$textRef, ':year_published'=>$yearVal
        ]);
      } else {
        $stmts['sources']->execute([
          ':uuid'=>$uuid, ':name'=>$name, ':text_reference'=>$textRef
        ]);
      }
    }
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

  // 6) flows (+ factors)
  $pdo->beginTransaction();
  try {
    foreach (filesIn($DATASET_DIR.'/flows') as $f) {
      $d = jread($f);

      $uuid    = $d['@id'] ?? null;
      if (!$uuid) { continue; }

      $name    = $d['name'] ?? null;
      $desc    = $d['description'] ?? null;
      $cat     = $d['category'] ?? null;
      $type    = $d['flowType'] ?? null;
      $loc_id  = jval($d,'location.@id');

      $ver     = $d['version'] ?? null;
      $lastCh  = isoToMySql($d['lastChange'] ?? null);

      $cas     = $d['cas'] ?? $d['casNumber'] ?? $d['CAS'] ?? null;
      if (is_array($cas))     { $cas = null; }
      $formula = $d['formula'] ?? null;
      if (is_array($formula)) { $formula = null; }

      $stmts['flows']->execute([
        ':uuid'          => $uuid,
        ':name'          => $name,
        ':description'   => $desc,
        ':category'      => $cat,
        ':version'       => $ver,
        ':last_change'   => $lastCh,
        ':flow_type'     => $type,
        ':cas'           => $cas,
        ':formula'       => $formula,
        ':location_uuid' => $loc_id,
        ':ref_fp_name'   => null,
        ':ref_fp_uuid'   => null
      ]);

      $refFpUuid = null;
      foreach (($d['flowProperties'] ?? []) as $pf) {
        $fpId  = jval($pf, 'flowProperty.@id');
        if (!$fpId) { continue; }

        $qHasFlowProp->execute([$fpId]);
        if (!$qHasFlowProp->fetchColumn()) {
          $missingFlowProps[$fpId] = true;
          continue;
        }

        $conv  = isset($pf['conversionFactor']) ? (float)$pf['conversionFactor'] : null;
        $isRef = b01($pf['isRefFlowProperty'] ?? false);

        $qFindFpf->execute([$uuid, $fpId]);
        $hit = $qFindFpf->fetch(PDO::FETCH_ASSOC);
        if ($hit && ($hit['uuid'] ?? null)) {
          $qUpdFpf->execute([$conv, $isRef, $hit['uuid']]);
        } else {
          $qInsFpf->execute([uuidv4(), $uuid, $fpId, $conv, $isRef]);
        }

        if ($isRef) { $refFpUuid = $fpId; }
      }

      if ($refFpUuid) {
        $qGetFpName->execute([$refFpUuid]);
        $fpName = $qGetFpName->fetchColumn() ?: null;
        $qUpdFlowRef->execute([$refFpUuid, $fpName, $uuid]);
      }
    }

    if (!empty($missingFlowProps)) {
      $some = array_slice(array_keys($missingFlowProps), 0, 20);
      error_log("Aviso: se omitieron ".count($missingFlowProps)." flow_property_factors por flow_property_uuid inexistente. Ejemplos: ".implode(', ', $some));
    }

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }

  // 7) processes (+ documentation + exchanges)
  $pdo->beginTransaction();
  try {
    foreach (filesIn($DATASET_DIR.'/processes') as $f) {
      $d   = jread($f);
      $pid = $d['@id'] ?? null;
      if (!$pid) { continue; }

      // processes
      $stmts['processes']->execute([
        ':uuid'        => $pid,
        ':name'        => $d['name'] ?? null,
        ':ptype'       => $d['processType'] ?? null,
        ':category'    => $d['category'] ?? null,
        ':description' => $d['description'] ?? null,
        ':version'     => $d['version'] ?? null,
        ':last_change' => isoToMySql($d['lastChange'] ?? null),
        ':tags'        => (isset($d['tags']) && is_array($d['tags'])) ? implode(',', $d['tags']) : null,
        ':valid_from'  => isoToMySql(jval($d,'processDocumentation.creationDate')),
        ':valid_until' => null,
        ':time_desc'   => null,
        ':loc_uuid'    => jval($d,'location.@id'),
        ':geo_desc'    => jval($d,'location.name'),
        ':tech_desc'   => null,
        ':dq_proc'     => null,
        ':dq_data'     => null,
        ':dq_flow'     => null,
        ':dq_social'   => null,
        ':created_at'  => isoToMySql($d['lastChange'] ?? null) ?? date('Y-m-d H:i:s'),
      ]);

      // documentation
      $pd = $d['processDocumentation'] ?? null;
      if (is_array($pd)) {
        $lciMethod   = $pd['lciMethod'] ?? $pd['LCIMethod'] ?? null;
        $modelConsts = $pd['modelingConstants'] ?? null;
        $dsComp      = $pd['dataCompleteness'] ?? jval($pd,'dataQualityIndicators.dataCompleteness');
        $dsSelect    = $pd['dataSelection'] ?? null;
        $dsTreat     = $pd['dataTreatment'] ?? null;
        $dsSample    = $pd['samplingProcedure'] ?? null;
        $dsPeriod    = $pd['dataCollectionPeriod'] ?? null;
        if (!$dsPeriod) {
          $tStart = jval($pd, 'time.startDate');
          $tEnd   = jval($pd, 'time.endDate');
          if ($tStart || $tEnd) { $dsPeriod = trim(($tStart ?? '').' - '.($tEnd ?? '')); }
        }
        $dsAdvice    = $pd['useAdvice'] ?? null;
        $complText   = $pd['completenessText'] ?? null;

        $sourcesText = $pd['sourcesText'] ?? null;
        if (!$sourcesText && isset($pd['sources']) && is_array($pd['sources'])) {
          $names = [];
          foreach ($pd['sources'] as $sx) {
            if (is_array($sx)) $names[] = $sx['name'] ?? $sx['shortName'] ?? $sx['@id'] ?? '';
          }
          $names = array_filter($names);
          if ($names) $sourcesText = implode('; ', $names);
        }

        $pubUuid = jval($pd, 'publication.@id') ?? jval($pd, 'referenceToPublishedSource.@id') ?? null;
        if ($pubUuid) { $qHasSource->execute([$pubUuid]); if (!$qHasSource->fetchColumn()) { $pubUuid = null; } }

        $project      = $pd['projectDescription'] ?? $pd['project'] ?? null;
        $intendedApp  = $pd['intendedApplication'] ?? null;
        $creationDate = isoToMySql($pd['creationDate'] ?? null);
        $copyright    = b01($pd['isCopyrightProtected'] ?? null);
        $accessUse    = $pd['accessAndUseRestrictions'] ?? $pd['restrictions'] ?? null;

        $stmts['process_documentation']->execute([
          ':proc_uuid'               => $pid,
          ':lci_method'              => $lciMethod,
          ':modeling_constants'      => $modelConsts,
          ':ds_data_completeness'    => $dsComp,
          ':ds_data_selection'       => $dsSelect,
          ':ds_data_treatment'       => $dsTreat,
          ':ds_sampling_procedure'   => $dsSample,
          ':ds_collection_period'    => $dsPeriod,
          ':ds_use_advice'           => $dsAdvice,
          ':completeness_text'       => $complText,
          ':sources_text'            => $sourcesText,
          ':publication_source_uuid' => $pubUuid,
          ':project'                 => $project,
          ':intended_application'    => $intendedApp,
          ':creation_date'           => $creationDate,
          ':copyright_flag'          => $copyright,
          ':access_use_restrictions' => $accessUse,
        ]);
      }

      // exchanges
      foreach (($d['exchanges'] ?? []) as $ex) {
        $isIn   = b01($ex['isInput'] ?? false);
        $isRef  = b01($ex['isQuantitativeReference'] ?? false);
        $amount = isset($ex['amount']) ? (float)$ex['amount'] : null;
        $intId  = isset($ex['internalId']) ? (int)$ex['internalId'] : null;

        $flowId = jval($ex, 'flow.@id');
        $unitId = jval($ex, 'unit.@id');
        $fpId   = jval($ex, 'flowProperty.@id');

        // remap de unidad si fue alias
        if ($unitId && isset($unitUuidAlias[$unitId])) {
          $unitId = $unitUuidAlias[$unitId];
        }

        $category = $ex['category'] ?? null;
        $locFromFlow = null;
        if (!$category || !jval($ex,'location.@id')) {
          $qFlowMeta->execute([$flowId]);
          if ($m = $qFlowMeta->fetch(PDO::FETCH_ASSOC)) {
            if (!$category)    $category    = $m['category'] ?? null;
            $locFromFlow = $m['location_uuid'] ?? null;
          }
        }
        $locUuid = jval($ex, 'location.@id') ?? $locFromFlow;

        [$priceType, $priceValue, $currencyUuid] = mapPrice($ex);
        [$uncType, $meanMode, $sdGsd, $minV, $maxV] = mapUncertainty($ex['uncertainty'] ?? null);
        
        // === Normalizar PRECIO ===
        if ($priceType === null || $priceType === '') { $priceType = 'NA'; }
        else {
          $pt = strtoupper((string)$priceType);
          if (!in_array($pt, ['COST','REVENUE'], true)) { $pt = 'COST'; }
          if ($priceValue === null || $priceValue === '') { $pt = 'NA'; $priceValue = null; $currencyUuid = null; }
          $priceType = $pt;
        }
        if ($priceType === 'NA') { $priceValue = null; $currencyUuid = null; }

        // === Normalizar INCERTIDUMBRE ===
        $ALLOWED_UNC = ['NONE','NORMAL','LOGNORMAL','TRIANGULAR'];
        if ($uncType === null || $uncType === '') { $uncType = 'NONE'; }
        else {
          $uncType = strtoupper((string)$uncType);
          if (!in_array($uncType, $ALLOWED_UNC, true)) { $uncType = 'NONE'; }
        }
        if ($uncType === 'NONE') {
          $meanMode = null; $sdGsd = null; $minV = null; $maxV = null;
        } elseif ($uncType === 'TRIANGULAR') {
          $sdGsd = null;
        }
        
        $unitNameFromEx = jval($ex, 'unit.name') ?? null;
        list($fpIdResolved, $unitIdResolved) = creea_resolveFpAndUnit($pdo, $flowId, $fpId, $unitId, $unitNameFromEx);
        $fpId   = $fpIdResolved;
        $unitId = $unitIdResolved;

        // === Chequeo unit_group: si difiere, LOG + skip ===
        $ugFp   = $fpId ? creea_unitGroupOfFp($pdo, $fpId) : null;
        $ugUnit = $unitId ? creea_unitGroupOfUnit($pdo, $unitId) : null;
        if ($ugFp && $ugUnit && $ugFp !== $ugUnit) {
          $kind = ($category === 'INPUT') ? 'IN' : (($category === 'OUTPUT') ? 'OUT' : 'UNK');
          creea_logMismatch([
            'dataset'            => $dataset ?? '',
            'process_uuid'       => $pid ?? '',
            'exchange_id'        => $intId ?? '',
            'kind'               => $kind,
            'flow_uuid'          => $flowId ?? '',
            'flow_property_uuid' => $fpId ?? '',
            'unit_uuid'          => $unitId ?? '',
            'fp_unit_group'      => $ugFp,
            'unit_unit_group'    => $ugUnit,
            'note'               => 'Unidad no pertenece al unit_group de la FP. Exchange omitido.'
          ]);
          continue; // saltamos este exchange para no detonar el trigger 1644
        }
        
        $isAvoided = b01($ex['isAvoidedProduct'] ?? $ex['isAvoided'] ?? false);

        $provUuid = jval($ex, 'defaultProvider.@id') ?? jval($ex, 'provider.@id');
        $provName = jval($ex, 'defaultProvider.name') ?? jval($ex, 'provider.name');
        if (!$provName && $provUuid) { $qProcName->execute([$provUuid]); $provName = $qProcName->fetchColumn() ?: null; }
        // Resolver provider (si no existe, buscar por flow/location; si no hay, dejar NULL)
        $provUuid = creea_resolve_provider($pdo, $provUuid, (string)$flowId, (string)$locUuid);
        if (!$provUuid) {
          $provUuid = null;
          creea_logMismatch([
            'dataset'            => (string)$dataset,
            'process_uuid'       => (string)$pid,
            'exchange_id'        => (string)$intId,
            'kind'               => 'IN',
            'flow_uuid'          => (string)$flowId,
            'given_provider'     => (string)($provName ?? ''),
            'note'               => 'FK provider: no existe; seteado a NULL'
          ]);
        }

        $dqText = jval($ex, 'dqEntry.text') ?? ($ex['dqEntry'] ?? null);
        $desc   = $ex['description'] ?? null;

        if ($isIn) {
          $hit = false;
          if ($intId !== null) { $qFindPi->execute([$pid, $intId]); $hit = $qFindPi->fetch(PDO::FETCH_ASSOC); }
          if ($hit && ($hit['uuid'] ?? null)) {
            if (creea_should_skip_exchange($pdo, $fpId, $unitId, $dataset ?? '', $pid ?? '', $intId ?? '', $flowId ?? '', $category ?? '', $unitNameFromEx ?? null)) { continue; }
            creea_exec_with_unit_fallback(
              $pdo, $qUpdPi,
              [
                $flowId, $category, $amount, $unitId, $fpId,
                $priceType, $priceValue, $currencyUuid,
                $uncType, $meanMode, $sdGsd, $minV, $maxV,
                $isAvoided, $provUuid, $provName, $dqText,
                $locUuid, $desc, $isRef,
                $hit['uuid']
              ],
              'process_inputs', 'IN', 3, 4,
              (string)($dataset ?? ''), (string)($pid ?? ''), (string)($intId ?? ''), (string)($flowId ?? '')
            );
          } else {
            if (creea_should_skip_exchange($pdo, $fpId, $unitId, $dataset ?? '', $pid ?? '', $intId ?? '', $flowId ?? '', $category ?? '', $unitNameFromEx ?? null)) { continue; }
            creea_exec_with_unit_fallback(
              $pdo, $qInsPi,
              [
                uuidv4(), $pid, $intId, $flowId, $category, $amount, $unitId,
                $fpId, $priceType, $priceValue, $currencyUuid,
                $uncType, $meanMode, $sdGsd, $minV, $maxV,
                $isAvoided, $provUuid, $provName, $dqText, $locUuid, $desc, $isRef
              ],
              'process_inputs', 'IN', 6, 7,
              (string)($dataset ?? ''), (string)($pid ?? ''), (string)($intId ?? ''), (string)($flowId ?? '')
            );
          }
        } else {
          $hit = false;
          if ($intId !== null) { $qFindPo->execute([$pid, $intId]); $hit = $qFindPo->fetch(PDO::FETCH_ASSOC); }
          if ($hit && ($hit['uuid'] ?? null)) {
            if (creea_should_skip_exchange($pdo, $fpId, $unitId, $dataset ?? '', $pid ?? '', $intId ?? '', $flowId ?? '', $category ?? '', $unitNameFromEx ?? null)) { continue; }
            creea_exec_with_unit_fallback(
              $pdo, $qUpdPo,
              [
                $flowId, $category, $amount, $unitId, $fpId,
                $priceType, $priceValue, $currencyUuid,
                $uncType, $meanMode, $sdGsd, $minV, $maxV,
                $isAvoided, $provUuid, $provName, $dqText,
                $locUuid, $desc, $isRef,
                $hit['uuid']
              ],
              'process_outputs', 'OUT', 3, 4,
              (string)($dataset ?? ''), (string)($pid ?? ''), (string)($intId ?? ''), (string)($flowId ?? '')
            );
          } else {
            if (creea_should_skip_exchange($pdo, $fpId, $unitId, $dataset ?? '', $pid ?? '', $intId ?? '', $flowId ?? '', $category ?? '', $unitNameFromEx ?? null)) { continue; }
            creea_exec_with_unit_fallback(
              $pdo, $qInsPo,
              [
                uuidv4(), $pid, $intId, $flowId, $category, $amount, $unitId,
                $fpId, $priceType, $priceValue, $currencyUuid,
                $uncType, $meanMode, $sdGsd, $minV, $maxV,
                $isAvoided, $provUuid, $provName, $dqText, $locUuid, $desc, $isRef
              ],
              'process_outputs', 'OUT', 6, 7,
              (string)($dataset ?? ''), (string)($pid ?? ''), (string)($intId ?? ''), (string)($flowId ?? '')
            );
          }
        }
      } // exchanges
    } // processes
    $pdo->commit();
    
// --- RESUMEN FINAL DEL IMPORT ---
if (!isset($stats)) { $stats = []; }
$sk = (int)($stats['skipped'] ?? 0);
echo "Omitidos por mismatch unidad↔flow_property: {$sk}\n";
echo "Log: /home/u303404040/domains/ciclodevida.mx/secure_lca_inserts/mismatch_exchanges.csv\n";

  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }

  // Fin
}
