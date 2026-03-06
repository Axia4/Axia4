<?php
/**
 * Migration 002: Import existing JSON data from the filesystem into the DB.
 * This runs once on first boot after the schema is created.
 * It is safe to run even if /DATA doesn't have all files – missing files are skipped.
 *
 * $db (PDO) is provided by the migration runner in db.php.
 */

// ── AuthConfig → config table ────────────────────────────────────────────────
$auth_config_file = '/DATA/AuthConfig.json';
if (file_exists($auth_config_file)) {
    $auth_config = json_decode(file_get_contents($auth_config_file), true) ?? [];
    $ins = $db->prepare("INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)");
    foreach ($auth_config as $k => $v) {
        $ins->execute([$k, is_string($v) ? $v : json_encode($v)]);
    }
}

// ── SISTEMA_INSTALADO marker ─────────────────────────────────────────────────
if (file_exists('/DATA/SISTEMA_INSTALADO.txt')) {
    $db->prepare("INSERT OR IGNORE INTO config (key, value) VALUES ('installed', '1')")->execute();
}

// ── Users (/DATA/Usuarios/*.json) ────────────────────────────────────────────
$users_dir = '/DATA/Usuarios';
if (is_dir($users_dir)) {
    $ins_user = $db->prepare(
        "INSERT OR IGNORE INTO users
             (username, display_name, email, password_hash, permissions, google_auth, meta)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $ins_uc = $db->prepare(
        "INSERT OR IGNORE INTO user_centros (user_id, centro_id, role, aulas)
         VALUES (?, ?, ?, ?)"
    );
    $ins_centro = $db->prepare("INSERT OR IGNORE INTO centros (centro_id) VALUES (?)");

    foreach (glob("$users_dir/*.json") ?: [] as $user_file) {
        $username = basename($user_file, '.json');
        $data = json_decode(file_get_contents($user_file), true);
        if (!is_array($data)) {
            continue;
        }
        $permissions = isset($data['permissions']) ? json_encode($data['permissions']) : '[]';
        // Store remaining non-standard keys in meta
        $meta_keys = ['display_name', 'email', 'password_hash', 'permissions', 'entreaulas', 'google_auth'];
        $meta = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, $meta_keys, true)) {
                $meta[$k] = $v;
            }
        }
        $ins_user->execute([
            $username,
            $data['display_name'] ?? '',
            $data['email'] ?? '',
            $data['password_hash'] ?? '',
            $permissions,
            (int) ($data['google_auth'] ?? 0),
            json_encode($meta),
        ]);
        $user_id = (int) $db->lastInsertId();
        if ($user_id === 0) {
            // Already existed – look it up
            $row = $db->prepare("SELECT id FROM users WHERE username = ?")->execute([$username]);
            $user_id = (int) $db->query("SELECT id FROM users WHERE username = " . $db->quote($username))->fetchColumn();
        }

        // Entreaulas centro assignment
        $ea = $data['entreaulas'] ?? [];
        // Support both old single "centro" and new "centros" array
        $centros = [];
        if (!empty($ea['centros']) && is_array($ea['centros'])) {
            $centros = $ea['centros'];
        } elseif (!empty($ea['centro'])) {
            $centros = [$ea['centro']];
        }
        $role = $ea['role'] ?? '';
        $aulas = json_encode($ea['aulas'] ?? []);
        foreach ($centros as $cid) {
            if ($cid === '') {
                continue;
            }
            $ins_centro->execute([$cid]);
            $ins_uc->execute([$user_id, $cid, $role, $aulas]);
        }
    }
}

// ── Invitations (/DATA/Invitaciones_de_usuarios.json) ────────────────────────
$inv_file = '/DATA/Invitaciones_de_usuarios.json';
if (file_exists($inv_file)) {
    $invs = json_decode(file_get_contents($inv_file), true) ?? [];
    $ins = $db->prepare(
        "INSERT OR IGNORE INTO invitations (code, active, single_use) VALUES (?, ?, ?)"
    );
    foreach ($invs as $code => $inv) {
        $ins->execute([
            strtoupper($code),
            (int) ($inv['active'] ?? 1),
            (int) ($inv['single_use'] ?? 1),
        ]);
    }
}

// ── Centros & Aularios (directory structure) ──────────────────────────────────
$centros_base = '/DATA/entreaulas/Centros';
if (is_dir($centros_base)) {
    $ins_centro  = $db->prepare("INSERT OR IGNORE INTO centros (centro_id) VALUES (?)");
    $ins_aulario = $db->prepare(
        "INSERT OR IGNORE INTO aularios (centro_id, aulario_id, name, icon, extra) VALUES (?, ?, ?, ?, ?)"
    );
    foreach (glob("$centros_base/*", GLOB_ONLYDIR) ?: [] as $centro_dir) {
        $centro_id = basename($centro_dir);
        $ins_centro->execute([$centro_id]);

        $aularios_dir = "$centro_dir/Aularios";
        foreach (glob("$aularios_dir/*.json") ?: [] as $aulario_file) {
            $aulario_id = basename($aulario_file, '.json');
            $adata = json_decode(file_get_contents($aulario_file), true);
            if (!is_array($adata)) {
                continue;
            }
            $name  = $adata['name'] ?? $aulario_id;
            $icon  = $adata['icon'] ?? '';
            $extra_keys = ['name', 'icon'];
            $extra = [];
            foreach ($adata as $k => $v) {
                if (!in_array($k, $extra_keys, true)) {
                    $extra[$k] = $v;
                }
            }
            $ins_aulario->execute([$centro_id, $aulario_id, $name, $icon, json_encode($extra)]);
        }

        // SuperCafe menu
        $menu_file = "$centro_dir/SuperCafe/Menu.json";
        if (file_exists($menu_file)) {
            $menu_data = file_get_contents($menu_file);
            $db->prepare("INSERT OR IGNORE INTO supercafe_menu (centro_id, data) VALUES (?, ?)")
               ->execute([$centro_id, $menu_data]);
        }

        // SuperCafe orders
        $comandas_dir = "$centro_dir/SuperCafe/Comandas";
        if (is_dir($comandas_dir)) {
            $ins_order = $db->prepare(
                "INSERT OR IGNORE INTO supercafe_orders
                     (centro_id, order_ref, fecha, persona, comanda, notas, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach (glob("$comandas_dir/*.json") ?: [] as $order_file) {
                $order_ref = basename($order_file, '.json');
                $odata = json_decode(file_get_contents($order_file), true);
                if (!is_array($odata)) {
                    continue;
                }
                $ins_order->execute([
                    $centro_id,
                    $order_ref,
                    $odata['Fecha']   ?? '',
                    $odata['Persona'] ?? '',
                    $odata['Comanda'] ?? '',
                    $odata['Notas']   ?? '',
                    $odata['Estado']  ?? 'Pedido',
                ]);
            }
        }

        // Comedor menu types & daily entries per aulario
        foreach (glob("$aularios_dir/*.json") ?: [] as $aulario_file) {
            $aulario_id = basename($aulario_file, '.json');

            $menu_types_file = "$aularios_dir/$aulario_id/Comedor-MenuTypes.json";
            if (file_exists($menu_types_file)) {
                $db->prepare(
                    "INSERT OR IGNORE INTO comedor_menu_types (centro_id, aulario_id, data) VALUES (?, ?, ?)"
                )->execute([$centro_id, $aulario_id, file_get_contents($menu_types_file)]);
            }

            $comedor_base = "$aularios_dir/$aulario_id/Comedor";
            if (is_dir($comedor_base)) {
                $ins_centry = $db->prepare(
                    "INSERT OR IGNORE INTO comedor_entries (centro_id, aulario_id, year_month, day, data) VALUES (?, ?, ?, ?, ?)"
                );
                foreach (glob("$comedor_base/*", GLOB_ONLYDIR) ?: [] as $ym_dir) {
                    $ym = basename($ym_dir);
                    foreach (glob("$ym_dir/*", GLOB_ONLYDIR) ?: [] as $day_dir) {
                        $day = basename($day_dir);
                        $data_file = "$day_dir/_datos.json";
                        if (file_exists($data_file)) {
                            $ins_centry->execute([
                                $centro_id, $aulario_id, $ym, $day,
                                file_get_contents($data_file),
                            ]);
                        }
                    }
                }
            }

            // Diario entries
            $diario_base = "$aularios_dir/$aulario_id/Diario";
            if (is_dir($diario_base)) {
                $ins_d = $db->prepare(
                    "INSERT OR IGNORE INTO diario_entries (centro_id, aulario_id, entry_date, data) VALUES (?, ?, ?, ?)"
                );
                foreach (glob("$diario_base/*.json") ?: [] as $diario_file) {
                    $entry_date = basename($diario_file, '.json');
                    $ins_d->execute([$centro_id, $aulario_id, $entry_date, file_get_contents($diario_file)]);
                }
            }

            // Panel alumno data
            $alumnos_base = "$aularios_dir/$aulario_id/Alumnos";
            if (is_dir($alumnos_base)) {
                $ins_pa = $db->prepare(
                    "INSERT OR IGNORE INTO panel_alumno (centro_id, aulario_id, alumno, data) VALUES (?, ?, ?, ?)"
                );
                foreach (glob("$alumnos_base/*/", GLOB_ONLYDIR) ?: [] as $alumno_dir) {
                    $alumno = basename($alumno_dir);
                    // Look for Panel.json (used by paneldiario)
                    $panel_files = glob("$alumno_dir/Panel*.json") ?: [];
                    foreach ($panel_files as $pf) {
                        $ins_pa->execute([
                            $centro_id, $aulario_id, $alumno,
                            file_get_contents($pf),
                        ]);
                    }
                }
            }
        }
    }
}

// ── Club config (/DATA/club/config.json) ──────────────────────────────────────
$club_config_file = '/DATA/club/config.json';
if (file_exists($club_config_file)) {
    $db->prepare("INSERT OR IGNORE INTO club_config (id, data) VALUES (1, ?)")
       ->execute([file_get_contents($club_config_file)]);
}

// ── Club events (/DATA/club/IMG/{date}/data.json) ─────────────────────────────
$club_img_dir = '/DATA/club/IMG';
if (is_dir($club_img_dir)) {
    $ins_ev = $db->prepare("INSERT OR IGNORE INTO club_events (date_ref, data) VALUES (?, ?)");
    foreach (glob("$club_img_dir/*/", GLOB_ONLYDIR) ?: [] as $event_dir) {
        $date_ref = basename($event_dir);
        $event_data_file = "$event_dir/data.json";
        $ins_ev->execute([
            $date_ref,
            file_exists($event_data_file) ? file_get_contents($event_data_file) : '{}',
        ]);
    }
}
